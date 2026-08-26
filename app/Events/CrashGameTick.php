<?php

namespace App\Events;

use App\Support\Crash\CrashPlayerBroadcastSanitizer;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Queued realtime tick for Crash-style rounds (broadcast after queue:work when
 * QUEUE_CONNECTION=redis). For in-process broadcasts see {@see CrashGameTickImmediate}.
 *
 * Channel naming: private-tenants.{tenantSlug}.crash (see routes/channels.php).
 * {@see CrashPlayerBroadcastSanitizer} strips sensitive extras before broadcast.
 */
class CrashGameTick implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $tenantSlug,
        public string $roundId,
        public float $multiplier,
        public string $phase,
        /** @var array<string, mixed> */
        public array $broadcastExtra = [],
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('tenants.'.$this->tenantSlug.'.crash')];
    }

    public function broadcastAs(): string
    {
        return 'CrashGameTick';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $base = [
            'round_id' => $this->roundId,
            'multiplier' => $this->multiplier,
            'phase' => $this->phase,
            'tenant_slug' => $this->tenantSlug,
            'emitted_at' => now()->toIso8601String(),
        ];

        /** Provably fair / stubs must be supplied in {@see $this->broadcastExtra} (CLI demo adds them in {@see CrashRoundEmitter::emitDemoTick}). */
        return [...$base, ...$this->broadcastExtra];
    }
}
