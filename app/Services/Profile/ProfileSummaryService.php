<?php

namespace App\Services\Profile;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Http\JsonResponse;

final class ProfileSummaryService
{
    public function __construct(
        private VipProgressCalculator $vipProgress,
    ) {}

    public function build(User $user): JsonResponse
    {
        $emptyTotals = fn (): array => [
            'deposits_minor' => 0,
            'withdrawals_minor' => 0,
            'bonuses_minor' => 0,
        ];

        /** @var Wallet|null $wallet */
        $wallet = $user->wallet;

        if (! $wallet instanceof Wallet) {
            return response()->json([
                'totals' => $emptyTotals(),
                'vip' => $this->vipProgress->compute(0),
            ]);
        }

        $deposits = (int) WalletTransaction::query()
            ->where('wallet_id', $wallet->id)
            ->where('type', 'deposit')
            ->where('amount_minor', '>', 0)
            ->sum('amount_minor');

        $withdrawals = (int) (WalletTransaction::query()
            ->where('wallet_id', $wallet->id)
            ->where('type', 'withdrawal')
            ->where('amount_minor', '<', 0)
            ->selectRaw('COALESCE(SUM(ABS(amount_minor)), 0) AS t')
            ->value('t') ?? 0);

        $bonuses = (int) WalletTransaction::query()
            ->where('wallet_id', $wallet->id)
            ->whereIn('type', ['signup_bonus', 'first_deposit_bonus'])
            ->where('amount_minor', '>', 0)
            ->sum('amount_minor');

        return response()->json([
            'totals' => [
                'deposits_minor' => $deposits,
                'withdrawals_minor' => $withdrawals,
                'bonuses_minor' => $bonuses,
            ],
            'vip' => $this->vipProgress->compute($deposits),
        ]);
    }
}
