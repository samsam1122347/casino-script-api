<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $slug = (string) config('gaming.default_tenant_slug', 'crashx');
        $row = DB::table('tenants')->where('slug', $slug)->first();
        if ($row === null || ! isset($row->crypto_assets)) {
            return;
        }

        $raw = $row->crypto_assets;
        $assets = is_string($raw) ? json_decode($raw, true) : null;
        if (! is_array($assets)) {
            return;
        }

        foreach ($assets as $i => $asset) {
            if (! is_array($asset)) {
                continue;
            }
            $assets[$i]['min_deposit_usd'] = 50;
        }

        DB::table('tenants')
            ->where('slug', $slug)
            ->update([
                'crypto_assets' => json_encode(array_values($assets), JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Intentionally no rollback — prior minima were heterogeneous per asset.
    }
};
