<?php

namespace Tests\Feature;

use App\Filament\Pages\CrashOperationsConsole;
use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FilamentAdminRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_panel_login_returns_success(): void
    {
        $path = trim((string) config('admin.panel_path'), '/');

        $this->get('/'.$path.'/login')
            ->assertSuccessful();
    }

    public function test_crash_live_ops_page_is_available_to_admins(): void
    {
        $admin = Admin::query()->create([
            'name' => 'Admin',
            'username' => 'admin',
            'password' => 'secret',
        ]);

        $this->actingAs($admin, 'admin')
            ->get(CrashOperationsConsole::getUrl())
            ->assertSuccessful()
            ->assertSee('Crash live ops');
    }
}
