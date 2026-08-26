<?php

namespace App\Services\Crash;

use App\Models\CrashRound;
use App\Models\CrashTenantSettings;
use App\Services\Crash\Engine\CrashMultiplierClock;

final class CrashGameStateService
{
    /** @return array<string, mixed> */
    public function stateForTenant(string $tenantId): array
    {
        $now = now();
        $recentBusted = $this->recentBustedMultipliers($tenantId);
        $settings = CrashTenantSettings::query()->where('tenant_id', $tenantId)->first();
        $settingsExist = $settings !== null;
        $limits = $this->limitsFor($settings);

        // Effective engine state — the global config flag AND the per-tenant flag.
        // Reporting only the config flag (as before) masked a tenant whose
        // crash_tenant_settings.engine_enabled was false, so the engine silently
        // skipped it while the UI claimed the engine was on.
        $configEngineEnabled = (bool) config('crash.engine.enabled', false);
        $engineEnabled = $configEngineEnabled && (bool) ($settings?->engine_enabled ?? false);

        /** @var CrashRound|null $round */
        $round = CrashRound::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('phase', ['betting', 'running'])
            ->orderByDesc('started_at')
            ->orderByDesc('created_at')
            ->first();

        if ($round === null) {
            $round = $this->recentClosedRound($tenantId, $now);
        }

        if ($round === null) {
            return [
                'engine_enabled' => $engineEnabled,
                'config_engine_enabled' => $configEngineEnabled,
                'tenant_engine_enabled' => (bool) ($settings?->engine_enabled ?? false),
                'tenant_settings_present' => $settingsExist,
                'game_enabled' => (bool) ($settings?->game_enabled ?? false),
                'game_paused' => (bool) ($settings?->game_paused ?? false),
                'round' => null,
                'multiplier_preview' => 1.0,
                'recent_busted_multipliers' => $recentBusted,
                'limits' => $limits,
                'server_now' => $now->toIso8601String(),
            ];
        }

        return [
            'engine_enabled' => $engineEnabled,
            'config_engine_enabled' => $configEngineEnabled,
            'tenant_engine_enabled' => (bool) ($settings?->engine_enabled ?? false),
            'tenant_settings_present' => $settingsExist,
            'game_enabled' => (bool) ($settings?->game_enabled ?? false),
            'game_paused' => (bool) ($settings?->game_paused ?? false),
            'limits' => $limits,
            'round' => [
                'id' => (string) $round->id,
                'external_round_id' => $round->external_round_id,
                'phase' => $round->phase,
                'betting_opens_at' => $round->betting_opens_at?->toIso8601String(),
                'betting_closes_at' => $round->betting_closes_at?->toIso8601String(),
                'started_running_at' => $round->started_running_at?->toIso8601String(),
                // Client runs the exponential curve locally for a smooth multiplier.
                'growth_per_second' => (float) ($round->growth_per_second_snapshot
                    ?? $settings?->growth_per_second
                    ?? config('crash.defaults.growth_per_second', 0.18)),
                'hash_commitment' => ($settings?->provably_fair_enabled ?? false)
                    ? $round->hash_commitment
                    : null,
                'pf_nonce' => ($settings?->provably_fair_enabled ?? false) ? $round->pf_nonce : null,
                'generation_source' => $round->generation_source,
                'crash_point_multiplier' => $round->phase === 'busted' ? (float) $round->crash_point_multiplier : null,
            ],
            'multiplier_preview' => CrashMultiplierClock::displayAt($round, $now),
            'recent_busted_multipliers' => $recentBusted,
            'server_now' => $now->toIso8601String(),
        ];
    }

    /**
     * Public stake/payout limits so the bet UI can validate before hitting the API.
     *
     * @return array<string, int|float|null>
     */
    private function limitsFor(?CrashTenantSettings $settings): array
    {
        if ($settings === null) {
            return [
                'min_bet_minor' => null,
                'max_bet_minor' => null,
                'max_win_minor_per_round' => null,
                'max_multiplier_cap' => null,
            ];
        }

        return [
            'min_bet_minor' => (int) $settings->min_bet_minor,
            'max_bet_minor' => (int) $settings->max_bet_minor,
            'max_win_minor_per_round' => (int) $settings->max_win_minor_per_round,
            'max_multiplier_cap' => (float) $settings->max_multiplier_cap,
        ];
    }

    private function recentClosedRound(string $tenantId, \Illuminate\Support\Carbon $now): ?CrashRound
    {
        $holdSeconds = max(0.0, (float) config('crash.engine.closed_hold_seconds', 2));
        if ($holdSeconds <= 0) {
            return null;
        }

        /** @var CrashRound|null $round */
        $round = CrashRound::query()
            ->where('tenant_id', $tenantId)
            ->where('phase', 'busted')
            ->whereNotNull('ended_at')
            ->orderByDesc('ended_at')
            ->orderByDesc('created_at')
            ->first();

        if ($round === null || $round->ended_at === null) {
            return null;
        }

        return $now->lessThan($round->ended_at->copy()->addSeconds($holdSeconds))
            ? $round
            : null;
    }

    /**
     * Completed crash outcomes for history UI / spectators (newest first).
     *
     * @return list<float>
     */
    private function recentBustedMultipliers(string $tenantId): array
    {
        return CrashRound::query()
            ->where('tenant_id', $tenantId)
            ->where('phase', 'busted')
            ->whereNotNull('crash_point_multiplier')
            ->orderByDesc('ended_at')
            ->orderByDesc('created_at')
            ->limit(32)
            ->get()
            ->map(static fn (CrashRound $r): float => round((float) $r->crash_point_multiplier, 2))
            ->values()
            ->all();
    }
}
