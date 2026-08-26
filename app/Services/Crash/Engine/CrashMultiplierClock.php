<?php

namespace App\Services\Crash\Engine;

use App\Models\CrashRound;
use Carbon\CarbonInterface;

final class CrashMultiplierClock
{
    /**
     * Server-authoritative display multiplier at $at (exponential growth, capped at crash point).
     */
    public static function displayAt(CrashRound $round, CarbonInterface $at): float
    {
        if ($round->phase !== 'running'
            || $round->started_running_at === null
            || $round->crash_point_multiplier === null) {
            return round((float) $round->last_multiplier, 4);
        }

        $crash = (float) $round->crash_point_multiplier;
        $growth = (float) ($round->growth_per_second_snapshot ?? config('crash.defaults.growth_per_second', 0.055));

        // Signed diff: elapsed only advances when `$at` is after `started_running_at`.
        // Default `absolute=true` masks small clock skew/future timestamps and freezes the multiplier.
        $elapsed = max(0.0, $round->started_running_at->diffInMicroseconds($at, false) / 1_000_000);
        $uncapped = exp($growth * $elapsed);

        return round(min($crash, $uncapped), 4);
    }

    public static function hasReachedCrashPoint(CrashRound $round, CarbonInterface $at, float $epsilon = 1e-4): bool
    {
        if ($round->crash_point_multiplier === null || $round->phase !== 'running') {
            return false;
        }

        return self::displayAt($round, $at) + $epsilon >= (float) $round->crash_point_multiplier;
    }
}
