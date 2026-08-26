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

class CrashRoundEngineWatchdogTest extends TestCase
{
    use RefreshDatabase;

    public function test_betting_round_is_created_with_committed_crash_point_and_reused_on_start(): void
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
            'pending_operator_crash_multiplier' => 4.44,
            'betting_duration_seconds' => 2,
            'growth_per_second' => 0.055,
        ]);

        app(CrashRoundEngine::class)->tickTenant($tenant);

        /** @var CrashRound $round */
        $round = CrashRound::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('phase', 'betting')
            ->firstOrFail();
        $committed = (float) $round->crash_point_multiplier;

        $this->assertSame(4.44, $committed);
        $this->assertSame('operator_override', $round->generation_source);
        $this->assertNull(
            CrashTenantSettings::query()->where('tenant_id', $tenant->getKey())->firstOrFail()->pending_operator_crash_multiplier,
        );

        $this->travelTo($round->betting_closes_at->copy()->addSecond());

        app(CrashRoundEngine::class)->tickTenant($tenant);

        $round->refresh();
        $this->assertSame('running', $round->phase);
        $this->assertSame($committed, (float) $round->crash_point_multiplier);
    }

    public function test_watchdog_force_busts_absurdly_long_running_round(): void
    {
        $this->seed(TenantSeeder::class);
        config([
            'crash.engine.enabled' => true,
            'crash.engine.running_watchdog_margin' => 2,
            'crash.engine.running_watchdog_grace_seconds' => 90,
            'crash.engine.running_watchdog_ceiling_seconds' => 500,
            'crash.engine.closed_hold_seconds' => 0,
        ]);

        $tenant = Tenant::query()->where('slug', 'crashx')->firstOrFail();
        CrashTenantSettings::query()->where('tenant_id', $tenant->getKey())->update([
            'engine_enabled' => true,
            'game_paused' => false,
            'game_enabled' => true,
            'growth_per_second' => 0.055,
            'tick_hz' => 6,
        ]);

        CrashRound::query()->create([
            'tenant_id' => $tenant->getKey(),
            'external_round_id' => Str::uuid()->toString(),
            'phase' => 'running',
            'last_multiplier' => 1,
            'tick_count' => 0,
            'started_at' => now()->subHours(6),
            'crash_point_multiplier' => 2,
            'started_running_at' => now()->subHours(6),
            'growth_per_second_snapshot' => 0.055,
            'generation_source' => 'algo',
        ]);

        app(CrashRoundEngine::class)->tickTenant($tenant);

        $this->assertGreaterThanOrEqual(
            1,
            CrashRound::query()->where('tenant_id', $tenant->getKey())->where('phase', 'betting')->count(),
        );
    }

    public function test_scheduled_tick_without_tenant_argument_advances_all_tenants(): void
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
            'growth_per_second' => 0.055,
            'tick_hz' => 6,
        ]);

        CrashRound::query()->create([
            'tenant_id' => $tenant->getKey(),
            'external_round_id' => Str::uuid()->toString(),
            'phase' => 'betting',
            'last_multiplier' => 1,
            'tick_count' => 0,
            'started_at' => now()->subMinute(),
            'betting_opens_at' => now()->subMinute(),
            'betting_closes_at' => now()->subSeconds(10),
            'generation_source' => 'algo',
        ]);

        $this->artisan('crash:tick')->assertExitCode(0);

        $this->assertSame(
            'running',
            CrashRound::query()
                ->where('tenant_id', $tenant->getKey())
                ->whereIn('phase', ['betting', 'running'])
                ->orderByDesc('created_at')
                ->value('phase'),
        );
    }

    public function test_busted_round_holds_before_next_betting_round(): void
    {
        $this->seed(TenantSeeder::class);
        config([
            'crash.engine.enabled' => true,
            'crash.engine.closed_hold_seconds' => 2,
        ]);

        $tenant = Tenant::query()->where('slug', 'crashx')->firstOrFail();
        CrashTenantSettings::query()->where('tenant_id', $tenant->getKey())->update([
            'engine_enabled' => true,
            'game_paused' => false,
            'game_enabled' => true,
            'growth_per_second' => 10,
            'tick_hz' => 6,
        ]);

        CrashRound::query()->create([
            'tenant_id' => $tenant->getKey(),
            'external_round_id' => Str::uuid()->toString(),
            'phase' => 'running',
            'last_multiplier' => 1,
            'tick_count' => 0,
            'started_at' => now()->subSeconds(5),
            'crash_point_multiplier' => 1.1,
            'started_running_at' => now()->subSeconds(5),
            'growth_per_second_snapshot' => 10,
            'generation_source' => 'algo',
        ]);

        app(CrashRoundEngine::class)->tickTenant($tenant);

        $this->assertSame(1, CrashRound::query()->where('tenant_id', $tenant->getKey())->where('phase', 'busted')->count());
        $this->assertSame(0, CrashRound::query()->where('tenant_id', $tenant->getKey())->where('phase', 'betting')->count());

        app(CrashRoundEngine::class)->tickTenant($tenant);
        $this->assertSame(0, CrashRound::query()->where('tenant_id', $tenant->getKey())->where('phase', 'betting')->count());

        $bustedAt = CrashRound::query()->where('phase', 'busted')->firstOrFail()->ended_at;
        $this->travelTo($bustedAt->copy()->addSeconds(3));

        app(CrashRoundEngine::class)->tickTenant($tenant);
        $this->assertSame(1, CrashRound::query()->where('tenant_id', $tenant->getKey())->where('phase', 'betting')->count());
    }
}
