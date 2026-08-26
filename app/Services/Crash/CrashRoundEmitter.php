<?php

namespace App\Services\Crash;

use App\Events\CrashGameOpsPulse;
use App\Events\CrashGameOpsPulseImmediate;
use App\Events\CrashGameTick;
use App\Events\CrashGameTickImmediate;
use App\Models\CrashBet;
use App\Models\CrashRound;
use App\Models\CrashTenantSettings;
use App\Models\Tenant;
use App\Services\Crash\CrashRecording\CrashRoundRecordingService;
use App\Support\Crash\CrashPlayerBroadcastSanitizer;

class CrashRoundEmitter
{
    public function __construct(
        private readonly CrashRoundRecordingService $recording,
    ) {}

    public function emitDemoTick(string $tenantSlug): void
    {
        $roundId = $this->recording->stickyExternalRoundId($tenantSlug);
        $this->emitTick(
            tenantSlug: $tenantSlug,
            roundId: $roundId,
            multiplier: round(1 + random_int(1, 450) / 100, 2),
            phase: 'running',
            broadcastExtra: [
                'server_seed_hash' => null,
                'round_commitment_stub' => 'demo-not-implemented',
                'provably_fair_demo' => true,
            ],
        );
    }

    public function emitTick(
        string $tenantSlug,
        string $roundId,
        float $multiplier,
        string $phase,
        array $broadcastExtra = [],
        bool $skipRecording = false,
    ): void {
        $playerExtras = CrashPlayerBroadcastSanitizer::sanitizeForPhase($broadcastExtra, $phase);

        event($this->makeTick(
            tenantSlug: $tenantSlug,
            roundId: $roundId,
            multiplier: $multiplier,
            phase: $phase,
            broadcastExtra: $playerExtras,
        ));

        $this->emitOperatorPulseIfApplicable($tenantSlug, $roundId, $multiplier, $phase, $broadcastExtra);

        if (! $skipRecording) {
            $this->recording->recordTick(
                tenantSlug: $tenantSlug,
                externalRoundId: $roundId,
                multiplier: $multiplier,
                phase: $phase,
            );
        }
    }

    protected function makeTick(
        string $tenantSlug,
        string $roundId,
        float $multiplier,
        string $phase,
        array $broadcastExtra = [],
    ): CrashGameTick {
        return config('crash.broadcast_immediately', false)
            ? new CrashGameTickImmediate($tenantSlug, $roundId, $multiplier, $phase, $broadcastExtra)
            : new CrashGameTick($tenantSlug, $roundId, $multiplier, $phase, $broadcastExtra);
    }

    /**
     * @param  array<string, mixed>  $rawBroadcastExtra
     */
    private function emitOperatorPulseIfApplicable(
        string $tenantSlug,
        string $roundExternalId,
        float $multiplier,
        string $phase,
        array $rawBroadcastExtra,
    ): void {
        $pk = $rawBroadcastExtra['crash_round_pk'] ?? null;
        if (! is_string($pk) || $pk === '') {
            return;
        }

        $round = CrashRound::query()->find($pk);
        if ($round === null) {
            return;
        }

        $tenant = Tenant::query()->whereKey($round->tenant_id)->first();
        if ($tenant === null || $tenant->slug !== $tenantSlug) {
            return;
        }

        $settings = CrashTenantSettings::query()->where('tenant_id', $round->tenant_id)->first();

        $pendingOps = ($settings !== null && $settings->pending_operator_crash_multiplier !== null)
            ? (float) $settings->pending_operator_crash_multiplier
            : null;

        /** @var object{open_count:int|string|null, stake_sum:int|string|null}|null $aggregate */
        $aggregate = CrashBet::query()
            ->where('crash_round_id', $round->getKey())
            ->where('status', 'open')
            ->selectRaw('count(*) as open_count, coalesce(sum(stake_minor), 0) as stake_sum')
            ->first();

        $openCount = $aggregate !== null ? (int) $aggregate->open_count : 0;
        $stakeSum = $aggregate !== null ? (int) $aggregate->stake_sum : 0;

        $committed = $round->crash_point_multiplier !== null
            ? (float) $round->crash_point_multiplier
            : null;

        $pulse = config('crash.broadcast_immediately', false)
            ? new CrashGameOpsPulseImmediate(
                tenantSlug: $tenantSlug,
                roundExternalId: $roundExternalId,
                crashRoundPk: (string) $round->getKey(),
                phase: $phase,
                displayMultiplier: $multiplier,
                committedCrashMultiplier: $committed,
                pendingOperatorCrashMultiplierFromSettings: $pendingOps,
                openBetCount: $openCount,
                openStakeMinorSum: $stakeSum,
                generationSource: $round->generation_source,
            )
            : new CrashGameOpsPulse(
                tenantSlug: $tenantSlug,
                roundExternalId: $roundExternalId,
                crashRoundPk: (string) $round->getKey(),
                phase: $phase,
                displayMultiplier: $multiplier,
                committedCrashMultiplier: $committed,
                pendingOperatorCrashMultiplierFromSettings: $pendingOps,
                openBetCount: $openCount,
                openStakeMinorSum: $stakeSum,
                generationSource: $round->generation_source,
            );

        event($pulse);
    }
}
