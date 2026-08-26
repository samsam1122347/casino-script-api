<?php

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Broadcast::routes([
    'prefix' => trim((string) config('admin.panel_path'), '/'),
    'middleware' => ['web', 'auth:admin', 'throttle:broadcast-auth'],
]);
