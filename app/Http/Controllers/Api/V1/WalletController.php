<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\WalletDepositClaimRequest;
use App\Http\Requests\WalletWithdrawRequest;
use App\Services\Tenant\TenantResolver;
use App\Services\Wallet\WalletDepositClaimService;
use App\Services\Wallet\WalletReadService;
use App\Services\Wallet\WalletWithdrawService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function show(Request $request, WalletReadService $wallets): JsonResponse
    {
        return $wallets->summary($request);
    }

    public function transactions(Request $request, WalletReadService $wallets): JsonResponse
    {
        return $wallets->transactions($request);
    }

    public function withdraw(
        WalletWithdrawRequest $request,
        TenantResolver $tenants,
        WalletWithdrawService $withdraw,
    ): JsonResponse {
        return $withdraw->submit($request, $tenants);
    }

    public function claimDeposit(
        WalletDepositClaimRequest $request,
        WalletDepositClaimService $claimService,
    ): JsonResponse {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $claimService->submit($user, $request->validated('currency'), $request->validated('network'));

        return response()->json(['success' => true]);
    }
}
