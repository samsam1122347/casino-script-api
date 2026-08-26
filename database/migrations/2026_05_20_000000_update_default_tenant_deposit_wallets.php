<?php

use Database\Seeders\TenantSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('tenants')
            ->where('slug', (string) config('gaming.default_tenant_slug', 'crashx'))
            ->update([
                'crypto_assets' => json_encode(TenantSeeder::depositWallets(), JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Intentionally do not restore old placeholder deposit addresses.
    }
};
