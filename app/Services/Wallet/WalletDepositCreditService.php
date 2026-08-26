<?php

namespace App\Services\Wallet;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Credits a verified inbound deposit and applies the one-time welcome credit on the player's first deposit.
 * Call this from treasury / payment webhooks when a deposit is confirmed on-chain or via PSP.
 */
final class WalletDepositCreditService
{
    public function creditVerifiedDeposit(User $user, int $amountMinor, array $meta = []): void
    {
        if ($amountMinor < 1) {
            throw new InvalidArgumentException('Deposit amount must be positive.');
        }

        $bonusMinor = max(0, (int) config('gaming.first_deposit_bonus_minor', 0));

        DB::transaction(function () use ($user, $amountMinor, $meta, $bonusMinor): void {
            /** @var Wallet $wallet */
            $wallet = Wallet::query()
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->firstOrFail();

            $hadPriorDeposit = WalletTransaction::query()
                ->where('wallet_id', $wallet->id)
                ->where('type', 'deposit')
                ->where('amount_minor', '>', 0)
                ->exists();

            $balance = (int) $wallet->balance_minor;
            $balance += $amountMinor;

            WalletTransaction::query()->create([
                'wallet_id' => $wallet->id,
                'type' => 'deposit',
                'amount_minor' => $amountMinor,
                'balance_after_minor' => $balance,
                'meta' => array_merge(['label' => 'Deposit'], $meta),
            ]);

            if (! $hadPriorDeposit && $bonusMinor > 0) {
                $balance += $bonusMinor;
                WalletTransaction::query()->create([
                    'wallet_id' => $wallet->id,
                    'type' => 'first_deposit_bonus',
                    'amount_minor' => $bonusMinor,
                    'balance_after_minor' => $balance,
                    'meta' => ['label' => 'Welcome bonus'],
                ]);
            }

            $wallet->balance_minor = $balance;
            $wallet->save();
        });
    }
}
