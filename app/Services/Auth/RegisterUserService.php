<?php

namespace App\Services\Auth;

use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Wallet\WalletProvisionService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;

final class RegisterUserService
{
    public function __construct(
        private WalletProvisionService $walletProvision,
    ) {}

    /**
     * @param  array{
     *     name?: string|null,
     *     username: string,
     *     password: string,
     *     recovery_question_keys: list<string>,
     *     recovery_answers: list<string>,
     * }  $data
     */
    public function register(string $tenantId, array $data): JsonResponse
    {
        $name = $data['name'] ?? $data['username'];

        if (User::query()->where('tenant_id', $tenantId)->whereRaw('LOWER(username) = ?', [mb_strtolower($data['username'])])->exists()) {
            throw new HttpResponseException(response()->json(['message' => 'Username already taken.'], 422));
        }

        /** @var list<string> $keys */
        $keys = $data['recovery_question_keys'];
        /** @var list<string> $answers */
        $answers = $data['recovery_answers'];

        try {
            $user = User::query()->create([
                'tenant_id' => $tenantId,
                'name' => $name,
                'username' => mb_strtolower($data['username']),
                'email' => null,
                'password' => $data['password'],
                'recovery_question_1' => $keys[0],
                'recovery_question_2' => $keys[1],
                'recovery_question_3' => $keys[2],
                'recovery_answer_1' => self::normalizeRecoveryAnswer($answers[0]),
                'recovery_answer_2' => self::normalizeRecoveryAnswer($answers[1]),
                'recovery_answer_3' => self::normalizeRecoveryAnswer($answers[2]),
            ]);
        } catch (QueryException $e) {
            // Lost the race against a concurrent signup for the same (tenant_id, username).
            if ($e->getCode() === '23000' || $e->getCode() === '23505') {
                throw new HttpResponseException(response()->json(['message' => 'Username already taken.'], 422));
            }
            throw $e;
        }

        $this->walletProvision->provision($user);

        $token = $user->createToken('web', ['*'])->plainTextToken;
        $user->load(['tenant:id,slug,display_name', 'wallet']);

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user),
        ], 201);
    }

    public static function normalizeRecoveryAnswer(string $answer): string
    {
        return mb_strtolower(trim($answer));
    }
}
