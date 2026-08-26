<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tenant_id',
    'external_round_id',
    'phase',
    'crash_point_multiplier',
    'hash_commitment',
    'revealed_server_seed',
    'pf_server_seed',
    'pf_nonce',
    'generation_source',
    'betting_opens_at',
    'betting_closes_at',
    'started_running_at',
    'max_multiplier_cap_snapshot',
    'growth_per_second_snapshot',
    'last_tick_broadcast_at',
    'last_multiplier',
    'tick_count',
    'started_at',
    'ended_at',
])]
class CrashRound extends Model
{
    use HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'betting_opens_at' => 'datetime',
            'betting_closes_at' => 'datetime',
            'started_running_at' => 'datetime',
            'last_tick_broadcast_at' => 'datetime',
            'last_multiplier' => 'decimal:4',
            'crash_point_multiplier' => 'decimal:4',
            'max_multiplier_cap_snapshot' => 'decimal:4',
            'growth_per_second_snapshot' => 'decimal:6',
        ];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return HasMany<CrashBet, $this> */
    public function bets(): HasMany
    {
        return $this->hasMany(CrashBet::class);
    }
}
