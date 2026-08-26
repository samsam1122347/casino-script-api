<?php

namespace App\Services\Crash\CrashRecording;

use App\Models\CrashRound;
use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class CrashRoundRecordingService
{
    public static function cacheKeyExternalRound(string $tenantSlug): string
    {
        return 'crash_demo_round_external_id:'.$tenantSlug;
    }

    public function forgetStickyExternalRound(string $tenantSlug): void
    {
        Cache::forget(self::cacheKeyExternalRound($tenantSlug));
    }

    /**
     * Sticky external round id for demo / CLI ticks until {@see forgetStickyExternalRound}.
     */
    public function stickyExternalRoundId(string $tenantSlug): string
    {
        $ttl = (int) config('crash.demo_round_ttl_seconds', 86400);

        /** @var string */
        return Cache::remember(
            self::cacheKeyExternalRound($tenantSlug),
            max(60, $ttl),
            fn (): string => (string) Str::uuid(),
        );
    }

    public function recordTick(
        string $tenantSlug,
        string $externalRoundId,
        float $multiplier,
        string $phase,
    ): void {
        $tenant = Tenant::query()->where('slug', $tenantSlug)->first();
        if ($tenant === null) {
            return;
        }

        $finalPhases = ['busted', 'ended', 'complete', 'cancelled'];
        $endedAt = in_array(mb_strtolower($phase), $finalPhases, true) ? now() : null;

        /** @var CrashRound $round */
        $round = CrashRound::query()->firstOrNew([
            'tenant_id' => $tenant->getKey(),
            'external_round_id' => $externalRoundId,
        ]);

        $wasFresh = ! $round->exists;

        if ($wasFresh) {
            $round->tick_count = 1;
            $round->started_at = now();
            $round->ended_at = null;
        } else {
            $round->tick_count = ((int) $round->tick_count) + 1;
        }

        $round->phase = $phase;
        $round->last_multiplier = $multiplier;

        if ($endedAt !== null) {
            $round->ended_at = $endedAt;
        }

        $round->save();
    }
}
