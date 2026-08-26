<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\Wallet\WalletDepositCreditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletSignupAndDepositTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_zero_balance_wallet_without_ledger_entries(): void
    {
        config(['gaming.first_deposit_bonus_minor' => 5000]);
        $tenant = Tenant::query()->create([
            'slug' => 'reg-wallet',
            'display_name' => 'Reg Wallet',
            'crypto_assets' => [],
        ]);

        $response = $this->postJson('/api/v1/auth/register', [
            'username' => 'newplayer',
            'password' => 'password1',
            'password_confirmation' => 'password1',
            'recovery_question_keys' => ['birth_city', 'mother_maiden', 'first_pet'],
            'recovery_answers' => ['Paris', 'Smith', 'Fluffy'],
        ], ['X-Tenant-Slug' => $tenant->slug]);

        $response->assertCreated();
        /** @var User|null $user */
        $user = User::query()->where('tenant_id', $tenant->id)->where('username', 'newplayer')->first();
        $this->assertNotNull($user);
        $wallet = Wallet::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($wallet);
        $this->assertSame(0, (int) $wallet->balance_minor);
        $this->assertSame(0, WalletTransaction::query()->where('wallet_id', $wallet->id)->count());
    }

    public function test_first_verified_deposit_applies_bonus_once(): void
    {
        config(['gaming.first_deposit_bonus_minor' => 5000]);
        $tenant = Tenant::query()->create([
            'slug' => 'dep-bonus',
            'display_name' => 'Deposit Bonus',
            'crypto_assets' => [],
        ]);
        /** @var User $user */
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        Wallet::query()->create([
            'user_id' => $user->id,
            'currency' => 'USD',
            'balance_minor' => 0,
        ]);

        /** @var WalletDepositCreditService $svc */
        $svc = app(WalletDepositCreditService::class);
        $svc->creditVerifiedDeposit($user, 1000, ['demo' => true]);

        $wallet = Wallet::query()->where('user_id', $user->id)->firstOrFail();
        $wallet->refresh();
        $this->assertSame(6000, (int) $wallet->balance_minor);

        $this->assertSame(2, WalletTransaction::query()->where('wallet_id', $wallet->id)->count());
        $this->assertTrue(WalletTransaction::query()->where('wallet_id', $wallet->id)->where('type', 'deposit')->exists());
        $this->assertTrue(WalletTransaction::query()->where('wallet_id', $wallet->id)->where('type', 'first_deposit_bonus')->exists());
    }

    public function test_second_verified_deposit_does_not_repeat_bonus(): void
    {
        config(['gaming.first_deposit_bonus_minor' => 5000]);
        $tenant = Tenant::query()->create([
            'slug' => 'dep-2',
            'display_name' => 'Deposit 2',
            'crypto_assets' => [],
        ]);
        /** @var User $user */
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        Wallet::query()->create([
            'user_id' => $user->id,
            'currency' => 'USD',
            'balance_minor' => 0,
        ]);
        $svc = app(WalletDepositCreditService::class);
        $svc->creditVerifiedDeposit($user, 1000, []);
        $svc->creditVerifiedDeposit($user, 500, []);

        $wallet = Wallet::query()->where('user_id', $user->id)->firstOrFail();
        $wallet->refresh();
        $this->assertSame(6500, (int) $wallet->balance_minor);
        $this->assertSame(3, WalletTransaction::query()->where('wallet_id', $wallet->id)->count());
    }
}
