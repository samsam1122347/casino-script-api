<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Minimal HMAC stub: expects header X-Signature = hex_hmac_sha256(raw_body, WEBHOOK_DEMO_SECRET).
 */
final class VerifyDemoWebhookSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('services.webhook_demo.secret', '');
        if ($secret === '') {
            return response()->json(['message' => 'Webhook not configured.'], 503);
        }

        $signature = (string) $request->header('X-Signature', '');
        $payload = $request->getContent();
        $expected = hash_hmac('sha256', $payload, $secret);

        if (! hash_equals($expected, $signature)) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        return $next($request);
    }
}
