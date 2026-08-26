<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ForgotPasswordRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_resets_password_with_security_answers(): void
    {
        $tenant = $this->createTenant('reset-tenant');
        $user = $this->createRecoverableUser($tenant);
        $user->createToken('old-session', ['*'])->plainTextToken;

        $challenge = $this->withHeader('X-Tenant-Slug', $tenant->slug)
            ->postJson('/api/v1/auth/forgot-password/challenge', [
                'username' => 'Recovery_User',
            ])
            ->assertOk()
            ->assertJsonPath('questions.0.key', 'birth_city')
            ->assertJsonPath('questions.1.key', 'mother_maiden')
            ->assertJsonPath('questions.2.key', 'first_pet')
            ->json();

        $verify = $this->postJson('/api/v1/auth/forgot-password/verify', [
            'challenge_id' => $challenge['challenge_id'],
            'answers' => [
                ['question_key' => 'birth_city', 'answer' => '  PARIS '],
                ['question_key' => 'mother_maiden', 'answer' => 'Smith'],
                ['question_key' => 'first_pet', 'answer' => 'Fluffy'],
            ],
        ])
            ->assertOk()
            ->assertJsonStructure(['reset_token', 'expires_in_seconds'])
            ->json();

        $this->postJson('/api/v1/auth/forgot-password/reset', [
            'reset_token' => $verify['reset_token'],
            'password' => 'new-secret-password',
            'password_confirmation' => 'new-secret-password',
        ])->assertOk();

        $user->refresh();
        $this->assertTrue(Hash::check('new-secret-password', $user->password));
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_rejects_wrong_security_answers(): void
    {
        $tenant = $this->createTenant('wrong-answer');
        $this->createRecoverableUser($tenant);

        $challenge = $this->withHeader('X-Tenant-Slug', $tenant->slug)
            ->postJson('/api/v1/auth/forgot-password/challenge', [
                'username' => 'recovery_user',
            ])
            ->assertOk()
            ->json();

        $this->postJson('/api/v1/auth/forgot-password/verify', [
            'challenge_id' => $challenge['challenge_id'],
            'answers' => [
                ['question_key' => 'birth_city', 'answer' => 'Paris'],
                ['question_key' => 'mother_maiden', 'answer' => 'Wrong'],
                ['question_key' => 'first_pet', 'answer' => 'Fluffy'],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['answers']);
    }

    public function test_unknown_username_gets_generic_recovery_failure(): void
    {
        $tenant = $this->createTenant('unknown-user');

        $this->withHeader('X-Tenant-Slug', $tenant->slug)
            ->postJson('/api/v1/auth/forgot-password/challenge', [
                'username' => 'missing_user',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['answers'])
            ->assertJsonMissing(['username' => ['The selected username is invalid.']]);
    }

    public function test_reset_token_cannot_be_reused(): void
    {
        $tenant = $this->createTenant('reuse-token');
        $user = $this->createRecoverableUser($tenant);

        $challenge = $this->withHeader('X-Tenant-Slug', $tenant->slug)
            ->postJson('/api/v1/auth/forgot-password/challenge', [
                'username' => 'recovery_user',
            ])
            ->assertOk()
            ->json();

        $verify = $this->postJson('/api/v1/auth/forgot-password/verify', [
            'challenge_id' => $challenge['challenge_id'],
            'answers' => [
                ['question_key' => 'birth_city', 'answer' => 'Paris'],
                ['question_key' => 'mother_maiden', 'answer' => 'Smith'],
                ['question_key' => 'first_pet', 'answer' => 'Fluffy'],
            ],
        ])->assertOk()->json();

        $this->postJson('/api/v1/auth/forgot-password/reset', [
            'reset_token' => $verify['reset_token'],
            'password' => 'first-new-password',
            'password_confirmation' => 'first-new-password',
        ])->assertOk();

        $this->postJson('/api/v1/auth/forgot-password/reset', [
            'reset_token' => $verify['reset_token'],
            'password' => 'second-new-password',
            'password_confirmation' => 'second-new-password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reset_token']);

        $user->refresh();
        $this->assertTrue(Hash::check('first-new-password', $user->password));
    }

    private function createTenant(string $slug): Tenant
    {
        return Tenant::query()->create([
            'slug' => $slug,
            'display_name' => 'Test Tenant',
            'crypto_assets' => [],
        ]);
    }

    private function createRecoverableUser(Tenant $tenant): User
    {
        /** @var User $user */
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'username' => 'recovery_user',
            'password' => 'old-secret-password',
            'recovery_question_1' => 'birth_city',
            'recovery_question_2' => 'mother_maiden',
            'recovery_question_3' => 'first_pet',
            'recovery_answer_1' => 'paris',
            'recovery_answer_2' => 'smith',
            'recovery_answer_3' => 'fluffy',
        ]);

        return $user;
    }
}
