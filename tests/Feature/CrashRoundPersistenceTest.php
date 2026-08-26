<?php

namespace Tests\Feature;

use App\Models\CrashRound;
use App\Models\Tenant;
use App\Services\Crash\CrashRecording\CrashRoundRecordingService;
use App\Services\Crash\CrashRoundEmitter;
use Database\Seeders\TenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class CrashRoundPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_ticks_reuse_same_db_round_within_sticky_period(): void
    {
        $this->seed(TenantSeeder::class);

        config(['crash.broadcast_immediately' => true]);
        Queue::fake();

        /** @var CrashRoundEmitter $emitter */
        $emitter = app(CrashRoundEmitter::class);
        $emitter->emitDemoTick('crashx');
        $emitter->emitDemoTick('crashx');

        $this->assertSame(1, CrashRound::query()->count());

        /** @var CrashRound $round */
        $round = CrashRound::query()->firstOrFail();

        $this->assertSame(2, $round->tick_count);
    }

    public function test_new_round_command_creates_separate_external_id_on_next_ticks(): void
    {
        $this->seed(TenantSeeder::class);

        config(['crash.broadcast_immediately' => true]);
        Queue::fake();

        $recording = app(CrashRoundRecordingService::class);
        $recording->forgetStickyExternalRound('crashx');

        $emitter = app(CrashRoundEmitter::class);
        $emitter->emitTick('crashx', (string) Str::uuid(), 1.2, 'running');

        Cache::forget(CrashRoundRecordingService::cacheKeyExternalRound('crashx'));

        $emitter->emitDemoTick('crashx');

        $tenant = Tenant::query()->where('slug', 'crashx')->firstOrFail();

        $this->assertSame(2, CrashRound::query()->where('tenant_id', $tenant->id)->count());
    }
}
