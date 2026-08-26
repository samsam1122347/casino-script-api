<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Crash\Engine\CrashRoundEngine;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Long-running Crash engine daemon.
 *
 * Replaces `Schedule::command('crash:tick')->everySecond()` in production: the
 * scheduler can only fire once per second, which forced clients to extrapolate
 * the multiplier curve ~1s ahead and overshoot the real crash point. This loop
 * ticks every tenant at a fixed sub-second rate so the broadcast feed is dense
 * enough for clients to interpolate (never extrapolate) between server ticks.
 *
 * Runs in its own container (deploy/docker-compose.yml `crash` service) under a
 * `restart: unless-stopped` policy. SIGTERM/SIGINT stop the loop cleanly between
 * ticks; an unexpected crash or the memory-ceiling exit is recovered by Docker.
 */
class CrashEngineDaemonCommand extends Command
{
    protected $signature = 'crash:engine
        {--hz= : Tick frequency in Hz (default: config crash.engine.daemon_hz)}
        {--once : Run a single tick pass for every tenant, then exit}';

    protected $description = 'Run the server-authoritative Crash engine as a fixed-rate daemon.';

    private bool $shouldStop = false;

    /** @var list<Tenant> */
    private array $tenantCache = [];

    private float $tenantCacheRefreshedAt = 0.0;

    public function handle(CrashRoundEngine $engine): int
    {
        if (! config('crash.engine.enabled', false)) {
            $this->components->warn('CRASH_ENGINE_ENABLED is false — daemon idle, exiting.');

            return self::SUCCESS;
        }

        if ($this->option('once')) {
            $this->tickAllTenants($engine);
            $this->components->info('Crash engine: single tick pass complete.');

            return self::SUCCESS;
        }

        $hz = (int) ($this->option('hz') ?: config('crash.engine.daemon_hz', 15));
        $hz = max(1, min(50, $hz));
        $intervalMicros = (int) round(1_000_000 / $hz);

        $this->registerSignalHandlers();
        $this->components->info(
            'Crash engine daemon started — '.$hz.' Hz ('.intdiv($intervalMicros, 1000).' ms interval), PID '.getmypid().'.',
        );

        // Recycle the process if memory creeps up; the container restart policy
        // brings it straight back. Cheap insurance against a slow leak.
        $memoryCeilingBytes = 256 * 1024 * 1024;
        $passes = 0;

        while (! $this->shouldStop) {
            $startedAt = microtime(true);

            $this->tickAllTenants($engine);

            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }
            if ($this->shouldStop) {
                break;
            }

            $passes++;
            if (($passes % 600) === 0 && memory_get_usage(true) > $memoryCeilingBytes) {
                $this->components->info('Memory ceiling reached — exiting for a clean container restart.');
                break;
            }

            $elapsedMicros = (int) round((microtime(true) - $startedAt) * 1_000_000);
            $sleepMicros = $intervalMicros - $elapsedMicros;
            if ($sleepMicros > 0) {
                usleep($sleepMicros);
            }
        }

        $this->components->info('Crash engine daemon stopped after '.$passes.' tick passes.');

        return self::SUCCESS;
    }

    private function tickAllTenants(CrashRoundEngine $engine): void
    {
        foreach ($this->tenants() as $tenant) {
            try {
                $engine->tickTenant($tenant);
            } catch (Throwable $e) {
                // One tenant's bad tick (or a transient DB hiccup) must never
                // take down the loop for every other tenant.
                report($e);
            }
        }
    }

    /**
     * Tenant list, refreshed every few seconds so a newly-created tenant is
     * picked up without re-querying on every sub-second tick.
     *
     * @return list<Tenant>
     */
    private function tenants(): array
    {
        $now = microtime(true);
        if ($this->tenantCache === [] || ($now - $this->tenantCacheRefreshedAt) >= 3.0) {
            $this->tenantCache = Tenant::query()->get()->all();
            $this->tenantCacheRefreshedAt = $now;
        }

        return $this->tenantCache;
    }

    private function registerSignalHandlers(): void
    {
        if (! function_exists('pcntl_signal')) {
            return;
        }

        $stop = function (): void {
            $this->shouldStop = true;
        };

        pcntl_signal(SIGTERM, $stop);
        pcntl_signal(SIGINT, $stop);
    }
}
