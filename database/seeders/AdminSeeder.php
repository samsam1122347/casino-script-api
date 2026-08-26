<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Solitary operator account for Filament. Set ADMIN_PASSWORD in .env before seeding.
 */
class AdminSeeder extends Seeder
{
    public const ADMIN_USERNAME = 'crashx_internal_admin';

    public function run(): void
    {
        $password = (string) env('ADMIN_PASSWORD', '');
        if ($password === '') {
            throw new RuntimeException(
                'AdminSeeder requires ADMIN_PASSWORD in .env (e.g. ADMIN_PASSWORD=your-secure-password php artisan db:seed --class=AdminSeeder).',
            );
        }

        Admin::query()->updateOrCreate(
            ['username' => self::ADMIN_USERNAME],
            [
                'name' => 'CrashX Console',
                'password' => Hash::make($password),
            ]
        );
    }
}
