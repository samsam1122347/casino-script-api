<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SupportInquiry;
use App\Models\SupportInquiryMessage;
use App\Services\Tenant\TenantResolver;
use App\Support\SupportConversationAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupportInquiryThreadController extends Controller
{
    public function __invoke(Request $request, string $inquiryId, TenantResolver $tenants): JsonResponse
    {
        $tenant = $tenants->tenantFromRequest($request);

        /** @var SupportInquiry|null $inquiry */
        $inquiry = SupportInquiry::query()
            ->whereKey($inquiryId)
            ->where('tenant_id', $tenant->id)
            ->first();

        if (! $inquiry) {
            return response()->json(['message' => 'Inquiry not found.'], 404);
        }

        $tokenRaw = $request->query('subscribe_token');
        $subscribeToken =
            $tokenRaw !== null && $tokenRaw !== ''
                ? mb_substr((string) $tokenRaw, 0, 128)
                : null;

        if (! SupportConversationAccess::canAccessConversation($request, $inquiry, $subscribeToken)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $messages = $inquiry->messages()
            ->with(['admin:id,name'])
            ->orderBy('created_at')
            ->get()
            ->map(fn (SupportInquiryMessage $m): array => [
                'id' => (string) $m->getKey(),
                'body' => $m->body,
                'is_from_admin' => (bool) $m->is_from_admin,
                'admin_name' => $m->admin?->name,
                'created_at' => $m->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        $payload = [
            'inquiry_id' => (string) $inquiry->getKey(),
            'request_id' => $inquiry->request_id,
            'initial_message' => $inquiry->message,
            'messages' => $messages,
        ];

        return response()->json($payload);
    }
}
