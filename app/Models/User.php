<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'tenant_id',
    'name',
    'username',
    'email',
    'password',
    'recovery_question_1',
    'recovery_question_2',
    'recovery_question_3',
    'recovery_answer_1',
    'recovery_answer_2',
    'recovery_answer_3',
])]
#[Hidden([
    'password',
    'remember_token',
    'recovery_answer_1',
    'recovery_answer_2',
    'recovery_answer_3',
])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasUuids, Notifiable;

    /** @return BelongsTo<Tenant, User> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return HasOne<Wallet, User> */
    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    /** @return HasMany<CrashBet, User> */
    public function crashBets(): HasMany
    {
        return $this->hasMany(CrashBet::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'recovery_answer_1' => 'hashed',
            'recovery_answer_2' => 'hashed',
            'recovery_answer_3' => 'hashed',
        ];
    }
}
