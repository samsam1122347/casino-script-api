<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Stub for partner webhooks — verify HMAC before trusting payload (see middleware).
 */
class WebhookDemoController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'received' => $request->all(),
        ]);
    }
}
