<?php

namespace App\Console\Commands;

use App\Models\CrashTenantSettings;
use Illuminate\Console\Command;

/**
 * Pushes current config defaults to every crash_tenant_settings row.
 * Safe to run on every deploy — does not touch house-edge, limits, or other
 * operator-tuned columns; only syncs pace/engine run-state fields.
 */
class CrashSyncDefaultsCommand extends Command
{
    protected $signature = 'crash:sync-defaults
        {--dry-run : Print what would change without writing}';

    protected $description = 'Sync growth_per_second / betting_duration_seconds / tick_hz from config to all tenant rows';

    public function handle(): int
    {
        $growth = (float) config('crash.defaults.growth_per_second', 0.055);
        $betting = (int) config('crash.defaults.betting_duration_seconds', 10);
        $hz = max(1, (int) config('crash.defaults.tick_hz', 10));

        $this->line("Config values: growth={$growth}, betting_secs={$betting}, tick_hz={$hz}");

        $rows = CrashTenantSettings::query()->get(['tenant_id', 'growth_per_second', 'betting_duration_seconds', 'tick_hz']);

        if ($rows->isEmpty()) {
            $this->warn('No crash_tenant_settings rows found. Run db:seed first.');

            return self::SUCCESS;
        }

        foreach ($rows as $row) {
            $changed = $row->growth_per_second != $growth
                || $row->betting_duration_seconds != $betting
                || $row->tick_hz != $hz;

            if (! $changed) {
                $this->line("tenant {$row->tenant_id}: already up-to-date");

                continue;
            }

            $this->line("tenant {$row->tenant_id}: growth {$row->growth_per_second}→{$growth}, betting {$row->betting_duration_seconds}→{$betting}, hz {$row->tick_hz}→{$hz}");

            if (! $this->option('dry-run')) {
                $row->update([
                    'growth_per_second' => $growth,
                    'betting_duration_seconds' => $betting,
                    'tick_hz' => $hz,
                ]);
            }
        }

        if ($this->option('dry-run')) {
            $this->warn('Dry run — no changes written.');
        } else {
            $this->info('Done.');
        }

        return self::SUCCESS;
    }
}
