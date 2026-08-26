<?php

namespace Tests\Feature;

use App\Models\CrashBet;
use App\Models\CrashRound;
use App\Models\CrashTenantSettings;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Crash\CrashCashoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CrashAutoCashoutTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Regression: the 1e-4 epsilon in tryAutoCashouts must not pay out a bet whose
     * auto-cashout target sits just above the round's crash point — that bet loses.
     */
    public function test_auto_cashout_does_not_pay_target_above_crash_point(): void
    {
        $tenant = Tenant::query()->create([
            'slug' => 'auto-cashout-tenant',
            'display_name' => 'AC',
            'crypto_assets' => [],
        ]);

        CrashTenantSettings::query()->create(
            CrashTenantSettings::defaultsForTenant((string) $tenant->getKey()),
        );

        $atCrashUser = User::factory()->create(['tenant_id' => $tenant->id]);
        $aboveCrashUser = User::factory()->create(['tenant_id' => $tenant->id]);

        foreach ([$atCrashUser, $aboveCrashUser] as $u) {
            Wallet::query()->create([
                'user_id' => $u->id,
                'currency' => 'USD',
                'balance_minor' => 0,
            ]);
        }

        // Running round whose display multiplier has already reached the 2.00x crash point.
        $round = CrashRound::query()->create([
            'tenant_id' => $tenant->getKey(),
            'external_round_id' => Str::uuid()->toString(),
            'phase' => 'running',
            'last_multiplier' => 1,
            'tick_count' => 0,
            'started_at' => now()->subMinutes(5),
            'crash_point_multiplier' => 2.0,
            'started_running_at' => now()->subMinutes(5),
            'growth_per_second_snapshot' => 0.055,
            'generation_source' => 'algo',
        ]);

        $atCrashBet = CrashBet::query()->create([
            'crash_round_id' => $round->id,
            'user_id' => $atCrashUser->id,
            'stake_minor' => 1000,
            'auto_cashout_multiplier' => 2.0,
            'status' => 'open',
        ]);

        $aboveCrashBet = CrashBet::query()->create([
            'crash_round_id' => $round->id,
            'user_id' => $aboveCrashUser->id,
            'stake_minor' => 1000,
            'auto_cashout_multiplier' => 2.0005,
            'status' => 'open',
        ]);

        app(CrashCashoutService::class)->tryAutoCashouts($round);

        $atCrashBet->refresh();
        $aboveCrashBet->refresh();

        $this->assertSame('cashed_out', $atCrashBet->status, 'target at the crash point should pay');
        $this->assertSame(2000, (int) $atCrashBet->payout_minor, 'payout = stake * 2.00x');

        $this->assertSame('open', $aboveCrashBet->status, 'target above the crash point must not pay');
        $this->assertNull($aboveCrashBet->payout_minor);

        $this->assertSame(
            2000,
            (int) Wallet::query()->where('user_id', $atCrashUser->id)->value('balance_minor'),
        );
        $this->assertSame(
            0,
            (int) Wallet::query()->where('user_id', $aboveCrashUser->id)->value('balance_minor'),
            'a bet that never reached its target must not credit the wallet',
        );
    }
}
