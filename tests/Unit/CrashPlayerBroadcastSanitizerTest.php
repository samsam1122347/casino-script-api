<?php

namespace Tests\Unit;

use App\Support\Crash\CrashPlayerBroadcastSanitizer;
use PHPUnit\Framework\TestCase;

class CrashPlayerBroadcastSanitizerTest extends TestCase
{
    public function test_running_phase_strips_true_crash_and_nonce(): void
    {
        $in = [
            'crash_round_pk' => 'r1',
            'crash_point_multiplier' => 6.72,
            'pf_nonce' => 'secretnonce',
            'hash_commitment' => 'deadbeef',
            'revealed_server_seed' => null,
            'generation_source' => 'algo',
        ];

        $out = CrashPlayerBroadcastSanitizer::sanitizeForPhase($in, 'running');

        $this->assertArrayNotHasKey('crash_point_multiplier', $out);
        $this->assertArrayNotHasKey('pf_nonce', $out);
        $this->assertSame('algo', $out['generation_source'] ?? null);
    }

    public function test_busted_phase_keeps_reveal_and_crash_multiplier(): void
    {
        $in = [
            'crash_point_multiplier' => 2.5,
            'revealed_server_seed' => 'abc123',
            'pf_nonce' => 'n1',
        ];

        $out = CrashPlayerBroadcastSanitizer::sanitizeForPhase($in, 'busted');

        $this->assertSame(2.5, $out['crash_point_multiplier'] ?? null);
        $this->assertSame('abc123', $out['revealed_server_seed'] ?? null);
    }
}
