<?php

namespace Tests\Feature;

use App\Services\Crash\CrashRoundEmitter;
use Database\Seeders\TenantSeeder;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CrashRoundEmitterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TenantSeeder::class);
    }

    public function test_demo_tick_queues_broadcast_when_not_immediate(): void
    {
        Queue::fake();

        config(['crash.broadcast_immediately' => false]);

        app(CrashRoundEmitter::class)->emitDemoTick('crashx');

        Queue::assertPushed(BroadcastEvent::class);
    }

    public function test_demo_tick_bypasses_queue_when_immediate(): void
    {
        Queue::fake();

        config(['crash.broadcast_immediately' => true]);

        app(CrashRoundEmitter::class)->emitDemoTick('crashx');

        Queue::assertNothingPushed();
    }
}
