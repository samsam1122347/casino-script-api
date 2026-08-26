<?php

namespace App\Support;

use App\Models\PersonalAccessToken;
use App\Models\SupportInquiry;
use App\Models\User;
use Illuminate\Http\Request;

final class SupportConversationAccess
{
    public static function bearerUser(Request $request): ?User
    {
        $authenticated = $request->user();
        if ($authenticated instanceof User) {
            return $authenticated;
        }

        $rawBearer = trim((string) $request->bearerToken());
        if ($rawBearer === '') {
            return null;
        }

        $tok = PersonalAccessToken::findToken($rawBearer);
        if ($tok === null || ! isset($tok->tokenable_id)) {
            return null;
        }

        return User::query()->whereKey((string) $tok->tokenable_id)->first();
    }

    /**
     * The player may reopen a thread via Sanctum (owner of the inquiry) or the opaque subscribe token minted when the ticket was opened.
     */
    public static function canAccessConversation(
        Request $request,
        SupportInquiry $inquiry,
        ?string $subscribeToken,
    ): bool {
        $token = ($subscribeToken === null || $subscribeToken === '') ? null : mb_substr(trim($subscribeToken), 0, 128);

        $user = self::bearerUser($request);

        if (
            $user !== null &&
            $inquiry->user_id !== null &&
            (string) $user->tenant_id === (string) $inquiry->tenant_id &&
            (string) $user->getKey() === (string) $inquiry->user_id
        ) {
            return true;
        }

        if (
            $inquiry->subscribe_token !== null &&
            $token !== null &&
            hash_equals((string) $inquiry->subscribe_token, $token)
        ) {
            return true;
        }

        return false;
    }
}
