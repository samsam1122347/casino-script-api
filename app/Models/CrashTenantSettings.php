<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'house_edge_bp',
    'min_bet_minor',
    'max_bet_minor',
    'max_win_minor_per_round',
    'max_multiplier_cap',
    'betting_duration_seconds',
    'growth_per_second',
    'tick_hz',
    'provably_fair_enabled',
    'game_enabled',
    'game_paused',
    'engine_enabled',
    'pending_operator_crash_multiplier',
])]
class CrashTenantSettings extends Model
{
    protected $primaryKey = 'tenant_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public function getRouteKeyName(): string
    {
        return 'tenant_id';
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'house_edge_bp' => 'integer',
            'min_bet_minor' => 'integer',
            'max_bet_minor' => 'integer',
            'max_win_minor_per_round' => 'integer',
            'max_multiplier_cap' => 'decimal:4',
            'growth_per_second' => 'decimal:6',
            'tick_hz' => 'integer',
            'provably_fair_enabled' => 'boolean',
            'game_enabled' => 'boolean',
            'game_paused' => 'boolean',
            'engine_enabled' => 'boolean',
            'pending_operator_crash_multiplier' => 'decimal:4',
        ];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** Edge as fraction e.g. 400bp => 0.04 */
    public function houseEdgeFraction(): float
    {
        return max(0.0, min(0.5, ((float) $this->house_edge_bp) / 10000));
    }

    public static function defaultsForTenant(string $tenantId): array
    {
        return [
            'tenant_id' => $tenantId,
            'house_edge_bp' => (int) config('crash.defaults.house_edge_bp', 400),
            'min_bet_minor' => (int) config('crash.defaults.min_bet_minor', 100),
            'max_bet_minor' => (int) config('crash.defaults.max_bet_minor', 1_000_000),
            'max_win_minor_per_round' => (int) config('crash.defaults.max_win_minor_per_round', 500_000_00),
            'max_multiplier_cap' => (float) config('crash.defaults.max_multiplier_cap', 10000),
            'betting_duration_seconds' => (int) config('crash.defaults.betting_duration_seconds', 12),
            'growth_per_second' => (float) config('crash.defaults.growth_per_second', 0.055),
            'tick_hz' => (int) config('crash.defaults.tick_hz', 6),
            'provably_fair_enabled' => (bool) config('crash.defaults.provably_fair_enabled', true),
            'game_enabled' => true,
            'game_paused' => false,
            // The product ships with the Crash engine live for every tenant. The global
            // CRASH_ENGINE_ENABLED config is the system-wide kill switch (checked separately
            // in the engine + bet service); this per-tenant flag defaults ON so a new tenant
            // runs without manual setup. Previously this mirrored config() and could be
            // frozen to false if the row was seeded before the env/cache was correct.
            'engine_enabled' => true,
            'pending_operator_crash_multiplier' => null,
        ];
    }
}
