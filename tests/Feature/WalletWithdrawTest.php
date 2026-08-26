<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\Wallet\WalletWithdrawalApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WalletWithdrawTest extends TestCase
{
    use RefreshDatabase;

    private static function cryptoAssetPreset(): array
    {
        return [
            [
                'id' => 'usdt',
                'symbol' => 'USDT',
                'name' => 'Tether USD',
                'network' => 'TRC20',
                'address' => 'TJxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxTest',
                'min_withdraw_usd' => 10,
            ],
        ];
    }

    public function test_withdraw_updates_balance_and_creates_transaction(): void
    {
        $tenant = Tenant::query()->create([
            'slug' => 'test-tenant-a',
            'display_name' => 'Test Tenant A',
            'crypto_assets' => self::cryptoAssetPreset(),
        ]);

        /** @var User $user */
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        Wallet::query()->create([
            'user_id' => $user->id,
            'currency' => 'USD',
            'balance_minor' => 50_000,
        ]);

        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson('/api/v1/wallet/withdraw', [
            'crypto_asset_id' => 'usdt',
            'destination_address' => 'TQzKw3p2nLr8Vk1uXyZb4Aa7Q5J9R6tNm3',
            'amount_minor' => 1500,
        ], [
            'X-Tenant-Slug' => $tenant->slug,
        ]);

        $response->assertOk();
        $response->assertJsonPath('withdrawal.amount_minor', 1500);
        $response->assertJsonPath('withdrawal.balance_after_minor', 48_500);

        $wallet = Wallet::query()->where('user_id', $user->id)->firstOrFail();
        $wallet->refresh();
        $this->assertSame(48_500, (int) $wallet->balance_minor);

        /** @var WalletTransaction|null $tx */
        $tx = WalletTransaction::query()->first();
        $this->assertNotNull($tx);
        $this->assertSame('withdrawal', $tx->type);
        $this->assertSame(-1500, (int) $tx->amount_minor);
        $this->assertSame(48_500, (int) $tx->balance_after_minor);
        $this->assertSame('pending', data_get($tx->meta, 'status'));
        $this->assertSame('usdt', data_get($tx->meta, 'asset_id'));

        $this->getJson('/api/v1/wallet/transactions', [
            'X-Tenant-Slug' => $tenant->slug,
        ])
            ->assertOk()
            ->assertJsonPath('data.0.type', 'withdrawal')
            ->assertJsonPath('data.0.meta.status', 'pending');
    }

    public function test_admin_can_approve_pending_withdrawal_without_changing_reserved_balance(): void
    {
        $tenant = Tenant::query()->create([
            'slug' => 'test-approve',
            'display_name' => 'Approve Tenant',
            'crypto_assets' => self::cryptoAssetPreset(),
        ]);

        /** @var User $user */
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        Wallet::query()->create([
            'user_id' => $user->id,
            'currency' => 'USD',
            'balance_minor' => 50_000,
        ]);

        Sanctum::actingAs($user, ['*']);

        $this->postJson('/api/v1/wallet/withdraw', [
            'crypto_asset_id' => 'usdt',
            'destination_address' => 'TQzKw3p2nLr8Vk1uXyZb4Aa7Q5J9R6tNm3',
            'amount_minor' => 1500,
        ], ['X-Tenant-Slug' => $tenant->slug])->assertOk();

        /** @var WalletTransaction $tx */
        $tx = WalletTransaction::query()->where('type', 'withdrawal')->firstOrFail();

        app(WalletWithdrawalApprovalService::class)->approve($tx, 'admin-1');

        $tx->refresh();
        $wallet = Wallet::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertSame('approved', data_get($tx->meta, 'status'));
        $this->assertSame('admin-1', data_get($tx->meta, 'approved_by_admin_id'));
        $this->assertSame(48_500, (int) $wallet->balance_minor);
        $this->assertSame(1, WalletTransaction::query()->count());
    }

    public function test_admin_can_reject_pending_withdrawal_and_refund_reserved_balance(): void
    {
        $tenant = Tenant::query()->create([
            'slug' => 'test-reject',
            'display_name' => 'Reject Tenant',
            'crypto_assets' => self::cryptoAssetPreset(),
        ]);

        /** @var User $user */
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        Wallet::query()->create([
            'user_id' => $user->id,
            'currency' => 'USD',
            'balance_minor' => 50_000,
        ]);

        Sanctum::actingAs($user, ['*']);

        $this->postJson('/api/v1/wallet/withdraw', [
            'crypto_asset_id' => 'usdt',
            'destination_address' => 'TQzKw3p2nLr8Vk1uXyZb4Aa7Q5J9R6tNm3',
            'amount_minor' => 1500,
        ], ['X-Tenant-Slug' => $tenant->slug])->assertOk();

        /** @var WalletTransaction $tx */
        $tx = WalletTransaction::query()->where('type', 'withdrawal')->firstOrFail();

        app(WalletWithdrawalApprovalService::class)->reject($tx, 'admin-2', 'Bad address');

        $tx->refresh();
        $wallet = Wallet::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertSame('rejected', data_get($tx->meta, 'status'));
        $this->assertSame('admin-2', data_get($tx->meta, 'rejected_by_admin_id'));
        $this->assertSame('Bad address', data_get($tx->meta, 'rejection_reason'));
        $this->assertSame(50_000, (int) $wallet->balance_minor);
        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_id' => $wallet->id,
            'type' => 'withdrawal_refund',
            'amount_minor' => 1500,
            'balance_after_minor' => 50_000,
        ]);
    }

    public function test_unknown_crypto_asset_returns_422(): void
    {
        $tenant = Tenant::query()->create([
            'slug' => 'test-tenant-no-asset',
            'display_name' => 'No Asset',
            'crypto_assets' => [],
        ]);
        /** @var User $user */
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        Wallet::query()->create([
            'user_id' => $user->id,
            'currency' => 'USD',
            'balance_minor' => 10_000,
        ]);

        Sanctum::actingAs($user, ['*']);

        $this->postJson('/api/v1/wallet/withdraw', [
            'crypto_asset_id' => 'btc',
            'destination_address' => '1BvBMSEYstWetqTFn5Au4m4GFg7xJaNVN2',
            'amount_minor' => 1000,
        ], ['X-Tenant-Slug' => $tenant->slug])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Unknown crypto asset for this tenant.');
    }

    public function test_insufficient_balance_returns_422(): void
    {
        $tenant = Tenant::query()->create([
            'slug' => 'test-tenant-bal',
            'display_name' => 'Bal Tenant',
            'crypto_assets' => self::cryptoAssetPreset(),
        ]);
        /** @var User $user */
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        Wallet::query()->create([
            'user_id' => $user->id,
            'currency' => 'USD',
            'balance_minor' => 500,
        ]);

        Sanctum::actingAs($user, ['*']);

        $this->postJson('/api/v1/wallet/withdraw', [
            'crypto_asset_id' => 'usdt',
            'destination_address' => 'TQzKw3p2nLr8Vk1uXyZb4Aa7Q5J9R6tNm3',
            'amount_minor' => 1000,
        ], ['X-Tenant-Slug' => $tenant->slug])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Insufficient balance.');
    }

    public function test_below_minimum_withdraw_returns_422(): void
    {
        $tenant = Tenant::query()->create([
            'slug' => 'test-min',
            'display_name' => 'Min Tenant',
            'crypto_assets' => self::cryptoAssetPreset(),
        ]);
        /** @var User $user */
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        Wallet::query()->create([
            'user_id' => $user->id,
            'currency' => 'USD',
            'balance_minor' => 50_000,
        ]);

        Sanctum::actingAs($user, ['*']);

        $this->postJson('/api/v1/wallet/withdraw', [
            'crypto_asset_id' => 'usdt',
            'destination_address' => 'TQzKw3p2nLr8Vk1uXyZb4Aa7Q5J9R6tNm3',
            'amount_minor' => 999,
        ], ['X-Tenant-Slug' => $tenant->slug])
            ->assertStatus(422)
            ->assertJsonPath('min_amount_minor', 1000);
    }

    public function test_wallet_not_found_returns_404(): void
    {
        $tenant = Tenant::query()->create([
            'slug' => 'test-no-wallet',
            'display_name' => 'No Wallet',
            'crypto_assets' => self::cryptoAssetPreset(),
        ]);
        /** @var User $user */
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        Sanctum::actingAs($user, ['*']);

        $this->postJson('/api/v1/wallet/withdraw', [
            'crypto_asset_id' => 'usdt',
            'destination_address' => 'TQzKw3p2nLr8Vk1uXyZb4Aa7Q5J9R6tNm3',
            'amount_minor' => 1000,
        ], ['X-Tenant-Slug' => $tenant->slug])
            ->assertStatus(404)
            ->assertJsonPath('message', 'Wallet not found.');
    }

    public function test_tenant_mismatch_returns_403(): void
    {
        $tenantA = Tenant::query()->create([
            'slug' => 'tenant-a',
            'display_name' => 'A',
            'crypto_assets' => self::cryptoAssetPreset(),
        ]);
        $tenantB = Tenant::query()->create([
            'slug' => 'tenant-b',
            'display_name' => 'B',
            'crypto_assets' => self::cryptoAssetPreset(),
        ]);

        /** @var User $user */
        $user = User::factory()->create(['tenant_id' => $tenantA->id]);
        Wallet::query()->create([
            'user_id' => $user->id,
            'currency' => 'USD',
            'balance_minor' => 50_000,
        ]);

        Sanctum::actingAs($user, ['*']);

        $this->postJson('/api/v1/wallet/withdraw', [
            'crypto_asset_id' => 'usdt',
            'destination_address' => 'TQzKw3p2nLr8Vk1uXyZb4Aa7Q5J9R6tNm3',
            'amount_minor' => 1000,
        ], ['X-Tenant-Slug' => $tenantB->slug])
            ->assertStatus(403)
            ->assertJsonPath('message', 'Tenant mismatch.');
    }

    public function test_idempotency_key_replays_cached_response(): void
    {
        $tenant = Tenant::query()->create([
            'slug' => 'test-idem',
            'display_name' => 'Idem',
            'crypto_assets' => self::cryptoAssetPreset(),
        ]);
        /** @var User $user */
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        Wallet::query()->create([
            'user_id' => $user->id,
            'currency' => 'USD',
            'balance_minor' => 50_000,
        ]);

        Sanctum::actingAs($user, ['*']);

        $headers = [
            'X-Tenant-Slug' => $tenant->slug,
            'Idempotency-Key' => 'withdraw-test-1',
        ];
        $body = [
            'crypto_asset_id' => 'usdt',
            'destination_address' => 'TQzKw3p2nLr8Vk1uXyZb4Aa7Q5J9R6tNm3',
            'amount_minor' => 2000,
        ];

        $first = $this->postJson('/api/v1/wallet/withdraw', $body, $headers);
        $first->assertOk();
        $second = $this->postJson('/api/v1/wallet/withdraw', $body, $headers);
        $second->assertOk();
        $this->assertSame($first->json(), $second->json());

        $wallet = Wallet::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame(48_000, (int) $wallet->balance_minor);
        $this->assertSame(1, WalletTransaction::query()->count());
    }

    public function test_idempotency_db_guard_prevents_double_debit_on_cache_miss(): void
    {
        $tenant = Tenant::query()->create([
            'slug' => 'test-idem-db',
            'display_name' => 'Idem DB',
            'crypto_assets' => self::cryptoAssetPreset(),
        ]);
        /** @var User $user */
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        Wallet::query()->create([
            'user_id' => $user->id,
            'currency' => 'USD',
            'balance_minor' => 50_000,
        ]);

        Sanctum::actingAs($user, ['*']);

        $headers = [
            'X-Tenant-Slug' => $tenant->slug,
            'Idempotency-Key' => 'withdraw-db-guard-1',
        ];
        $body = [
            'crypto_asset_id' => 'usdt',
            'destination_address' => 'TQzKw3p2nLr8Vk1uXyZb4Aa7Q5J9R6tNm3',
            'amount_minor' => 2000,
        ];

        $first = $this->postJson('/api/v1/wallet/withdraw', $body, $headers);
        $first->assertOk();

        // Simulate the Cache fast-path missing (eviction / separate worker):
        // the in-transaction DB-unique guard must still prevent a second debit.
        Cache::flush();

        $second = $this->postJson('/api/v1/wallet/withdraw', $body, $headers);
        $second->assertOk();

        $wallet = Wallet::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame(48_000, (int) $wallet->balance_minor, 'wallet must be debited exactly once');
        $this->assertSame(1, WalletTransaction::query()->count(), 'only one withdrawal row may exist');
        $this->assertSame(
            $first->json('withdrawal.id'),
            $second->json('withdrawal.id'),
            'replay must return the original withdrawal',
        );
    }
}
