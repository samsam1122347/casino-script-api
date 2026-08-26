<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CrashBetPlaceRequest;
use App\Http\Requests\Api\V1\CrashCashoutRequest;
use App\Models\CrashBet;
use App\Models\CrashRound;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Crash\CrashBetService;
use App\Services\Crash\CrashCashoutService;
use App\Services\Crash\CrashGameStateService;
use App\Services\Crash\CrashLiveBetBroadcaster;
use App\Services\Tenant\TenantResolver;
use App\Services\Tenant\TenantUserGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CrashGameController extends Controller
{
    public function state(Request $request, TenantResolver $tenants, CrashGameStateService $stateService): JsonResponse
    {
        $tenant = $tenants->tenantFromRequest($request);

        return response()->json($stateService->stateForTenant((string) $tenant->getKey()), 200);
    }

    /**
     * Public read-only Crash state so guests and spectators (no bets) stay in sync
     * when WebSockets require auth or the queue hasn’t flushed ticks yet.
     */
    public function siteState(Request $request, TenantResolver $tenants, CrashGameStateService $stateService): JsonResponse
    {
        try {
            $tenant = $tenants->resolveForPublicConfig($request);
        } catch (\Throwable) {
            return response()->json(['message' => 'No tenant configured.'], 503);
        }

        return response()->json($stateService->stateForTenant((string) $tenant->getKey()), 200);
    }

    public function placeBet(
        CrashBetPlaceRequest $request,
        TenantResolver $tenants,
        CrashBetService $bets,
        CrashLiveBetBroadcaster $liveBets,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $tenant = $tenants->tenantFromRequest($request);

        TenantUserGuard::assertUserBelongsToTenant($user, $tenant);

        $rawIdem = $request->headers->get('Idempotency-Key');
        $idem = null;
        if (is_string($rawIdem)) {
            $idem = trim($rawIdem);
            if (strlen($idem) > 128) {
                return response()->json(['message' => 'Invalid Idempotency-Key.'], 422);
            }
            if ($idem === '') {
                $idem = null;
            }
        }

        /** @var array{stake_minor: int|string, auto_cashout_multiplier?: float|null} $v */
        $v = $request->validated();
        $stake = (int) $v['stake_minor'];

        $auto = array_key_exists('auto_cashout_multiplier', $v) && $v['auto_cashout_multiplier'] !== null
            ? (float) $v['auto_cashout_multiplier']
            : null;

        $idemStorageKey = null;
        if ($idem !== null) {
            $payloadHash = hash('sha256', $tenant->getKey()."\n".$stake."\n".($auto ?? 'null'));
            $idemStorageKey = 'crash:bet:'.$user->id.':'.$idem.':'.$payloadHash;

            /** @var array{status:int, body:mixed}|null $cached */
            $cached = Cache::get($idemStorageKey);

            if (is_array($cached) && isset($cached['status'], $cached['body']) && is_int($cached['status'])) {
                return response()->json($cached['body'], $cached['status']);
            }
        }

        try {
            $bet = $bets->placeBetTransactional(
                user: $user,
                tenantId: (string) $tenant->getKey(),
                stakeMinor: $stake,
                autoCashoutMultiplier: $auto,
                placeIdempotencyKey: $idem,
            );

            /** @var Wallet|null $wallet */
            $wallet = Wallet::query()->where('user_id', $user->id)->first();

            $payload = [
                'bet' => [
                    'id' => (string) $bet->id,
                    'crash_round_id' => (string) $bet->crash_round_id,
                    'stake_minor' => $bet->stake_minor,
                    'auto_cashout_multiplier' => $bet->auto_cashout_multiplier,
                    'status' => $bet->status,
                ],
                'balance_after_minor' => $wallet !== null ? $wallet->balance_minor : null,
            ];

            if ($idemStorageKey !== null) {
                Cache::put($idemStorageKey, ['status' => 200, 'body' => $payload], now()->addDay());
            }

            $liveBets->broadcast($bet, 'placed');

            return response()->json($payload, 200);
        } catch (HttpException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getStatusCode());
        }
    }

    public function cashOut(
        CrashCashoutRequest $request,
        TenantResolver $tenants,
        CrashCashoutService $cashouts,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $tenant = $tenants->tenantFromRequest($request);

        TenantUserGuard::assertUserBelongsToTenant($user, $tenant);

        $rawIdem = $request->headers->get('Idempotency-Key');
        $idem = null;
        if (is_string($rawIdem)) {
            $idem = trim($rawIdem);
            if (strlen($idem) > 128) {
                return response()->json(['message' => 'Invalid Idempotency-Key.'], 422);
            }
            if ($idem === '') {
                $idem = null;
            }
        }

        $round = CrashRound::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('phase', 'running')
            ->orderByDesc('started_running_at')
            ->first();

        if ($round === null) {
            return response()->json(['message' => 'No active running round.'], 404);
        }

        $idemStorageKey = null;

        if ($idem !== null) {
            $idemStorageKey = 'crash:cashout:'.$user->id.':'.$idem.':'.md5((string) $round->id);
            /** @var array{status:int, body:mixed}|null $cached */
            $cached = Cache::get($idemStorageKey);
            if (is_array($cached) && isset($cached['status'], $cached['body']) && is_int($cached['status'])) {
                return response()->json($cached['body'], $cached['status']);
            }
        }

        $result = $cashouts->cashout($user, $round, null);

        if (($result['ok'] ?? false) !== true) {
            return response()->json(['message' => $result['message'] ?? 'Cashout failed.'], (int) ($result['code'] ?? 400));
        }

        if ($idemStorageKey !== null) {
            Cache::put($idemStorageKey, ['status' => 200, 'body' => $result], now()->addDay());
        }

        return response()->json($result, 200);
    }

    public function cancelBet(
        Request $request,
        TenantResolver $tenants,
        CrashBetService $bets,
        CrashLiveBetBroadcaster $liveBets,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $tenant = $tenants->tenantFromRequest($request);

        TenantUserGuard::assertUserBelongsToTenant($user, $tenant);

        try {
            $result = $bets->cancelOpenBetTransactional(
                user: $user,
                tenantId: (string) $tenant->getKey(),
            );

            $liveBets->broadcast($result['bet'], 'refunded');

            return response()->json([
                'ok' => true,
                'bet' => [
                    'id' => (string) $result['bet']->id,
                    'crash_round_id' => (string) $result['bet']->crash_round_id,
                    'stake_minor' => $result['bet']->stake_minor,
                    'status' => $result['bet']->status,
                ],
                'balance_after_minor' => $result['balance_after_minor'],
            ], 200);
        } catch (HttpException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getStatusCode());
        }
    }

    public function myBets(Request $request, TenantResolver $tenants): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $tenant = $tenants->tenantFromRequest($request);

        TenantUserGuard::assertUserBelongsToTenant($user, $tenant);

        $limit = min(100, max(1, (int) $request->query('limit', 25)));

        $rows = CrashBet::query()
            ->where('user_id', $user->id)
            ->whereHas('round', fn ($q) => $q->where('tenant_id', $tenant->getKey()))
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        return response()->json([
            'bets' => $rows->map(fn (CrashBet $b) => [
                'id' => (string) $b->id,
                'crash_round_id' => (string) $b->crash_round_id,
                'stake_minor' => $b->stake_minor,
                'auto_cashout_multiplier' => $b->auto_cashout_multiplier,
                'cashout_multiplier' => $b->cashout_multiplier,
                'payout_minor' => $b->payout_minor,
                'status' => $b->status,
                'created_at' => $b->created_at?->toIso8601String(),
            ]),
        ]);
    }

    public function liveBets(Request $request, TenantResolver $tenants, CrashLiveBetBroadcaster $liveBets): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $tenant = $tenants->tenantFromRequest($request);

        TenantUserGuard::assertUserBelongsToTenant($user, $tenant);

        $limit = min(100, max(1, (int) $request->query('limit', 60)));

        $rows = CrashBet::query()
            ->with(['round:id,tenant_id,phase', 'user:id,name,username,email'])
            ->whereHas('round', fn ($q) => $q->where('tenant_id', $tenant->getKey()))
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        return response()->json([
            'bets' => $rows->map(fn (CrashBet $b) => [
                ...$liveBets->publicRow($b),
                'source' => 'real',
            ]),
            'server_now' => now()->toIso8601String(),
        ]);
    }
}
