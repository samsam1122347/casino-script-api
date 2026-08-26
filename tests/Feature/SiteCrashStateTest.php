<?php

namespace Tests\Feature;

use App\Models\CrashRound;
use App\Models\Tenant;
use Database\Seeders\TenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SiteCrashStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_site_crash_state_is_public_and_returns_recent_busted_multipliers(): void
    {
        $this->seed(TenantSeeder::class);
        $tenant = Tenant::query()->where('slug', 'crashx')->firstOrFail();

        CrashRound::query()->create([
            'tenant_id' => $tenant->id,
            'external_round_id' => (string) Str::uuid(),
            'phase' => 'busted',
            'last_multiplier' => 3.5,
            'tick_count' => 5,
            'started_at' => now()->subMinute(),
            'ended_at' => now(),
            'crash_point_multiplier' => 4.12,
            'hash_commitment' => null,
            'revealed_server_seed' => null,
            'pf_server_seed' => null,
            'pf_nonce' => null,
            'generation_source' => 'algo',
            'betting_opens_at' => now()->subMinutes(2),
            'betting_closes_at' => now()->subMinutes(2)->addSeconds(10),
            'started_running_at' => now()->subMinute(),
            'max_multiplier_cap_snapshot' => 1000,
            'growth_per_second_snapshot' => 0.05,
            'last_tick_broadcast_at' => null,
        ]);

        $res = $this->getJson('/api/v1/site/crash-state?tenant=crashx');

        $res->assertOk();
        $res->assertJsonPath('recent_busted_multipliers.0', 4.12);
        $res->assertJsonPath('round.phase', 'busted');
        $res->assertJsonPath('round.crash_point_multiplier', 4.12);
    }
}
