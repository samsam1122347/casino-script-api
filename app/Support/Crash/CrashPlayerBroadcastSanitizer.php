<?php

namespace App\Support\Crash;

use App\Events\CrashGameOpsPulse;

/**
 * Filters fields players must not see mid-round. Operators receive the full feed on
 * {@see CrashGameOpsPulse} (private admin channel).
 */
final class CrashPlayerBroadcastSanitizer
{
    /**
     * @param  array<string, mixed>  $extras
     * @return array<string, mixed>
     */
    public static function sanitizeForPhase(array $extras, string $phase): array
    {
        $p = strtolower(trim($phase));
        $terminal = in_array($p, ['busted', 'cancelled'], true);

        $out = $extras;

        unset($out['pending_operator_crash_multiplier']);

        if (! $terminal) {
            unset($out['crash_point_multiplier'], $out['revealed_server_seed']);
        }

        if ($p === 'running') {
            unset($out['pf_nonce']);
        }

        return $out;
    }
}
