<?php

/**
 * Minimal Filament panel config — publish full file with `php artisan vendor:publish --tag=filament-config`.
 *
 * @see https://filamentphp.com/docs/panels/notifications#broadcast-notifications-overview
 *
 * Browser Echo must not use `PUSHER_HOST=soketi` / internal port 6001 — that is for Laravel → Soketi HTTP only.
 * Defaults derive the public WS host from `APP_URL` when `PUSHER_HOST` is the Docker service name,
 * and use `wss` on port 443 when the client scheme is https (Caddy `handle /app/*` → soketi).
 */
$broadcastDefault = env('BROADCAST_CONNECTION', 'null');

$filamentEcho = null;

if ($broadcastDefault !== 'null') {
    $serverPusherHost = strtolower((string) env('PUSHER_HOST', ''));
    $appUrlHost = parse_url((string) env('APP_URL', ''), PHP_URL_HOST);
    $appUrlHost = is_string($appUrlHost) ? $appUrlHost : '';

    $wsHost = env('PUBLIC_PUSHER_WS_HOST') ?: env('VITE_PUSHER_HOST');
    if (! is_string($wsHost) || trim($wsHost) === '') {
        $wsHost = ($serverPusherHost === 'soketi' || $serverPusherHost === '127.0.0.1' || $serverPusherHost === 'localhost') && $appUrlHost !== ''
            ? $appUrlHost
            : env('PUSHER_HOST', '127.0.0.1');
    }

    // Do not inherit `PUSHER_SCHEME=http` — that applies to Laravel → Soketi inside Docker, not browsers.
    $clientScheme = strtolower((string) env('PUBLIC_PUSHER_WS_SCHEME', env('VITE_PUSHER_SCHEME', 'https')));
    $forceTls = $clientScheme === 'https';

    $wsPortRaw = env('PUBLIC_PUSHER_WS_PORT') ?? env('VITE_PUSHER_PORT');
    $wsPort = ($wsPortRaw !== null && $wsPortRaw !== '')
        ? (int) $wsPortRaw
        : ($forceTls ? 443 : (int) env('PUSHER_PORT', 6001));

    $filamentEcho = [
        'broadcaster' => 'pusher',
        'key' => env('VITE_PUSHER_APP_KEY', env('PUSHER_APP_KEY')),
        'cluster' => env('VITE_PUSHER_APP_CLUSTER', env('PUSHER_APP_CLUSTER', 'mt1')),
        'wsHost' => $wsHost,
        'wsPort' => $wsPort,
        'wssPort' => $wsPort,
        'authEndpoint' => '/'.trim((string) config('admin.panel_path'), '/').'/broadcasting/auth',
        'disableStats' => true,
        'encrypted' => $forceTls,
        'forceTLS' => $forceTls,
        'enabledTransports' => ['ws', 'wss'],
    ];
}

return [

    'broadcasting' => [
        'echo' => $filamentEcho,
    ],

    'default_filesystem_disk' => env('FILESYSTEM_DISK', 'local'),

    'temporary_file_url_expiry_minutes' => 30,

    'assets_path' => null,

    'cache_path' => base_path('bootstrap/cache/filament'),

    'livewire_loading_delay' => 'default',

    'file_generation' => [
        'flags' => [],
    ],

    'system_route_prefix' => 'filament',
];
