<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Tenant\TenantResolver;
use App\Support\TenantCryptoAssets;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SiteConfigController extends Controller
{
    public function show(Request $request, TenantResolver $tenants): JsonResponse
    {
        try {
            $tenant = $tenants->resolveForPublicConfig($request);
        } catch (\Throwable) {
            return response()->json(['message' => 'No tenant configured.'], 503);
        }

        $theme = $tenant->theme ?? [];

        return response()->json([
            'tenant' => [
                'id' => $tenant->id,
                'slug' => $tenant->slug,
                'display_name' => $tenant->display_name,
            ],
            'tagline' => $tenant->tagline,
            'theme' => $theme,
            'crypto_assets' => TenantCryptoAssets::sanitize($tenant->crypto_assets ?? []),
        ]);
    }
}
