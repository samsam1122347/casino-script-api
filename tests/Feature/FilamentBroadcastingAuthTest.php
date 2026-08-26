<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class FilamentBroadcastingAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        /*
         * Bootstrap loads routes/channels.php while BROADCAST_CONNECTION=null (see phpunit.xml),
         * so channel auth handlers live on the null broadcaster. Align with production (pusher)
         * then re-require channel definitions onto the resolved Pusher broadcaster.
         */
        config([
            'broadcasting.default' => 'pusher',
            'broadcasting.connections.pusher' => [
                'driver' => 'pusher',
                'key' => 'test-key',
                'secret' => 'test-secret',
                'app_id' => 'test-id',
                'options' => [
                    'cluster' => 'mt1',
                    'host' => '127.0.0.1',
                    'port' => 6001,
                    'scheme' => 'http',
                    'encrypted' => true,
                    'useTLS' => false,
                ],
            ],
        ]);

        app(BroadcastManager::class)->forgetDrivers();
        require base_path('routes/channels.php');
    }

    public function test_crash_ops_broadcasting_auth_resolves_admin_guard_user(): void
    {
        $panelPath = trim(config('admin.panel_path'), '/');
        $admin = Admin::query()->create([
            'name' => 'Ops',
            'username' => 'ops',
            'password' => bcrypt('secret'),
        ]);

        $this->actingAs($admin, 'admin');

        $response = $this->postJson("/{$panelPath}/broadcasting/auth", [
            'socket_id' => '123.456',
            'channel_name' => 'private-tenants.crashx.crash-ops',
        ]);

        $response->assertSuccessful();
        $response->assertJsonStructure(['auth']);
    }

    public function test_filament_notification_broadcasting_auth_resolves_admin_guard_user(): void
    {
        $panelPath = trim(config('admin.panel_path'), '/');
        $admin = Admin::query()->create([
            'name' => 'Ops',
            'username' => 'ops-notifications',
            'password' => bcrypt('secret'),
        ]);

        $this->actingAs($admin, 'admin');

        $response = $this->postJson("/{$panelPath}/broadcasting/auth", [
            'socket_id' => '123.456',
            'channel_name' => 'private-App.Models.Admin.'.$admin->getKey(),
        ]);

        $response->assertSuccessful();
        $response->assertJsonStructure(['auth']);
    }
}
