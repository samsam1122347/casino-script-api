<?php

namespace App\Services\Crash;

use App\Events\CrashBetAction;
use App\Models\CrashBet;

final class CrashLiveBetBroadcaster
{
    public function broadcast(CrashBet $bet, string $action): void
    {
        $loaded = $bet->loadMissing(['round.tenant:id,slug', 'user:id,name,username,email']);
        $tenantSlug = $loaded->round?->tenant?->slug;

        if (! is_string($tenantSlug) || $tenantSlug === '') {
            return;
        }

        event(new CrashBetAction(
            tenantSlug: $tenantSlug,
            action: $action,
            bet: $this->publicRow($loaded),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function publicRow(CrashBet $bet): array
    {
        return [
            'id' => (string) $bet->id,
            'crash_round_id' => (string) $bet->crash_round_id,
            'round_phase' => is_string($bet->round?->phase) ? $bet->round->phase : null,
            'player' => $this->publicPlayerHandle($bet),
            'stake_minor' => (int) $bet->stake_minor,
            'status' => $this->publicBetStatus((string) $bet->status),
            'cashout_multiplier' => $bet->cashout_multiplier !== null ? (float) $bet->cashout_multiplier : null,
            'payout_minor' => $bet->status === 'lost'
                ? 0
                : ($bet->payout_minor !== null ? (int) $bet->payout_minor : null),
            'created_at' => $bet->created_at?->toIso8601String(),
        ];
    }

    private function publicBetStatus(string $status): string
    {
        return match ($status) {
            'cashed_out' => 'cashed_out',
            'lost' => 'lost',
            'refunded' => 'refunded',
            default => 'open',
        };
    }

    private function publicPlayerHandle(CrashBet $bet): string
    {
        $raw = $bet->user?->username ?: $bet->user?->name;
        if (is_string($raw) && trim($raw) !== '') {
            return $this->maskPlayerName($raw);
        }

        return 'Player #'.strtoupper(substr(hash('sha256', (string) $bet->user_id), 0, 6));
    }

    private function maskPlayerName(string $name): string
    {
        $clean = preg_replace('/\s+/', '', trim($name)) ?: 'Player';
        $length = mb_strlen($clean);

        if ($length <= 3) {
            return mb_substr($clean, 0, 1).'***';
        }

        return mb_substr($clean, 0, min(3, $length - 1)).'***'.mb_substr($clean, -1);
    }
}
