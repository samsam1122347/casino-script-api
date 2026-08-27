<?php

namespace App\Services\Wallet;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;

final class WalletDepositClaimService
{
    public function submit(User $user, string $currency, string $network): void
    {
        DB::transaction(function () use ($user, $currency, $network): void {
            /** @var Wallet $wallet */
            $wallet = Wallet::query()
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->firstOrFail();

            WalletTransaction::query()->create([
                'wallet_id' => $wallet->id,
                'type' => 'deposit',
                'amount_minor' => 0,
                'balance_after_minor' => $wallet->balance_minor,
                'meta' => [
                    'label' => 'Deposit Claim',
                    'status' => 'pending',
                    'currency' => $currency,
                    'network' => $network,
                ],
            ]);
        });
    }
}
