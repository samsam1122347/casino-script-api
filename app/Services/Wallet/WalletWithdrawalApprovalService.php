<?php

namespace App\Services\Wallet;

use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class WalletWithdrawalApprovalService
{
    public function approve(WalletTransaction $transaction, ?string $adminId = null): WalletTransaction
    {
        return DB::transaction(function () use ($transaction, $adminId): WalletTransaction {
            /** @var WalletTransaction $locked */
            $locked = WalletTransaction::query()
                ->whereKey($transaction->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertPendingWithdrawal($locked);

            $meta = $locked->meta ?? [];
            $meta['status'] = 'approved';
            $meta['approved_at'] = now()->toIso8601String();
            $meta['approved_by_admin_id'] = $adminId;

            $locked->meta = $meta;
            $locked->save();

            return $locked->refresh();
        });
    }

    public function reject(WalletTransaction $transaction, ?string $adminId = null, ?string $reason = null): WalletTransaction
    {
        return DB::transaction(function () use ($transaction, $adminId, $reason): WalletTransaction {
            /** @var WalletTransaction $locked */
            $locked = WalletTransaction::query()
                ->whereKey($transaction->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertPendingWithdrawal($locked);

            /** @var Wallet $wallet */
            $wallet = Wallet::query()
                ->whereKey($locked->wallet_id)
                ->lockForUpdate()
                ->firstOrFail();

            $refundMinor = abs((int) $locked->amount_minor);
            $wallet->balance_minor = (int) $wallet->balance_minor + $refundMinor;
            $wallet->save();

            $meta = $locked->meta ?? [];
            $meta['status'] = 'rejected';
            $meta['rejected_at'] = now()->toIso8601String();
            $meta['rejected_by_admin_id'] = $adminId;
            $meta['rejection_reason'] = $reason !== null && trim($reason) !== ''
                ? trim($reason)
                : null;

            $locked->meta = $meta;
            $locked->save();

            WalletTransaction::query()->create([
                'wallet_id' => $wallet->id,
                'type' => 'withdrawal_refund',
                'amount_minor' => $refundMinor,
                'balance_after_minor' => $wallet->balance_minor,
                'meta' => [
                    'status' => 'processed',
                    'withdrawal_transaction_id' => (string) $locked->getKey(),
                    'admin_id' => $adminId,
                    'reason' => $meta['rejection_reason'],
                ],
            ]);

            return $locked->refresh();
        });
    }

    private function assertPendingWithdrawal(WalletTransaction $transaction): void
    {
        if ($transaction->type !== 'withdrawal') {
            throw new HttpException(422, 'Only withdrawal requests can be reviewed.');
        }

        if (($transaction->meta['status'] ?? null) !== 'pending') {
            throw new HttpException(422, 'Only pending withdrawal requests can be reviewed.');
        }

        if ((int) $transaction->amount_minor >= 0) {
            throw new HttpException(422, 'Withdrawal request amount is invalid.');
        }
    }
}
