<?php

namespace App\Models;

use App\Support\TenantCryptoAssets;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Validation\ValidationException;

#[Fillable(['slug', 'display_name', 'theme', 'tagline', 'crypto_assets'])]
class Tenant extends Model
{
    use HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'theme' => 'array',
            'crypto_assets' => 'array',
        ];
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** @return HasMany<SupportInquiry, $this> */
    public function supportInquiries(): HasMany
    {
        return $this->hasMany(SupportInquiry::class);
    }

    /** @return HasMany<CrashRound, $this> */
    public function crashRounds(): HasMany
    {
        return $this->hasMany(CrashRound::class);
    }

    /** @return HasOne<CrashTenantSettings, $this> */
    public function crashTenantSettings(): HasOne
    {
        return $this->hasOne(CrashTenantSettings::class, 'tenant_id');
    }

    protected static function booted(): void
    {
        static::saving(static function (Tenant $tenant): void {
            $raw = $tenant->crypto_assets ?? [];
            if (! is_array($raw)) {
                $raw = [];
            }

            $assets = TenantCryptoAssets::sanitize($raw);
            $ids = array_column($assets, 'id');
            $uniqueIds = array_unique($ids);

            if (count($ids) !== count($uniqueIds)) {
                throw ValidationException::withMessages([
                    'crypto_assets' => __('Each deposit wallet asset id must be unique.'),
                ]);
            }

            $tenant->crypto_assets = array_values($assets);
        });
    }
}
