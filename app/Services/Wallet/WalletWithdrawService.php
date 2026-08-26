<?php

namespace App\Services\Wallet;

use App\Http\Requests\WalletWithdrawRequest;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\Tenant\TenantResolver;
use App\Services\Tenant\TenantUserGuard;
use App\Support\TenantCryptoAssets;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class WalletWithdrawService
{
    public function submit(WalletWithdrawRequest $request, TenantResolver $tenants): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $tenant = $tenants->tenantFromRequest($request);

        TenantUserGuard::assertUserBelongsToTenant($user, $tenant);

        $rawIdem = $request->headers->get('Idempotency-Key');
        $idempotencyKey = null;
        if (is_string($rawIdem)) {
            $idempotencyKey = trim($rawIdem);
            if (strlen($idempotencyKey) > 128) {
                abort(422, 'Invalid Idempotency-Key.');
            }
            if ($idempotencyKey === '') {
                $idempotencyKey = null;
            }
        }

        /** @var array{crypto_asset_id: string, destination_address: string, amount_minor: int} $v */
        $v = $request->validated();
        $assetId = $v['crypto_asset_id'];
        $destinationAddress = $v['destination_address'];
        $amountMinor = (int) $v['amount_minor'];

        $assets = TenantCryptoAssets::sanitize($tenant->crypto_assets ?? []);
        $asset = TenantCryptoAssets::findById($assets, $assetId);
        if ($asset === null) {
            return response()->json(['message' => 'Unknown crypto asset for this tenant.'], 422);
        }

        $minUsd = isset($asset['min_withdraw_usd']) && is_numeric($asset['min_withdraw_usd'])
            ? round((float) $asset['min_withdraw_usd'], 2)
            : 0.0;
        $minMinor = $minUsd > 0
            ? max(1, (int) round($minUsd * 100))
            : (int) config('gaming.min_withdraw_minor_default', 1000);

        $maxMinor = (int) config('gaming.max_withdraw_minor_per_request', 50000_00);
        if ($amountMinor > $maxMinor) {
            return response()->json([
                'message' => 'Amount exceeds per-request withdrawal limit.',
                'max_amount_minor' => $maxMinor,
            ], 422);
        }
        if ($amountMinor < $minMinor) {
            return response()->json([
                'message' => 'Amount below minimum withdrawal for this asset.',
                'min_amount_minor' => $minMinor,
            ], 422);
        }

        $idemStorageKey = null;
        $idemDbKey = null;
        if ($idempotencyKey !== null) {
            $payloadHash = hash('sha256', "{$assetId}\n{$destinationAddress}\n{$amountMinor}");
            $idemStorageKey = 'withdraw:'.$user->id.':'.$idempotencyKey.':'.$payloadHash;
            // Per-wallet DB-unique key — the only race-safe guard (the Cache layer below is a fast path only).
            $idemDbKey = substr('wd:'.$idempotencyKey.':'.$payloadHash, 0, 191);
            $cached = Cache::get($idemStorageKey);
            if (is_array($cached) && isset($cached['status'], $cached['body']) && is_int($cached['status'])) {
                return response()->json($cached['body'], $cached['status']);
            }
        }

        try {
            $body = DB::transaction(function () use ($user, $amountMinor, $destinationAddress, $asset, $idemDbKey) {
                /** @var Wallet|null $wallet */
                $wallet = Wallet::query()->where('user_id', $user->id)->lockForUpdate()->first();
                if (! $wallet) {
                    abort(404, 'Wallet not found.');
                }

                // Idempotency replay: the wallet row is locked, so a concurrent duplicate is
                // serialized behind us and will find the committed row here on its turn.
                if ($idemDbKey !== null) {
                    /** @var WalletTransaction|null $existing */
                    $existing = WalletTransaction::query()
                        ->where('wallet_id', $wallet->id)
                        ->where('idempotency_key', $idemDbKey)
                        ->first();

                    if ($existing !== null) {
                        return [
                            'ok' => true,
                            'withdrawal' => [
                                'id' => (string) $existing->id,
                                'amount_minor' => abs((int) $existing->amount_minor),
                                'balance_after_minor' => (int) $existing->balance_after_minor,
                                'asset_id' => $existing->meta['asset_id'] ?? null,
                                'status' => $existing->meta['status'] ?? null,
                            ],
                        ];
                    }
                }

                if ($wallet->balance_minor < $amountMinor) {
                    abort(422, 'Insufficient balance.');
                }

                $wallet->balance_minor -= $amountMinor;
                $wallet->save();

                $tx = WalletTransaction::query()->create([
                    'wallet_id' => $wallet->id,
                    'type' => 'withdrawal',
                    'idempotency_key' => $idemDbKey,
                    'amount_minor' => -$amountMinor,
                    'balance_after_minor' => $wallet->balance_minor,
                    'meta' => [
                        'status' => 'pending',
                        'asset_id' => $asset['id'],
                        'symbol' => $asset['symbol'],
                        'network' => $asset['network'],
                        'destination_address' => $destinationAddress,
                    ],
                ]);

                return [
                    'ok' => true,
                    'withdrawal' => [
                        'id' => (string) $tx->id,
                        'amount_minor' => $amountMinor,
                        'balance_after_minor' => $wallet->balance_minor,
                        'asset_id' => $asset['id'],
                        'status' => 'pending',
                    ],
                ];
            });
        } catch (HttpException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getStatusCode());
        }

        Log::info('wallet.withdraw.request', [
            'tenant_slug' => $tenant->slug,
            'user_id' => (string) $user->id,
            'asset_id' => $assetId,
            'amount_minor' => $amountMinor,
            'destination_tail' => self::maskTail($destinationAddress),
            'transaction_id' => $body['withdrawal']['id'] ?? null,
        ]);

        if ($idemStorageKey !== null) {
            Cache::put($idemStorageKey, ['status' => 200, 'body' => $body], 86400);
        }

        return response()->json($body);
    }

    private static function maskTail(string $address): string
    {
        $t = trim($address);
        if (mb_strlen($t) < 12) {
            return '***';
        }

        return '…'.mb_substr($t, -8);
    }
}
