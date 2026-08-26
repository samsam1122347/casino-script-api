<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids as HasUuidPrimaryKeys;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class PersonalAccessToken extends SanctumPersonalAccessToken
{
    use HasUuidPrimaryKeys;

    protected static function booted(): void
    {
        static::creating(function (self $token): void {
            $key = $token->getKeyName();
            if (! filled($token->getKey())) {
                $token->setAttribute($key, (string) Str::uuid7());
            }
        });
    }
}
