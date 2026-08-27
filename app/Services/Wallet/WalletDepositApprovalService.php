<?php

namespace App\Services\Wallet;

use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class WalletDepositApprovalService
{
    public function __construct(
        private WalletDepositCreditService $creditService
    ) {}

    public function approve(WalletTransaction $record, int $amountMinor, ?string $adminId = null): void
    {
        if ($record->type !== 'deposit' || ($record->meta['status'] ?? null) !== 'pending') {
            throw new HttpException(400, 'Transaction is not a pending deposit claim.');
        }

        DB::transaction(function () use ($record, $amountMinor, $adminId): void {
            // Mark the claim as processed
            $meta = $record->meta;
            $meta['status'] = 'processed';
            $meta['approved_by'] = $adminId;
            $record->meta = $meta;
            $record->save();

            // Actually credit the funds
            $this->creditService->creditVerifiedDeposit(
                $record->wallet->user,
                $amountMinor,
                [
                    'claim_id' => $record->id,
                    'approved_by' => $adminId,
                ]
            );
        });
    }

    public function reject(WalletTransaction $record, ?string $adminId = null, ?string $reason = null): void
    {
        if ($record->type !== 'deposit' || ($record->meta['status'] ?? null) !== 'pending') {
            throw new HttpException(400, 'Transaction is not a pending deposit claim.');
        }

        $meta = $record->meta;
        $meta['status'] = 'rejected';
        $meta['rejected_by'] = $adminId;
        if ($reason) {
            $meta['reject_reason'] = $reason;
        }

        $record->meta = $meta;
        $record->save();
    }
}
