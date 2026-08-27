<?php

namespace App\Providers;

use App\Models\PersonalAccessToken;
use App\Models\SupportInquiryMessage;
use App\Models\WalletTransaction;
use App\Observers\SupportInquiryMessageObserver;
use App\Observers\WalletTransactionObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register before boot so any early User/tokens() usage uses the UUID-capable model (Docker/Octane-safe).
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
    }

    public function boot(): void
    {
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        RateLimiter::for('broadcast-auth', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?? $request->ip());
        });

        RateLimiter::for('public-read', function (Request $request) {
            return Limit::perMinute(120)->by($request->ip());
        });

        RateLimiter::for('webhook-demo', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip());
        });

        RateLimiter::for('support-live', function (Request $request) {
            return Limit::perMinute(20)->by($request->ip());
        });

        RateLimiter::for('wallet-write', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->getAuthIdentifier().':'.$request->ip());
        });

        RateLimiter::for('wallet-read', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->getAuthIdentifier().':'.$request->ip());
        });

        RateLimiter::for('profile-read', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->getAuthIdentifier().':'.$request->ip());
        });

        RateLimiter::for('password-change', function (Request $request) {
            return Limit::perMinute(5)->by($request->user()?->getAuthIdentifier().':'.$request->ip());
        });

        RateLimiter::for('crash-api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->getAuthIdentifier().':'.$request->ip());
        });

        SupportInquiryMessage::observe(SupportInquiryMessageObserver::class);
        WalletTransaction::observe(WalletTransactionObserver::class);

        $this->warnMisconfiguredLocalPusher();
    }

    private function warnMisconfiguredLocalPusher(): void
    {
        if (! $this->app->environment('local')) {
            return;
        }

        if (config('broadcasting.default') !== 'pusher') {
            return;
        }

        $host = (string) config('broadcasting.connections.pusher.options.host', '');
        if ($host !== '' && str_contains($host, '.pusher.com')) {
            Log::warning(
                'BROADCAST_CONNECTION=pusher but PUSHER_HOST resolves to *.pusher.com; use your Soketi hostname (e.g. soketi) in Docker.',
            );
        }
    }
}
