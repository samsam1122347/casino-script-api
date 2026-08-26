<?php

namespace Tests\Unit;

use App\Services\Crash\Engine\CrashPointSampler;
use PHPUnit\Framework\TestCase;

class CrashPointSamplerTest extends TestCase
{
    public function test_uniform_pf_is_deterministic(): void
    {
        $s = new CrashPointSampler;

        $a = $s->uniformFromPf('abcd', 'deadbeef', 'round-uuid-1');
        $b = $s->uniformFromPf('abcd', 'deadbeef', 'round-uuid-1');
        $this->assertSame($a, $b);
    }

    public function test_pf_multiplier_stays_inside_bounds(): void
    {
        $s = new CrashPointSampler;

        $u = $s->uniformFromPf(str_repeat('a', 32), str_repeat('b', 8), 'x');
        $m = $s->multiplierFromUniform($u, 0.05, minMult: 1.05, maxCap: 9000.0);
        $this->assertGreaterThanOrEqual(1.05, $m);
        $this->assertLessThanOrEqual(9000.0, $m);
    }
}
