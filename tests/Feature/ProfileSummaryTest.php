<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_aggregates_wallet_transaction_totals(): void
    {
        $tenant = Tenant::query()->create([
            'slug' => 'psum-1',
            'display_name' => 'P',
            'crypto_assets' => [],
        ]);
        /** @var User $user */
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $wallet = Wallet::query()->create([
            'user_id' => $user->id,
            'currency' => 'USD',
            'balance_minor' => 10_000,
        ]);

        WalletTransaction::query()->create([
            'wallet_id' => $wallet->id,
            'type' => 'withdrawal',
            'amount_minor' => -2_000,
            'balance_after_minor' => 8_000,
            'meta' => [],
        ]);
        WalletTransaction::query()->create([
            'wallet_id' => $wallet->id,
            'type' => 'signup_bonus',
            'amount_minor' => 5_000,
            'balance_after_minor' => 5_000,
            'meta' => [],
        ]);
        WalletTransaction::query()->create([
            'wallet_id' => $wallet->id,
            'type' => 'deposit',
            'amount_minor' => 1_000,
            'balance_after_minor' => 6_000,
            'meta' => [],
        ]);

        Sanctum::actingAs($user, ['*']);

        $response = $this->getJson('/api/v1/profile/summary', [
            'X-Tenant-Slug' => $tenant->slug,
        ]);

        $response->assertOk();
        $response->assertJsonPath('totals.deposits_minor', 1000);
        $response->assertJsonPath('totals.withdrawals_minor', 2000);
        $response->assertJsonPath('totals.bonuses_minor', 5000);

        // 1000 cents deposited → XP = 10 → tier bronze_1, progress toward 600 XP ceiling
        $response->assertJsonPath('vip.tier_id', 'bronze_1');
        $response->assertJsonPath('vip.xp_total', 10);
        $response->assertJsonPath('vip.xp_to_next', 590);
        $response->assertJsonPath('vip.at_max', false);
    }

    public function test_summary_vip_max_tier_when_deposits_exceed_final_threshold(): void
    {
        $tenant = Tenant::query()->create([
            'slug' => 'psum-vip-max',
            'display_name' => 'VIP Max',
            'crypto_assets' => [],
        ]);
        /** @var User $user */
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $wallet = Wallet::query()->create([
            'user_id' => $user->id,
            'currency' => 'USD',
            'balance_minor' => 1_000_000,
        ]);
        WalletTransaction::query()->create([
            'wallet_id' => $wallet->id,
            'type' => 'deposit',
            'amount_minor' => 1_500_000,
            'balance_after_minor' => 1_500_000,
            'meta' => [],
        ]);

        Sanctum::actingAs($user, ['*']);

        $response = $this->getJson('/api/v1/profile/summary', [
            'X-Tenant-Slug' => $tenant->slug,
        ]);

        $response->assertOk();
        $response->assertJsonPath('vip.tier_id', 'gold_1');
        $response->assertJsonPath('vip.progress_percent', 100);
        $response->assertJsonPath('vip.at_max', true);
    }

    public function test_summary_includes_default_vip_without_wallet(): void
    {
        $tenant = Tenant::query()->create([
            'slug' => 'psum-no-wallet',
            'display_name' => 'NW',
            'crypto_assets' => [],
        ]);
        /** @var User $user */
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        Sanctum::actingAs($user, ['*']);

        $this->getJson('/api/v1/profile/summary', [
            'X-Tenant-Slug' => $tenant->slug,
        ])
            ->assertOk()
            ->assertJsonPath('vip.xp_total', 0)
            ->assertJsonPath('vip.tier_id', 'bronze_1');
    }
}
