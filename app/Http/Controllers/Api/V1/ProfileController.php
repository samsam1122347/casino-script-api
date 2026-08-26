<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Profile\ProfileSummaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function summary(Request $request, ProfileSummaryService $summary): JsonResponse
    {
        /** @var User $user */
        $user = $request->user()->loadMissing('wallet');

        return $summary->build($user);
    }
}
