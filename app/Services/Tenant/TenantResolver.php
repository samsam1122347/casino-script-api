<?php

namespace App\Services\Tenant;

use App\Models\Tenant;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;

final class TenantResolver
{
    public function slugFromRequest(Request $request): string
    {
        $raw = (string) $request->header('X-Tenant-Slug', config('gaming.default_tenant_slug', 'crashx'));
        $slug = strtolower(trim($raw));
        if (strlen($slug) > 64 || ! preg_match('/^[a-z0-9_-]+$/', $slug)) {
            return (string) config('gaming.default_tenant_slug', 'crashx');
        }

        return $slug;
    }

    public function tenantFromSlug(string $slug): Tenant
    {
        return Tenant::query()->where('slug', $slug)->firstOrFail();
    }

    public function tenantFromRequest(Request $request): Tenant
    {
        $slug = $this->slugFromRequest($request);

        $tenant = Tenant::query()->where('slug', $slug)->first()
            ?? Tenant::query()->orderBy('slug')->first();

        if ($tenant instanceof Tenant) {
            return $tenant;
        }

        throw new HttpResponseException(
            response()->json([
                'message' => 'No tenant configured yet. Run: php artisan migrate --seed (or `./vendor/bin/sail artisan migrate --seed`).',
                'code' => 'NO_TENANT_CONFIGURED',
            ], 503),
        );
    }

    public function resolveForPublicConfig(Request $request): Tenant
    {
        $slug = (string) $request->query('tenant', config('gaming.default_tenant_slug', 'crashx'));

        $tenant = Tenant::query()->where('slug', $slug)->first()
            ?? Tenant::query()->orderBy('slug')->first();

        if (! $tenant instanceof Tenant) {
            throw new HttpResponseException(
                response()->json([
                    'message' => 'No tenant configured yet. Run: php artisan migrate --seed.',
                    'code' => 'NO_TENANT_CONFIGURED',
                ], 503),
            );
        }

        return $tenant;
    }
}
