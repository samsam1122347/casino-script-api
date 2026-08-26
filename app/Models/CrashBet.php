<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'crash_round_id',
    'user_id',
    'stake_minor',
    'auto_cashout_multiplier',
    'cashout_multiplier',
    'payout_minor',
    'status',
    'meta',
    'place_idempotency_key',
    'cashout_idempotency_key',
])]
class CrashBet extends Model
{
    use HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cashout_multiplier' => 'decimal:4',
            'auto_cashout_multiplier' => 'decimal:4',
            'meta' => 'array',
        ];
    }

    /** @return BelongsTo<CrashRound, $this> */
    public function round(): BelongsTo
    {
        return $this->belongsTo(CrashRound::class, 'crash_round_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
