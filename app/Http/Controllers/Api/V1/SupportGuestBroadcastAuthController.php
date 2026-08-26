<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SupportInquiry;
use App\Services\Tenant\TenantResolver;
use Illuminate\Broadcasting\Broadcasters\Broadcaster;
use Illuminate\Broadcasting\Broadcasters\PusherBroadcaster;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Pusher/Echo authorization for visitors without Sanctum: validates {@see SupportInquiry::$subscribe_token}
 * and binds the subscribed tenant via {@see TenantResolver::$slugFromRequest()}.
 *
 * Laravel's default {@code /api/broadcasting/auth} rejects private subscriptions when no logged-in user exists;
 * this endpoint signs the channel directly after validating the ticket token (see portal BFF route).
 */
class SupportGuestBroadcastAuthController extends Controller
{
    public function __invoke(Request $request, TenantResolver $tenants, BroadcastManager $broadcastManager): mixed
    {
        $validated = $request->validate([
            'channel_name' => ['required', 'string', 'max:255'],
            'socket_id' => ['required', 'string', 'max:128'],
            'subscribe_token' => ['required', 'string', 'max:128'],
        ]);

        $channelName = (string) $validated['channel_name'];
        $prefix = 'private-support-inquiries.';
        if (! Str::startsWith($channelName, $prefix)) {
            throw new AccessDeniedHttpException('Unsupported broadcast channel.');
        }

        $inquiryId = (string) substr($channelName, strlen($prefix));
        $token = $validated['subscribe_token'];

        $tenant = $tenants->tenantFromRequest($request);

        $query = SupportInquiry::query()
            ->whereKey($inquiryId)
            ->where('tenant_id', $tenant->id)
            ->whereNotNull('subscribe_token');

        $inquiry = $query->first();
        if (! $inquiry || ! hash_equals((string) $inquiry->subscribe_token, $token)) {
            throw new AccessDeniedHttpException('Subscription not allowed.');
        }

        /** @var Broadcaster $broadcaster */
        $broadcaster = $broadcastManager->driver();

        if (! $broadcaster instanceof PusherBroadcaster) {
            abort(503, 'Realtime is not configured for this environment.');
        }

        $response = $broadcaster->validAuthenticationResponse($request, true);

        if ($response === null || $response === '') {
            abort(503, 'Realtime bridge returned an empty signing response.');
        }

        return response()->json($response);
    }
}
