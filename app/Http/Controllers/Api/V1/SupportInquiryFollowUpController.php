<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SupportInquiry;
use App\Models\SupportInquiryMessage;
use App\Services\Tenant\TenantResolver;
use App\Support\SupportConversationAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupportInquiryFollowUpController extends Controller
{
    public function __invoke(Request $request, TenantResolver $tenants): JsonResponse
    {
        $tenant = $tenants->tenantFromRequest($request);

        $validated = $request->validate([
            'inquiry_id' => ['required', 'uuid'],
            'message' => ['required', 'string', 'max:2000'],
            'subscribe_token' => ['sometimes', 'nullable', 'string', 'max:128'],
            'client_message_id' => ['sometimes', 'nullable', 'string', 'max:128'],
        ]);

        /** @var SupportInquiry|null $inquiry */
        $inquiry = SupportInquiry::query()
            ->whereKey((string) $validated['inquiry_id'])
            ->where('tenant_id', $tenant->id)
            ->first();

        if (! $inquiry) {
            return response()->json(['message' => 'Inquiry not found.'], 404);
        }

        $subTok = isset($validated['subscribe_token']) && is_string($validated['subscribe_token'])
            ? mb_substr(trim($validated['subscribe_token']), 0, 128)
            : null;

        if (! SupportConversationAccess::canAccessConversation($request, $inquiry, $subTok)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        /** @var SupportInquiryMessage $message */
        $message = $inquiry->messages()->create([
            'body' => (string) $validated['message'],
            'is_from_admin' => false,
            'admin_id' => null,
        ]);

        return response()->json([
            'ok' => true,
            'message_id' => (string) $message->getKey(),
            'accepted_client_message_id' => isset($validated['client_message_id']) && is_string($validated['client_message_id'])
                ? mb_substr(trim($validated['client_message_id']), 0, 128)
                : null,
        ]);
    }
}
