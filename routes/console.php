<?php

use App\Models\CrashRound;
use App\Models\Tenant;
use App\Services\Crash\CrashRecording\CrashRoundRecordingService;
use App\Services\Crash\CrashRoundEmitter;
use App\Services\Crash\Engine\CrashRoundEngine;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('crash:new-round {tenant_slug=crashx}', function (string $tenant_slug, CrashRoundRecordingService $recording): void {
    $recording->forgetStickyExternalRound($tenant_slug);
    $this->components->info('Next `crash:emit-tick` for ['.$tenant_slug.'] allocates a fresh external_round_id.');
})->purpose('Clear sticky demo Crash round cache key per tenant_slug');

$emitCrashTick = function (string $tenant_slug, CrashRoundEmitter $emitter): void {
    $emitter->emitDemoTick($tenant_slug);
    $this->components->info(
        'Crash tick emitted for private-tenants.'.$tenant_slug.'.crash (queued unless CRASH_BROADCAST_IMMEDIATE=true).',
    );
};

Artisan::command('crash:emit-tick {tenant_slug=crashx}', $emitCrashTick)
    ->purpose('Emit one crash round tick via CrashRoundEmitter (Soketi / Echo verification)');

Artisan::command('crash:broadcast-demo {tenant_slug=crashx}', $emitCrashTick)
    ->purpose('Alias of crash:emit-tick');

Artisan::command('crash:tick {tenant_slug?}', function (CrashRoundEngine $engine): void {
    if (! config('crash.engine.enabled', false)) {
        $this->components->warn('CRASH_ENGINE_ENABLED is false — nothing to tick.');

        return;
    }

    $tenant_slug = $this->argument('tenant_slug');
    if ($tenant_slug !== null && trim($tenant_slug) !== '') {
        $tenant = Tenant::query()->where('slug', strtolower(trim($tenant_slug)))->firstOrFail();
        $before = CrashRound::query()->where('tenant_id', $tenant->getKey())->count();
        $engine->tickTenant($tenant);
        $after = CrashRound::query()->where('tenant_id', $tenant->getKey())->count();
        $active = CrashRound::query()
            ->where('tenant_id', $tenant->getKey())
            ->whereIn('phase', ['betting', 'running'])
            ->orderByDesc('created_at')
            ->value('phase') ?? 'none';
        $this->components->info('Crash engine tick executed for tenant ['.$tenant->slug.'] (rows '.$before.' -> '.$after.', active phase '.$active.').');

        return;
    }

    $tenants = 0;
    $before = CrashRound::query()->count();
    foreach (Tenant::query()->cursor() as $tenant) {
        $tenants++;
        $engine->tickTenant($tenant);
    }
    $after = CrashRound::query()->count();

    if ($tenants === 0) {
        $this->components->warn('Crash tick: no rows in `tenants` — engine did nothing. Seed a tenant (e.g. php artisan db:seed --class=TenantSeeder).');
    }

    $this->components->info('Crash engine tick executed for all tenants (tenants '.$tenants.', rows '.$before.' -> '.$after.').');
})->purpose('Advance server-authoritative Crash rounds (requires CRASH_ENGINE_ENABLED=true).');

/*
 * Scheduled fallback for the Crash engine — ONLY when the `crash:engine` daemon is
 * not running (config `crash.engine.daemon_enabled` / env CRASH_ENGINE_DAEMON).
 * In production the daemon container drives the engine at `daemon_hz`; the 1 Hz
 * scheduler is too coarse and forces clients to extrapolate past the real crash
 * point. This keeps local dev working with no extra process.
 *
 * Do not use withoutOverlapping() here: it uses a cache mutex and will *silently skip* a tick if the
 * previous artisan process still holds the lock (>1s ticks, slow DB, or two schedule workers). Manual
 * `php artisan crash:tick crashx` bypasses that mutex — which looked like "CLI works, scheduler doesn't".
 * CrashRoundEngine already serializes per-tenant work via DB transactions + lockForUpdate().
 * Run exactly one `schedule` container replica.
 */
if (config('crash.engine.enabled', false) && ! config('crash.engine.daemon_enabled', false)) {
    Schedule::command('crash:tick')->everySecond();
}
