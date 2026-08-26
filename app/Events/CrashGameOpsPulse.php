<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * House-only crash telemetry (committed crash point mid-round, override queue, exposures).
 *
 * Clients subscribe on private channel tenants.{tenantSlug}.crash-ops (@see routes/channels.php).
 * Never broadcast sensitive fields on {@see CrashGameTick}.
 */
class CrashGameOpsPulse implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $tenantSlug,
        /** External round UUID string (crash_round.external_round_id). */
        public string $roundExternalId,
        /** Internal crash_rounds.pk */
        public string $crashRoundPk,
        public string $phase,
        /** Multiplier ladder value shown publicly (same semantic as CrashGameTick.multiplier). */
        public float $displayMultiplier,
        /** Persisted bust target once betting closed (operators only — null during betting). */
        public ?float $committedCrashMultiplier,
        /** Pending override waiting to consume on next run start — operators only. */
        public ?float $pendingOperatorCrashMultiplierFromSettings,
        public int $openBetCount,
        public int $openStakeMinorSum,
        public ?string $generationSource,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('tenants.'.$this->tenantSlug.'.crash-ops')];
    }

    public function broadcastAs(): string
    {
        return 'CrashGameOpsPulse';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'tenant_slug' => $this->tenantSlug,
            'round_id' => $this->roundExternalId,
            'crash_round_pk' => $this->crashRoundPk,
            'phase' => $this->phase,
            'display_multiplier' => $this->displayMultiplier,
            'committed_crash_multiplier' => $this->committedCrashMultiplier,
            'pending_operator_override_multiplier' => $this->pendingOperatorCrashMultiplierFromSettings,
            'open_bet_count' => $this->openBetCount,
            'open_stake_minor_sum' => $this->openStakeMinorSum,
            'generation_source' => $this->generationSource,
            'emitted_at' => now()->toIso8601String(),
        ];
    }
}
