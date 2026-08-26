<?php

namespace App\Console\Commands;

use App\Models\CrashRound;
use App\Models\CrashTenantSettings;
use App\Models\Tenant;
use App\Services\Crash\CrashGameStateService;
use App\Services\Crash\Engine\CrashRoundEngine;
use Illuminate\Console\Command;

class CrashEngineInspectCommand extends Command
{
    protected $signature = 'crash:inspect
        {tenant_slug=crashx : Tenant slug matching X-Tenant-Slug}
        {--tick : Run one engine tick for this tenant before printing state}';

    protected $description = 'Print Crash engine config, tenant settings, and recent rounds (avoid fragile tinker quoting)';

    public function handle(CrashGameStateService $gameState, CrashRoundEngine $engine): int
    {
        $slug = strtolower(trim((string) $this->argument('tenant_slug')));

        $tenant = Tenant::query()->where('slug', $slug)->first();
        if ($tenant === null) {
            $this->error('No tenant with slug ['.$slug.'].');

            return self::FAILURE;
        }

        $this->line('config.crash.engine.enabled = '.json_encode(config('crash.engine.enabled', false)));
        $this->line('tenant id = '.$tenant->getKey().' slug = '.$tenant->slug);
        $this->newLine();

        $settings = CrashTenantSettings::query()->where('tenant_id', $tenant->getKey())->first();

        if ($settings === null) {
            $this->warn('No crash_tenant_settings row for this tenant (engine will create defaults on first tick).');
        } else {
            $this->components->bulletList([
                'engine_enabled '.$this->dumpBool($settings->engine_enabled),
                'game_enabled '.$this->dumpBool($settings->game_enabled),
                'game_paused '.$this->dumpBool($settings->game_paused),
                'growth_per_second '.json_encode((float) $settings->growth_per_second),
                'tick_hz '.$settings->tick_hz,
                'betting_duration_seconds '.$settings->betting_duration_seconds,
            ]);
        }

        if ((bool) $this->option('tick')) {
            $before = CrashRound::query()->where('tenant_id', $tenant->getKey())->count();
            $this->newLine();
            $this->line('Running one crash:tick for tenant ['.$slug.'] before inspection...');
            $engine->tickTenant($tenant);
            $after = CrashRound::query()->where('tenant_id', $tenant->getKey())->count();
            $this->line('  crash_rounds count before='.$before.' after='.$after);
        }

        $this->newLine();
        $state = $gameState->stateForTenant((string) $tenant->getKey());

        $round = $state['round'] ?? null;
        $phase = 'none';
        if (is_array($round) && isset($round['phase']) && is_string($round['phase'])) {
            $phase = $round['phase'];
        }
        $this->line('public state.phase = '.$phase);

        $multPreview = $state['multiplier_preview'] ?? null;
        if (is_numeric($multPreview)) {
            $this->line('public state.multiplier_preview = '.json_encode((float) $multPreview));
        }

        $this->newLine();
        $rows = CrashRound::query()
            ->where('tenant_id', $tenant->getKey())
            ->orderByDesc('created_at')
            ->limit(8)
            ->get(['phase', 'external_round_id', 'crash_point_multiplier', 'growth_per_second_snapshot', 'started_running_at', 'betting_closes_at']);

        $noRounds = $rows->isEmpty();

        if ($noRounds) {
            $this->warn('No crash_rounds rows for this tenant.');
        } else {
            $this->table(
                ['phase', 'crash_mult', 'growth_snap', 'external_round_id', 'bet_closes/run_start'],
                $rows->map(fn (CrashRound $r): array => [
                    $r->phase,
                    (string) ($r->crash_point_multiplier ?? ''),
                    $r->growth_per_second_snapshot ?? '—',
                    substr((string) $r->external_round_id, 0, 8).'…',
                    ($r->betting_closes_at ?? $r->started_running_at)?->toIso8601String() ?? '—',
                ])->all(),
            );
        }

        if (
            $noRounds
            && config('crash.engine.enabled', false)
            && Tenant::query()->count() >= 1
        ) {
            $looksReady =
                $settings !== null
                && $settings->engine_enabled
                && $settings->game_enabled
                && ! $settings->game_paused;

            if ($looksReady || $settings === null) {
                $this->newLine();
                $this->warn(
                    'Engine is configured to advance rounds but this tenant has no crash_round rows yet. '
                    .'Ensure Laravel schedule:work is running (Compose schedule service). '
                    .'Manual check: php artisan crash:tick '.$slug.' — if a betting row appears, the ticker was never scheduled.',
                );
            }
        }

        $this->newLine();
        $this->line('Place-bet check (CrashBetService needs a betting row):');
        $bettingRound = CrashRound::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('phase', 'betting')
            ->orderByDesc('created_at')
            ->first();
        /** @var CrashRound|null $blockingRunning */
        $blockingRunning = CrashRound::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('phase', 'running')
            ->orderByDesc('created_at')
            ->first();

        if ($bettingRound !== null) {
            $this->line('  Betting row AVAILABLE (id='.$bettingRound->id.') — if bets still fail, check betting_closes_at, X-Tenant-Slug.');
        } else {
            $this->warn('  No betting-phase row → POST …/crash/bets → 409 “No betting round is available”.');
            if ($blockingRunning !== null) {
                $this->line('  Note: tenant has RUNNING round id='.$blockingRunning->id.' (engine must bust it before another betting opens). Use crash:inspect growth columns and Laravel logs.');
            }
        }

        $this->line('  Tenant rows in DB: '.Tenant::query()->count().' (`tenantFromRequest` fallback no longer nondeterministic: orderBy slug).');

        return self::SUCCESS;
    }

    private function dumpBool(bool $b): string
    {
        return json_encode($b);
    }
}
