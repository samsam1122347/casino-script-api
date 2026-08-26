<?php

namespace App\Services\Wallet;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class WalletReadService
{
    public function summary(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user()->loadMissing('wallet');

        $wallet = $user->wallet;
        if (! $wallet) {
            return response()->json(['wallet' => null], 404);
        }

        return response()->json([
            'wallet' => [
                'id' => $wallet->id,
                'currency' => $wallet->currency,
                'balance_minor' => $wallet->balance_minor,
                'balance' => $wallet->balanceMajor(),
            ],
        ]);
    }

    public function transactions(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user()->loadMissing('wallet');

        $wallet = $user->wallet;
        if (! $wallet) {
            return response()->json(['data' => [], 'meta' => ['total' => 0]]);
        }

        // Clamp to 1..50 — a 0/negative per_page makes paginate() return every row.
        $perPage = max(1, min((int) $request->query('per_page', 20), 50));

        $paginator = $wallet->transactions()
            ->orderByDesc('created_at')
            ->paginate($perPage);

        $items = $paginator->getCollection()->map(fn ($row) => [
            'id' => $row->id,
            'type' => $row->type,
            'amount_minor' => $row->amount_minor,
            'amount' => round($row->amount_minor / 100, 2),
            'balance_after_minor' => $row->balance_after_minor,
            'meta' => $row->meta,
            'created_at' => $row->created_at?->toIso8601String(),
        ]);

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }
}
