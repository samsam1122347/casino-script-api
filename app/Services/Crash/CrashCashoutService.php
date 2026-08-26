<?php

namespace App\Services\Crash;

use App\Models\CrashBet;
use App\Models\CrashRound;
use App\Models\CrashTenantSettings;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\Crash\Engine\CrashMultiplierClock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class CrashCashoutService
{
    public function __construct(
        private readonly CrashLiveBetBroadcaster $liveBets,
    ) {}

    /**
     * Idempotent cash-out for the user's open bet on the given round (must be running).
     *
     * @return array{ok: true, cashout: array<string, mixed>}|array{ok: false, message: string, code: int}
     */
    public function cashout(
        User $user,
        CrashRound $round,
        ?float $forcedMultiplier,
    ): array {
        if ($round->phase !== 'running') {
            return ['ok' => false, 'message' => 'Round is not running.', 'code' => 409];
        }

        $settings = CrashTenantSettings::query()->where('tenant_id', $round->tenant_id)->first();
        if ($settings === null) {
            return ['ok' => false, 'message' => 'Crash is not configured for this tenant.', 'code' => 503];
        }

        try {
            $body = DB::transaction(function () use ($user, $round, $settings, $forcedMultiplier) {
                /** @var CrashBet|null $bet */
                $bet = CrashBet::query()
                    ->where('crash_round_id', $round->id)
                    ->where('user_id', $user->id)
                    ->where('status', 'open')
                    ->lockForUpdate()
                    ->first();

                if ($bet === null) {
                    abort(404, 'No open bet for this round.');
                }

                $round->refresh();
                if ($round->phase !== 'running') {
                    abort(409, 'Round is no longer running.');
                }

                $authoritative = $forcedMultiplier !== null
                    ? round($forcedMultiplier, 4)
                    : CrashMultiplierClock::displayAt($round, now());
                $stake = (int) $bet->stake_minor;
                $payout = (int) floor($stake * $authoritative);
                $cap = (int) $settings->max_win_minor_per_round;
                if ($payout > $cap) {
                    $payout = $cap;
                }

                /** @var Wallet|null $wallet */
                $wallet = Wallet::query()->where('user_id', $user->id)->lockForUpdate()->first();
                if ($wallet === null) {
                    abort(404, 'Wallet not found.');
                }

                $wallet->balance_minor += $payout;
                $wallet->save();

                WalletTransaction::query()->create([
                    'wallet_id' => $wallet->id,
                    'type' => 'crash_payout',
                    'amount_minor' => $payout,
                    'balance_after_minor' => $wallet->balance_minor,
                    'meta' => [
                        'crash_round_id' => (string) $round->id,
                        'crash_bet_id' => (string) $bet->id,
                        'cashout_multiplier' => $authoritative,
                    ],
                ]);

                $bet->cashout_multiplier = $authoritative;
                $bet->payout_minor = $payout;
                $bet->status = 'cashed_out';
                $bet->save();

                return [
                    'ok' => true,
                    'cashout' => [
                        'bet_id' => (string) $bet->id,
                        'round_id' => (string) $round->id,
                        'external_round_id' => (string) $round->external_round_id,
                        'cashout_multiplier' => $authoritative,
                        'payout_minor' => $payout,
                        'balance_after_minor' => $wallet->balance_minor,
                    ],
                ];
            });

            Log::channel('gaming')->info('crash.cashout', [
                'event' => 'cashout',
                'source' => $forcedMultiplier !== null ? 'auto' : 'manual',
                'tenant_id' => (string) $round->tenant_id,
                'user_id' => (string) $user->id,
                'crash_bet_id' => $body['cashout']['bet_id'] ?? null,
                'crash_round_id' => $body['cashout']['round_id'] ?? null,
                'external_round_id' => $body['cashout']['external_round_id'] ?? null,
                'cashout_multiplier' => $body['cashout']['cashout_multiplier'] ?? null,
                'payout_minor' => $body['cashout']['payout_minor'] ?? null,
                'balance_after_minor' => $body['cashout']['balance_after_minor'] ?? null,
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            $betId = $body['cashout']['bet_id'] ?? null;
            if (is_string($betId)) {
                /** @var CrashBet|null $broadcastBet */
                $broadcastBet = CrashBet::query()->find($betId);
                if ($broadcastBet !== null) {
                    $this->liveBets->broadcast($broadcastBet, $forcedMultiplier !== null ? 'auto_cashed_out' : 'cashed_out');
                }
            }

            /** @phpstan-ignore return.type */
            return $body;
        } catch (HttpException $e) {
            return ['ok' => false, 'message' => $e->getMessage(), 'code' => $e->getStatusCode()];
        }
    }

    public function tryAutoCashouts(CrashRound $round): void
    {
        if ($round->phase !== 'running') {
            return;
        }

        $mult = CrashMultiplierClock::displayAt($round, now());
        $crash = $round->crash_point_multiplier !== null
            ? (float) $round->crash_point_multiplier
            : null;

        $bets = CrashBet::query()
            ->where('crash_round_id', $round->id)
            ->where('status', 'open')
            ->whereNotNull('auto_cashout_multiplier')
            ->orderBy('created_at')
            ->get();

        foreach ($bets as $bet) {
            $target = (float) $bet->auto_cashout_multiplier;

            // A target above the round's crash point is never reached — the bet loses.
            // Without this guard the 1e-4 epsilon below would pay out just above crash.
            if ($crash !== null && $target > $crash + 1e-9) {
                continue;
            }

            if ($mult + 1e-4 >= $target) {
                /** @var User|null $user */
                $user = User::query()->find($bet->user_id);
                if ($user === null) {
                    continue;
                }
                $round->refresh();
                $this->cashout($user, $round, $target);
            }
        }
    }
}
