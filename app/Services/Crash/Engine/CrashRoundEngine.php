<?php

namespace App\Services\Crash\Engine;

use App\Models\CrashBet;
use App\Models\CrashRound;
use App\Models\CrashTenantSettings;
use App\Models\Tenant;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\Crash\CrashCashoutService;
use App\Services\Crash\CrashLiveBetBroadcaster;
use App\Services\Crash\CrashRoundEmitter;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class CrashRoundEngine
{
    public function __construct(
        private readonly CrashRoundEmitter $emitter,
        private readonly CrashPointSampler $sampler,
        private readonly CrashCashoutService $cashouts,
        private readonly CrashLiveBetBroadcaster $liveBets,
    ) {}

    public function tickTenant(Tenant $tenant): void
    {
        if (! config('crash.engine.enabled', false)) {
            return;
        }

        CrashTenantSettings::query()->firstOrCreate(
            ['tenant_id' => $tenant->getKey()],
            CrashTenantSettings::defaultsForTenant((string) $tenant->getKey()),
        );

        DB::transaction(function () use ($tenant): void {
            /** @var CrashTenantSettings $lockedSettings */
            $lockedSettings = CrashTenantSettings::query()
                ->where('tenant_id', $tenant->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedSettings->engine_enabled) {
                return;
            }

            if ($lockedSettings->game_paused || ! $lockedSettings->game_enabled) {
                return;
            }

            /** @var CrashRound|null $round */
            $round = CrashRound::query()
                ->where('tenant_id', $tenant->getKey())
                ->whereIn('phase', ['betting', 'running'])
                ->orderByDesc('started_at')
                ->orderByDesc('created_at')
                ->lockForUpdate()
                ->first();

            $now = now();

            if ($round === null) {
                if ($this->tenantIsInClosedHold($tenant, $now)) {
                    return;
                }

                $this->createBettingRound($tenant, $lockedSettings);

                return;
            }

            if ($round->phase === 'betting') {
                $closesAt = $round->betting_closes_at;

                if ($closesAt !== null && $now->greaterThanOrEqualTo($closesAt)) {
                    $this->closeBettingAndStartRunning($tenant, $lockedSettings, $round, $now);

                    return;
                }

                $this->maybeBroadcastBettingPulse($tenant, $lockedSettings, $round);

                return;
            }

            if ($round->phase !== 'running') {
                return;
            }

            $this->normalizeRunningStartedAtIfInFuture($round, $now, $tenant);

            $this->cashouts->tryAutoCashouts($round);
            $round->refresh();

            if ($round->phase !== 'running') {
                return;
            }

            // A `running` row with unreachable crash point (growth≈0, missing crash/start, corrupt row)
            // never reaches `betting` again → API returns "No betting round is available".
            if ($this->runningRoundNeedsForcedBust($round, $lockedSettings, $now)) {
                $this->sanitizeRunningRoundBeforeBust($round, $now);
                Log::warning('Crash round auto-busted: running phase could not advance to crash multiplier.', [
                    'tenant_slug' => $tenant->slug,
                    'crash_round_id' => (string) $round->getKey(),
                    'external_round_id' => $round->external_round_id,
                    'crash_mult' => $round->crash_point_multiplier,
                    'growth_snapshot' => $round->growth_per_second_snapshot,
                    'settings_growth' => $lockedSettings->growth_per_second,
                ]);

                $this->finalizeBustedRound($tenant, $lockedSettings, $round->fresh(), $now);

                return;
            }

            if (CrashMultiplierClock::hasReachedCrashPoint($round, $now)) {
                $this->finalizeBustedRound($tenant, $lockedSettings, $round, $now);

                return;
            }

            $this->pulseRunningBroadcast($tenant, $lockedSettings, $round);
        });
    }

    private function runningRoundNeedsForcedBust(
        CrashRound $round,
        CrashTenantSettings $settings,
        CarbonInterface $now,
    ): bool {
        if ($round->phase !== 'running') {
            return false;
        }

        if ($round->crash_point_multiplier === null || $round->started_running_at === null) {
            return true;
        }

        $growth = (float) ($round->growth_per_second_snapshot
            ?? $settings->growth_per_second
            ?? config('crash.defaults.growth_per_second', 0.055));

        if ($growth <= 1e-9 && ! CrashMultiplierClock::hasReachedCrashPoint($round, $now)) {
            return true;
        }

        if ($round->started_running_at !== null && $round->crash_point_multiplier !== null) {
            $crash = max(1.01, (float) $round->crash_point_multiplier);
            $g = max($growth, 1e-12);
            $theoretical = log($crash) / $g;
            $margin = max(1.0, (float) config('crash.engine.running_watchdog_margin', 15));
            $grace = max(30.0, (float) config('crash.engine.running_watchdog_grace_seconds', 120));
            $ceiling = max($grace, (float) config('crash.engine.running_watchdog_ceiling_seconds', 86_400));

            $elapsed = max(0, $now->getTimestamp() - $round->started_running_at->getTimestamp());
            $limit = min($ceiling, max($grace, ($theoretical * $margin) + 60.0));

            if ($elapsed > $limit) {
                return true;
            }
        }

        return false;
    }

    private function normalizeRunningStartedAtIfInFuture(CrashRound $round, CarbonInterface $now, Tenant $tenant): void
    {
        if ($round->started_running_at === null) {
            return;
        }

        if ($now->lessThan($round->started_running_at)) {
            Log::warning('Crash running round started_running_at was ahead of server clock; clamped to now.', [
                'tenant_slug' => $tenant->slug,
                'crash_round_id' => (string) $round->getKey(),
                'external_round_id' => $round->external_round_id,
                'had_started_at' => $round->started_running_at->toIso8601String(),
            ]);
            $round->started_running_at = Carbon::parse($now);
            $round->save();
        }
    }

    private function sanitizeRunningRoundBeforeBust(CrashRound $round, CarbonInterface $now): void
    {
        if ($round->crash_point_multiplier === null) {
            $round->crash_point_multiplier = max(1.01, round((float) ($round->last_multiplier ?? 1), 4));
        }

        if ($round->started_running_at === null) {
            $round->started_running_at = $now->copy()->subSecond();
        }

        $round->save();
    }

    /** Refund stakes for open bets when a betting round is cancelled (operator). */
    public function cancelBettingRoundWithRefunds(string $crashRoundPk): void
    {
        DB::transaction(function () use ($crashRoundPk): void {
            /** @var CrashRound|null $round */
            $round = CrashRound::query()->whereKey($crashRoundPk)->lockForUpdate()->first();
            if ($round === null) {
                abort(404, 'Round not found.');
            }

            if ($round->phase !== 'betting') {
                abort(409, 'Only betting rounds can be cancelled this way.');
            }

            /** @var Collection<int, CrashBet> $bets */
            $bets = CrashBet::query()
                ->where('crash_round_id', $round->id)
                ->where('status', 'open')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($bets as $bet) {
                /** @var Wallet|null $wallet */
                $wallet = Wallet::query()->where('user_id', $bet->user_id)->lockForUpdate()->first();
                if ($wallet === null) {
                    continue;
                }

                $stake = (int) $bet->stake_minor;
                $wallet->balance_minor += $stake;
                $wallet->save();

                WalletTransaction::query()->create([
                    'wallet_id' => $wallet->id,
                    'type' => 'crash_refund',
                    'amount_minor' => $stake,
                    'balance_after_minor' => $wallet->balance_minor,
                    'meta' => [
                        'crash_round_id' => (string) $round->id,
                        'crash_bet_id' => (string) $bet->id,
                    ],
                ]);

                $bet->status = 'refunded';
                $bet->save();
            }

            $round->phase = 'cancelled';
            $round->ended_at = now();
            $round->save();
        });
    }

    private function createBettingRound(Tenant $tenant, CrashTenantSettings $settings): CrashRound
    {
        $external = (string) Str::uuid();
        $pfEnabled = (bool) $settings->provably_fair_enabled;
        $src = 'algo';

        $seed = null;
        $nonce = null;
        $commitment = null;

        if ($pfEnabled) {
            $seed = bin2hex(random_bytes(32));
            $nonce = bin2hex(random_bytes(8));
            $commitment = hash('sha256', $seed.'|'.$nonce.'|'.$external);
        }

        if ($settings->pending_operator_crash_multiplier !== null) {
            $crash = round((float) $settings->pending_operator_crash_multiplier, 4);
            $crash = max(1.01, min((float) $settings->max_multiplier_cap, $crash));
            $settings->pending_operator_crash_multiplier = null;
            $settings->save();
            $src = 'operator_override';
        } else {
            $u = $pfEnabled && is_string($seed) && is_string($nonce)
                ? $this->sampler->uniformFromPf($seed, $nonce, $external)
                : $this->sampler->randomUniform();

            $crash = $this->sampler->multiplierFromUniform(
                $u,
                $settings->houseEdgeFraction(),
                minMult: 1.01,
                maxCap: (float) $settings->max_multiplier_cap,
            );
        }

        /** @var CrashRound $round */
        $round = CrashRound::query()->create([
            'tenant_id' => $tenant->getKey(),
            'external_round_id' => $external,
            'phase' => 'betting',
            'last_multiplier' => 1,
            'tick_count' => 0,
            'started_at' => now(),
            'ended_at' => null,
            'crash_point_multiplier' => $crash,
            'hash_commitment' => $commitment,
            'revealed_server_seed' => null,
            'pf_server_seed' => $seed,
            'pf_nonce' => $nonce,
            'generation_source' => $src,
            'betting_opens_at' => now(),
            'betting_closes_at' => now()->addSeconds(max(2, (int) $settings->betting_duration_seconds)),
            'started_running_at' => null,
            'max_multiplier_cap_snapshot' => $settings->max_multiplier_cap,
            'growth_per_second_snapshot' => null,
            'last_tick_broadcast_at' => null,
        ]);

        $this->maybeBroadcastBettingPulse($tenant, $settings, $round->fresh(), force: true);

        return $round;
    }

    private function closeBettingAndStartRunning(
        Tenant $tenant,
        CrashTenantSettings $settings,
        CrashRound $round,
        Carbon $now,
    ): void {
        if ($round->crash_point_multiplier === null) {
            if ($settings->provably_fair_enabled && is_string($round->pf_server_seed) && is_string($round->pf_nonce)) {
                $u = $this->sampler->uniformFromPf($round->pf_server_seed, $round->pf_nonce, $round->external_round_id);
                $round->crash_point_multiplier = $this->sampler->multiplierFromUniform(
                    $u,
                    $settings->houseEdgeFraction(),
                    minMult: 1.01,
                    maxCap: (float) $settings->max_multiplier_cap,
                );
                $round->generation_source ??= 'algo';
            } else {
                $round->crash_point_multiplier = max(1.01, round((float) ($round->last_multiplier ?? 1), 4));
                $round->generation_source ??= 'recovered_missing_commitment';
            }
        }

        $round->started_running_at = $now;
        $round->phase = 'running';
        $round->growth_per_second_snapshot = $settings->growth_per_second;
        $round->max_multiplier_cap_snapshot = $settings->max_multiplier_cap;
        $round->save();

        $fresh = $round->fresh();

        $this->pulseRunningBroadcast($tenant, $settings, $fresh, force: true);
    }

    private function finalizeBustedRound(
        Tenant $tenant,
        CrashTenantSettings $settings,
        CrashRound $round,
        Carbon $now,
    ): void {
        $this->cashouts->tryAutoCashouts($round);

        CrashBet::query()
            ->where('crash_round_id', $round->id)
            ->where('status', 'open')
            ->update(['status' => 'lost']);

        CrashBet::query()
            ->where('crash_round_id', $round->id)
            ->where('status', 'lost')
            ->with(['round.tenant:id,slug', 'user:id,name,username,email'])
            ->orderBy('created_at')
            ->each(fn (CrashBet $bet) => $this->liveBets->broadcast($bet, 'lost'));

        if (is_string($round->pf_server_seed)) {
            $round->revealed_server_seed = $round->pf_server_seed;
            $round->pf_server_seed = null;
        }

        $crash = (float) $round->crash_point_multiplier;

        $round->phase = 'busted';
        $round->last_multiplier = $crash;
        $round->ended_at = $now;
        $round->tick_count = ((int) $round->tick_count) + 1;
        $round->last_tick_broadcast_at = $now;
        $round->save();

        $fresh = $round->fresh();

        $this->emitter->emitTick(
            tenantSlug: $tenant->slug,
            roundId: $fresh->external_round_id,
            multiplier: $crash,
            phase: 'busted',
            broadcastExtra: $this->envelopeExtras($settings, $fresh),
            skipRecording: true,
        );

        if (
            max(0.0, (float) config('crash.engine.closed_hold_seconds', 2)) <= 0
            && $settings->game_enabled === true
            && ! $settings->game_paused
        ) {
            $this->createBettingRound($tenant, $settings);
        }
        // Otherwise, do not create the next betting round immediately. The
        // closed hold lets every client show the final bust multiplier before
        // bets reopen.
    }

    private function tenantIsInClosedHold(Tenant $tenant, CarbonInterface $now): bool
    {
        $holdSeconds = max(0.0, (float) config('crash.engine.closed_hold_seconds', 2));
        if ($holdSeconds <= 0) {
            return false;
        }

        /** @var CrashRound|null $lastBusted */
        $lastBusted = CrashRound::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('phase', 'busted')
            ->whereNotNull('ended_at')
            ->orderByDesc('ended_at')
            ->orderByDesc('created_at')
            ->first();

        if ($lastBusted === null || $lastBusted->ended_at === null) {
            return false;
        }

        return $now->lessThan($lastBusted->ended_at->copy()->addSeconds($holdSeconds));
    }

    private function pulseRunningBroadcast(
        Tenant $tenant,
        CrashTenantSettings $settings,
        CrashRound $round,
        bool $force = false,
    ): void {
        if (! $force && ! $this->shouldEmitNow($settings, $round)) {
            return;
        }

        $now = now();

        $mult = CrashMultiplierClock::displayAt($round, $now);

        $round->last_multiplier = $mult;
        $round->tick_count = ((int) $round->tick_count) + 1;
        $round->last_tick_broadcast_at = $now;
        $round->save();

        $this->emitter->emitTick(
            tenantSlug: $tenant->slug,
            roundId: $round->external_round_id,
            multiplier: $mult,
            phase: 'running',
            broadcastExtra: $this->envelopeExtras($settings, $round->fresh()),
            skipRecording: true,
        );
    }

    private function maybeBroadcastBettingPulse(
        Tenant $tenant,
        CrashTenantSettings $settings,
        CrashRound $round,
        bool $force = false,
    ): void {
        if (! $force && ! $this->shouldEmitNow($settings, $round)) {
            return;
        }

        $now = now();

        $round->last_multiplier = 1.0;
        $round->tick_count = ((int) $round->tick_count) + 1;
        $round->last_tick_broadcast_at = $now;
        $round->save();

        $this->emitter->emitTick(
            tenantSlug: $tenant->slug,
            roundId: $round->external_round_id,
            multiplier: 1.0,
            phase: 'betting',
            broadcastExtra: $this->envelopeExtras($settings, $round->fresh()),
            skipRecording: true,
        );
    }

    private function shouldEmitNow(CrashTenantSettings $settings, CrashRound $round): bool
    {
        $hz = max(1, (int) $settings->tick_hz);
        $minIntervalSeconds = 1 / $hz;
        $last = $round->last_tick_broadcast_at;

        if ($last === null) {
            return true;
        }

        return now()->greaterThanOrEqualTo($last->copy()->addSeconds($minIntervalSeconds));
    }

    /**
     * @return array<string, mixed>
     */
    private function envelopeExtras(CrashTenantSettings $settings, CrashRound $round): array
    {
        return [
            'crash_round_pk' => (string) $round->id,
            'server_ts' => microtime(true),
            'betting_opens_at' => $round->betting_opens_at?->toIso8601String(),
            'betting_closes_at' => $round->betting_closes_at?->toIso8601String(),
            'started_running_at' => $round->started_running_at?->toIso8601String(),
            // Lets the client run the exponential curve locally and interpolate
            // smoothly between server ticks instead of stepping.
            'growth_per_second' => (float) ($round->growth_per_second_snapshot
                ?? $settings->growth_per_second
                ?? config('crash.defaults.growth_per_second', 0.18)),
            'hash_commitment' => $round->hash_commitment,
            'revealed_server_seed' => $round->revealed_server_seed,
            'pf_nonce' => $round->pf_nonce,
            'provably_fair_enabled' => (bool) $settings->provably_fair_enabled,
            'generation_source' => $round->generation_source,
            'crash_point_multiplier' => $round->phase === 'busted' ? (float) $round->crash_point_multiplier : null,
        ];
    }
}
