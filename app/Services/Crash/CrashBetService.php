<?php

namespace App\Services\Crash;

use App\Models\CrashBet;
use App\Models\CrashRound;
use App\Models\CrashTenantSettings;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class CrashBetService
{
    /**
     * Place a Crash bet atomically after locking tenant settings and the latest betting-round row for the tenant.
     */
    public function placeBetTransactional(
        User $user,
        string $tenantId,
        int $stakeMinor,
        ?float $autoCashoutMultiplier,
        ?string $placeIdempotencyKey = null,
    ): CrashBet {
        return DB::transaction(function () use ($user, $tenantId, $stakeMinor, $autoCashoutMultiplier, $placeIdempotencyKey) {
            /** @var CrashTenantSettings|null $settings */
            $settings = CrashTenantSettings::query()->where('tenant_id', $tenantId)->lockForUpdate()->first();

            if ($settings === null) {
                abort(503, 'Crash is not configured for this tenant.');
            }

            if (! $settings->game_enabled || $settings->game_paused) {
                abort(423, 'Crash is paused for this tenant.');
            }

            if (! $settings->engine_enabled || ! config('crash.engine.enabled', false)) {
                abort(423, 'Crash engine is not accepting bets right now.');
            }

            if ($placeIdempotencyKey !== null) {
                /** @var CrashBet|null $existing */
                $existing = CrashBet::query()
                    ->where('user_id', $user->id)
                    ->where('place_idempotency_key', $placeIdempotencyKey)
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    if ((int) $existing->stake_minor !== $stakeMinor) {
                        abort(409, 'Idempotency-Key reused with a different payload.');
                    }
                    $prevAuto = $existing->auto_cashout_multiplier !== null ? (float) $existing->auto_cashout_multiplier : null;
                    if (($prevAuto === null) !== ($autoCashoutMultiplier === null)
                        || ($autoCashoutMultiplier !== null && abs($prevAuto - $autoCashoutMultiplier) > 1e-6)) {
                        abort(409, 'Idempotency-Key reused with a different payload.');
                    }

                    return $existing;
                }
            }

            /** @var CrashRound|null $round */
            $round = CrashRound::query()
                ->where('tenant_id', $tenantId)
                ->where('phase', 'betting')
                ->orderByDesc('created_at')
                ->lockForUpdate()
                ->first();

            if ($round === null) {
                abort(409, 'No betting round is available.');
            }

            return $this->placeBet($user, $settings, $round, $stakeMinor, $autoCashoutMultiplier, $placeIdempotencyKey);
        });
    }

    /**
     * Cancel the user's open bet while its round is still in the betting window.
     *
     * @return array{bet: CrashBet, balance_after_minor: int}
     */
    public function cancelOpenBetTransactional(User $user, string $tenantId): array
    {
        return DB::transaction(function () use ($user, $tenantId): array {
            /** @var CrashBet|null $bet */
            $bet = CrashBet::query()
                ->where('user_id', $user->id)
                ->where('status', 'open')
                ->whereHas('round', fn ($q) => $q
                    ->where('tenant_id', $tenantId)
                    ->where('phase', 'betting'))
                ->lockForUpdate()
                ->latest('created_at')
                ->first();

            if ($bet === null) {
                abort(404, 'No queued bet is available to cancel.');
            }

            /** @var CrashRound $round */
            $round = CrashRound::query()
                ->whereKey($bet->crash_round_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($round->phase !== 'betting') {
                abort(409, 'Bet is already in progress.');
            }

            if ($round->betting_closes_at !== null && now()->greaterThanOrEqualTo($round->betting_closes_at)) {
                abort(409, 'Betting window has closed.');
            }

            /** @var Wallet|null $wallet */
            $wallet = Wallet::query()->where('user_id', $user->id)->lockForUpdate()->first();
            if ($wallet === null) {
                abort(404, 'Wallet not found.');
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
                    'reason' => 'player_cancelled_queued_bet',
                ],
            ]);

            $bet->status = 'refunded';
            $bet->save();

            Log::channel('gaming')->info('crash.bet.cancelled', [
                'event' => 'bet_cancelled',
                'tenant_id' => (string) $round->tenant_id,
                'user_id' => (string) $user->id,
                'crash_bet_id' => (string) $bet->id,
                'crash_round_id' => (string) $round->id,
                'stake_minor' => $stake,
                'balance_after_minor' => (int) $wallet->balance_minor,
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            return [
                'bet' => $bet,
                'balance_after_minor' => (int) $wallet->balance_minor,
            ];
        });
    }

    private function placeBet(
        User $user,
        CrashTenantSettings $settings,
        CrashRound $round,
        int $stakeMinor,
        ?float $autoCashoutMultiplier,
        ?string $placeIdempotencyKey,
    ): CrashBet {
        if ($round->phase !== 'betting') {
            abort(409, 'Betting is closed for this round.');
        }

        $now = now();
        if ($round->betting_closes_at !== null && $now->greaterThanOrEqualTo($round->betting_closes_at)) {
            abort(409, 'Betting window has closed.');
        }

        $min = (int) $settings->min_bet_minor;
        $max = (int) $settings->max_bet_minor;

        if ($stakeMinor < $min || $stakeMinor > $max) {
            abort(422, 'Stake is outside tenant limits.');
        }

        $cap = (float) $settings->max_multiplier_cap;

        if ($autoCashoutMultiplier !== null) {
            if ($autoCashoutMultiplier <= 1 || $autoCashoutMultiplier > $cap) {
                abort(422, 'Invalid auto cash-out multiplier.');
            }
        }

        /** @var CrashBet|null $existingForRound */
        $existingForRound = CrashBet::query()
            ->where('crash_round_id', $round->id)
            ->where('user_id', $user->id)
            ->lockForUpdate()
            ->first();

        if ($existingForRound !== null && $existingForRound->status !== 'refunded') {
            abort(409, 'You already have a bet for this round.');
        }

        /** @var Wallet|null $wallet */
        $wallet = Wallet::query()->where('user_id', $user->id)->lockForUpdate()->first();
        if ($wallet === null) {
            abort(404, 'Wallet not found.');
        }

        if ($wallet->balance_minor < $stakeMinor) {
            abort(422, 'Insufficient balance.');
        }

        $wallet->balance_minor -= $stakeMinor;
        $wallet->save();

        WalletTransaction::query()->create([
            'wallet_id' => $wallet->id,
            'type' => 'crash_stake',
            'amount_minor' => -$stakeMinor,
            'balance_after_minor' => $wallet->balance_minor,
            'meta' => [
                'crash_round_id' => (string) $round->id,
                'crash_bet_id' => null,
            ],
        ]);

        if ($existingForRound !== null) {
            $existingForRound->stake_minor = $stakeMinor;
            $existingForRound->auto_cashout_multiplier = $autoCashoutMultiplier;
            $existingForRound->cashout_multiplier = null;
            $existingForRound->payout_minor = null;
            $existingForRound->status = 'open';
            $existingForRound->meta = null;
            $existingForRound->place_idempotency_key = $placeIdempotencyKey;
            $existingForRound->save();
            $bet = $existingForRound;
        } else {
            /** @var CrashBet $bet */
            $bet = CrashBet::query()->create([
                'crash_round_id' => $round->id,
                'user_id' => $user->id,
                'stake_minor' => $stakeMinor,
                'auto_cashout_multiplier' => $autoCashoutMultiplier,
                'cashout_multiplier' => null,
                'payout_minor' => null,
                'status' => 'open',
                'meta' => null,
                'place_idempotency_key' => $placeIdempotencyKey,
            ]);
        }

        Log::channel('gaming')->info('crash.bet.placed', [
            'event' => 'bet_placed',
            'tenant_id' => (string) $round->tenant_id,
            'user_id' => (string) $user->id,
            'crash_bet_id' => (string) $bet->id,
            'crash_round_id' => (string) $round->id,
            'external_round_id' => $round->external_round_id,
            'stake_minor' => $stakeMinor,
            'auto_cashout_multiplier' => $autoCashoutMultiplier,
            'place_idempotency_key' => $placeIdempotencyKey,
            'balance_after_minor' => (int) $wallet->balance_minor,
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return $bet;
    }
}
