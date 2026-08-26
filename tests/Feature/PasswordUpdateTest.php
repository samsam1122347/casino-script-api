<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_updates_password_when_current_password_correct(): void
    {
        $tenant = Tenant::query()->create([
            'slug' => 'pw-tenant',
            'display_name' => 'T',
            'crypto_assets' => [],
        ]);

        /** @var User $user */
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'password' => 'old-secret-aa',
        ]);

        $t1 = $user->createToken('session-a', ['*'])->plainTextToken;
        $user->createToken('session-b', ['*'])->plainTextToken;
        $this->assertSame(2, $user->tokens()->count());

        $this->flushHeaders();
        $this->withToken($t1)->patchJson(
            '/api/v1/auth/password',
            [
                'current_password' => 'old-secret-aa',
                'password' => 'new-secret-bb-cc',
                'password_confirmation' => 'new-secret-bb-cc',
            ],
        )->assertOk();

        $user->refresh();
        $this->assertTrue(Hash::check('new-secret-bb-cc', $user->password));
        $this->assertSame(2, $user->fresh()->tokens()->count());

        $this->flushHeaders();
        $this->withToken($t1)->getJson('/api/v1/auth/me')->assertOk();
    }

    public function test_rejects_incorrect_current_password(): void
    {
        $tenant = Tenant::query()->create([
            'slug' => 'pw-bad',
            'display_name' => 'T',
            'crypto_assets' => [],
        ]);

        /** @var User $user */
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'password' => 'correct',
        ]);

        $tok = $user->createToken('x', ['*'])->plainTextToken;

        $this->flushHeaders();
        $this->withToken($tok)->patchJson('/api/v1/auth/password', [
            'current_password' => 'wrong',
            'password' => 'newpw-xxxxxxxx',
            'password_confirmation' => 'newpw-xxxxxxxx',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['current_password']);

        $user->refresh();
        $this->assertTrue(Hash::check('correct', $user->password));
    }
}
