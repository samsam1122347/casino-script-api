<?php

$raw = env('CORS_ALLOWED_ORIGINS');
$origins = ($raw === null || $raw === '')
    ? ['*']
    : array_values(array_filter(array_map('trim', explode(',', $raw))));

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $origins,

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => ! in_array('*', $origins, true),

];
