<?php

namespace Tests\Feature;

use App\Models\CrashRound;
use App\Models\CrashTenantSettings;
use App\Models\Tenant;
use App\Services\Crash\Engine\CrashRoundEngine;
use Database\Seeders\TenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CrashRoundEngineRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_forces_bust_when_running_multiplier_never_advances(): void
    {
        $this->seed(TenantSeeder::class);
        config([
            'crash.engine.enabled' => true,
            'crash.engine.closed_hold_seconds' => 0,
        ]);

        $tenant = Tenant::query()->where('slug', 'crashx')->firstOrFail();

        CrashTenantSettings::query()->where('tenant_id', $tenant->getKey())->update([
            'engine_enabled' => true,
            'game_paused' => false,
            'game_enabled' => true,
            'growth_per_second' => 0.0,
            'tick_hz' => 6,
            'pending_operator_crash_multiplier' => null,
        ]);

        CrashRound::query()->create([
            'tenant_id' => $tenant->getKey(),
            'external_round_id' => Str::uuid()->toString(),
            'phase' => 'running',
            'last_multiplier' => 1,
            'tick_count' => 0,
            'started_at' => now()->subMinute(),
            'crash_point_multiplier' => 50,
            'started_running_at' => now()->subMinute(),
            'growth_per_second_snapshot' => 0,
            'generation_source' => 'algo',
        ]);

        app(CrashRoundEngine::class)->tickTenant($tenant);

        $this->assertGreaterThanOrEqual(
            1,
            CrashRound::query()->where('tenant_id', $tenant->getKey())->where('phase', 'busted')->count(),
            'running round stuck with zero growth should bust',
        );

        $this->assertGreaterThanOrEqual(
            1,
            CrashRound::query()->where('tenant_id', $tenant->getKey())->where('phase', 'betting')->count(),
            'after bust engine should allocate a betting round',
        );
    }
}
