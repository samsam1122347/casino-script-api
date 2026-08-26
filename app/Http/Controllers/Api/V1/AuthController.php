<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\PasswordUpdateRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Auth\LoginUserService;
use App\Services\Auth\RegisterUserService;
use App\Services\Tenant\TenantResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function register(Request $request, RegisterUserService $register, TenantResolver $tenants): JsonResponse
    {
        $allowedKeys = config('recovery_questions.keys', []);

        $validated = $request->validate([
            'username' => ['required', 'string', 'regex:/^[a-zA-Z0-9_]{3,24}$/', 'max:24'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'recovery_question_keys' => ['required', 'array', 'size:3'],
            'recovery_question_keys.*' => ['required', 'string', Rule::in($allowedKeys)],
            'recovery_answers' => ['required', 'array', 'size:3'],
            'recovery_answers.*' => ['required', 'string', 'min:2', 'max:128'],
        ]);

        $keys = $validated['recovery_question_keys'];
        if (count(array_unique($keys)) !== 3) {
            throw ValidationException::withMessages([
                'recovery_question_keys' => ['Choose three different security questions.'],
            ]);
        }

        $validated['username'] = mb_strtolower($validated['username']);

        $tenant = $tenants->tenantFromRequest($request);

        return $register->register($tenant->id, $validated);
    }

    public function login(Request $request, LoginUserService $login, TenantResolver $tenants): JsonResponse
    {
        $validated = $request->validate([
            'username' => ['required', 'string', 'max:24'],
            'password' => ['required', 'string'],
        ]);

        return $login->login($tenants->slugFromRequest($request), $validated);
    }

    public function forgotPasswordChallenge(Request $request, TenantResolver $tenants): JsonResponse
    {
        $validated = $request->validate([
            'username' => ['required', 'string', 'max:24'],
        ]);

        $user = $this->findRecoveryUser($request, $tenants, $validated['username']);

        if (! $user || ! $this->hasRecoveryQuestions($user)) {
            throw $this->recoveryFailed();
        }

        $challengeId = (string) Str::uuid();

        Cache::put(
            $this->forgotPasswordChallengeCacheKey($challengeId),
            ['user_id' => $user->id],
            now()->addMinutes(15),
        );

        return response()->json([
            'challenge_id' => $challengeId,
            'questions' => [
                ['key' => $user->recovery_question_1],
                ['key' => $user->recovery_question_2],
                ['key' => $user->recovery_question_3],
            ],
            'expires_in_seconds' => 15 * 60,
        ]);
    }

    public function forgotPasswordVerify(Request $request): JsonResponse
    {
        $allowedKeys = config('recovery_questions.keys', []);

        $validated = $request->validate([
            'challenge_id' => ['required', 'uuid'],
            'answers' => ['required', 'array', 'size:3'],
            'answers.*.question_key' => ['required', 'string', Rule::in($allowedKeys)],
            'answers.*.answer' => ['required', 'string', 'min:2', 'max:128'],
        ]);

        $challengeKey = $this->forgotPasswordChallengeCacheKey($validated['challenge_id']);
        /** @var array{user_id: string}|null $payload */
        $payload = Cache::get($challengeKey);

        if (! is_array($payload) || ! isset($payload['user_id'])) {
            throw $this->recoveryFailed();
        }

        /** @var User|null $user */
        $user = User::query()->find($payload['user_id']);

        if (! $user || ! $this->hasRecoveryQuestions($user)) {
            Cache::forget($challengeKey);

            throw $this->recoveryFailed();
        }

        /** @var list<array{question_key: string, answer: string}> $answers */
        $answers = $validated['answers'];

        if (! $this->recoveryAnswersMatch($user, $answers)) {
            throw $this->recoveryFailed();
        }

        Cache::forget($challengeKey);

        $resetToken = Str::random(64);
        Cache::put(
            $this->forgotPasswordResetCacheKey($resetToken),
            ['user_id' => $user->id],
            now()->addMinutes(15),
        );

        return response()->json([
            'reset_token' => $resetToken,
            'expires_in_seconds' => 15 * 60,
        ]);
    }

    public function forgotPasswordReset(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reset_token' => ['required', 'string', 'size:64'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $resetKey = $this->forgotPasswordResetCacheKey($validated['reset_token']);
        /** @var array{user_id: string}|null $payload */
        $payload = Cache::get($resetKey);

        if (! is_array($payload) || ! isset($payload['user_id'])) {
            throw $this->recoveryFailed('reset_token');
        }

        /** @var User|null $user */
        $user = User::query()->find($payload['user_id']);

        if (! $user) {
            Cache::forget($resetKey);

            throw $this->recoveryFailed('reset_token');
        }

        $user->forceFill([
            'password' => $validated['password'],
        ])->save();

        DB::table('personal_access_tokens')
            ->where('tokenable_type', $user->getMorphClass())
            ->where('tokenable_id', $user->getKey())
            ->delete();
        Cache::forget($resetKey);

        return response()->json([
            'ok' => true,
            'message' => 'Password has been reset.',
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $token = $user->currentAccessToken();
        if ($token instanceof PersonalAccessToken) {
            DB::table('personal_access_tokens')
                ->where('id', $token->getKey())
                ->delete();
        }

        return response()->json(['ok' => true]);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user()->load(['tenant:id,slug,display_name', 'wallet']);

        return response()->json(['user' => new UserResource($user)]);
    }

    public function updatePassword(PasswordUpdateRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validated();

        $user->forceFill([
            'password' => $validated['password'],
        ])->save();

        return response()->json(['ok' => true]);
    }

    private function findRecoveryUser(Request $request, TenantResolver $tenants, string $username): ?User
    {
        $tenant = $tenants->tenantFromRequest($request);

        /** @var User|null $user */
        $user = User::query()
            ->where('tenant_id', $tenant->id)
            ->whereRaw('LOWER(username) = ?', [mb_strtolower($username)])
            ->first();

        return $user;
    }

    private function hasRecoveryQuestions(User $user): bool
    {
        return filled($user->recovery_question_1)
            && filled($user->recovery_question_2)
            && filled($user->recovery_question_3)
            && filled($user->recovery_answer_1)
            && filled($user->recovery_answer_2)
            && filled($user->recovery_answer_3);
    }

    /**
     * @param  list<array{question_key: string, answer: string}>  $answers
     */
    private function recoveryAnswersMatch(User $user, array $answers): bool
    {
        $expected = [
            $user->recovery_question_1 => $user->recovery_answer_1,
            $user->recovery_question_2 => $user->recovery_answer_2,
            $user->recovery_question_3 => $user->recovery_answer_3,
        ];

        $provided = [];
        foreach ($answers as $answer) {
            $provided[$answer['question_key']] = RegisterUserService::normalizeRecoveryAnswer($answer['answer']);
        }

        if (array_keys($expected) !== array_keys($provided)) {
            ksort($expected);
            ksort($provided);
        }

        if (array_keys($expected) !== array_keys($provided)) {
            return false;
        }

        foreach ($expected as $key => $hash) {
            if (! is_string($hash) || ! Hash::check($provided[$key], $hash)) {
                return false;
            }
        }

        return true;
    }

    private function forgotPasswordChallengeCacheKey(string $challengeId): string
    {
        return 'auth:forgot-password:challenge:'.$challengeId;
    }

    private function forgotPasswordResetCacheKey(string $resetToken): string
    {
        return 'auth:forgot-password:reset:'.hash('sha256', $resetToken);
    }

    private function recoveryFailed(string $field = 'answers'): ValidationException
    {
        return ValidationException::withMessages([
            $field => ['The recovery information could not be verified. Please start again.'],
        ]);
    }
}
