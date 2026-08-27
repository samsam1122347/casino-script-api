<?php

namespace App\Services\Auth;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

final class LoginUserService
{
    /**
     * @param  array{username: string, password: string}  $data
     */
    public function login(string $tenantSlug, array $data): JsonResponse
    {
        /** @var User|null $user */
        $user = User::query()
            ->whereRaw('LOWER(username) = ?', [mb_strtolower($data['username'])])
            ->whereHas('tenant', fn ($q) => $q->where('slug', $tenantSlug))
            ->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw new HttpResponseException(response()->json(['message' => 'Invalid credentials.'], 401));
        }

        if ($user->is_blocked) {
            throw new HttpResponseException(response()->json([
                'message' => 'Your account has been suspended.',
                'reason' => $user->blocked_reason,
            ], 403));
        }

        $token = $user->createToken('web', ['*'])->plainTextToken;
        $user->load(['tenant:id,slug,display_name', 'wallet']);

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user),
        ]);
    }
}
