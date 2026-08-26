<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SupportInquiry;
use App\Services\Tenant\TenantResolver;
use App\Support\SupportConversationAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SupportInquiryController extends Controller
{
    public function __invoke(Request $request, TenantResolver $tenants): JsonResponse
    {
        $tenant = $tenants->tenantFromRequest($request);

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'client_message_id' => ['sometimes', 'nullable', 'string', 'max:128'],
        ]);

        $requestId = (string) Str::uuid();
        $subscribeToken = Str::random(64);

        $user = SupportConversationAccess::bearerUser($request);
        $userId = $user?->getKey();

        $clientMid = isset($validated['client_message_id']) && is_string($validated['client_message_id'])
            ? mb_substr(trim($validated['client_message_id']), 0, 128)
            : null;

        $ua = $request->userAgent();
        $truncatedUa = $ua !== null ? mb_substr($ua, 0, 512) : null;

        $inquiry = null;

        DB::transaction(function () use (
            $validated,
            $tenant,
            $userId,
            $requestId,
            $subscribeToken,
            $request,
            $clientMid,
            $truncatedUa,
            &$inquiry
        ): void {
            $rec = SupportInquiry::query()->create([
                'tenant_id' => $tenant->id,
                'user_id' => $userId ?? null,
                'request_id' => $requestId,
                'subscribe_token' => $subscribeToken,
                'message' => (string) $validated['message'],
                'email' => isset($validated['email']) ? (string) $validated['email'] : null,
                'client_message_id' => ($clientMid === '' || $clientMid === null) ? null : $clientMid,
                'ip_address' => $request->ip(),
                'user_agent' => $truncatedUa,
            ]);

            $inquiry = $rec;

            $rec->messages()->create([
                'body' => (string) $validated['message'],
                'is_from_admin' => false,
                'admin_id' => null,
            ]);
        });

        Log::channel('support')->info('support.inquiry', [
            'inquiry_id' => $inquiry?->getKey(),
            'request_id' => $requestId,
            'tenant_slug' => $tenant->slug,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'user_id' => $userId,
            'client_message_id' => ($clientMid === '' || $clientMid === null) ? null : $clientMid,
            'preview' => mb_substr((string) $validated['message'], 0, 240),
            'email_tail' => $this->emailTail(isset($validated['email']) ? (string) $validated['email'] : null),
        ]);

        return response()->json([
            'ok' => true,
            'inquiry_id' => (string) ($inquiry?->getKey() ?? ''),
            'request_id' => $requestId,
            'subscribe_token' => $subscribeToken,
            'accepted_client_message_id' => $clientMid,
        ]);
    }

    private function emailTail(?string $email): ?string
    {
        $e = is_string($email) ? strtolower(trim($email)) : '';
        if ($e === '') {
            return null;
        }
        $at = strpos($e, '@');

        return $at === false ? null : ('…'.mb_substr($e, $at));
    }
}
