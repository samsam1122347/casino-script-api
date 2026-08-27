<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CrashGameController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\SiteConfigController;
use App\Http\Controllers\Api\V1\SupportGuestBroadcastAuthController;
use App\Http\Controllers\Api\V1\SupportInquiryController;
use App\Http\Controllers\Api\V1\SupportInquiryFollowUpController;
use App\Http\Controllers\Api\V1\SupportInquiryThreadController;
use App\Http\Controllers\Api\V1\WalletController;
use App\Http\Controllers\Api\V1\WebhookDemoController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', fn () => response()->json([
        'service' => 'crashx-api',
        'status' => 'ok',
        'app' => config('app.name'),
    ]));

    Route::get('/site/config', [SiteConfigController::class, 'show'])
        ->middleware('throttle:public-read');

    Route::get('/site/crash-state', [CrashGameController::class, 'siteState'])
        ->middleware('throttle:public-read');

    Route::post('/auth/register', [AuthController::class, 'register'])
        ->middleware('throttle:auth');
    Route::post('/auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:auth');
    Route::post('/auth/forgot-password/challenge', [AuthController::class, 'forgotPasswordChallenge'])
        ->middleware('throttle:auth');
    Route::post('/auth/forgot-password/verify', [AuthController::class, 'forgotPasswordVerify'])
        ->middleware('throttle:auth');
    Route::post('/auth/forgot-password/reset', [AuthController::class, 'forgotPasswordReset'])
        ->middleware('throttle:auth');

    Route::post('/support/inquiries', SupportInquiryController::class)
        ->middleware('throttle:support-live');

    Route::post('/support/inquiries/messages', SupportInquiryFollowUpController::class)
        ->middleware('throttle:support-live');

    Route::get('/support/inquiries/{inquiry}/thread', SupportInquiryThreadController::class)
        ->middleware('throttle:support-live');

    Route::post('/support/broadcast-auth', SupportGuestBroadcastAuthController::class)
        ->middleware('throttle:broadcast-auth');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::patch('/auth/password', [AuthController::class, 'updatePassword'])
            ->middleware('throttle:password-change');
        Route::get('/profile/summary', [ProfileController::class, 'summary'])
            ->middleware('throttle:profile-read');
        Route::get('/wallet', [WalletController::class, 'show'])
            ->middleware('throttle:wallet-read');
        Route::get('/wallet/transactions', [WalletController::class, 'transactions'])
            ->middleware('throttle:wallet-read');
        Route::post('/wallet/withdraw', [WalletController::class, 'withdraw'])
            ->middleware('throttle:wallet-write');
        Route::post('/wallet/deposit-claim', [WalletController::class, 'claimDeposit'])
            ->middleware('throttle:wallet-write');

        Route::get('/games/crash/state', [CrashGameController::class, 'state'])
            ->middleware('throttle:crash-api');
        Route::post('/games/crash/bets', [CrashGameController::class, 'placeBet'])
            ->middleware('throttle:crash-api');
        Route::post('/games/crash/bets/cancel', [CrashGameController::class, 'cancelBet'])
            ->middleware('throttle:crash-api');
        Route::post('/games/crash/cashouts', [CrashGameController::class, 'cashOut'])
            ->middleware('throttle:crash-api');
        Route::get('/games/crash/my-bets', [CrashGameController::class, 'myBets'])
            ->middleware('throttle:crash-api');
        Route::get('/games/crash/live-bets', [CrashGameController::class, 'liveBets'])
            ->middleware('throttle:crash-api');
    });

    Route::post('/webhooks/demo', WebhookDemoController::class)
        ->middleware(['throttle:webhook-demo', 'webhook.demo']);
});
