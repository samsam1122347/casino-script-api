<?php

use App\Models\Admin;
use App\Models\SupportInquiry;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
| Private Crash channel (Pusher name: private-tenants.{slug}.crash)
| Authorization: bearer Sanctum user must belong to the tenant slug in the channel.
|
| IDOR: The callback binds the subscribed tenant slug to the authenticated user's tenant;
| spoofing another tenant slug in the channel name will fail authorization here.
*/
Broadcast::channel('tenants.{tenantSlug}.crash', function ($user, string $tenantSlug) {
    if ($user->tenant?->slug !== $tenantSlug) {
        return false;
    }

    return [
        'id' => (string) $user->getKey(),
        'name' => $user->name,
    ];
});

/*
| House-only channel (Pusher: private-tenants.{slug}.crash-ops). Filament/Echo must use
| `/{ADMIN_PANEL_PATH}/broadcasting/auth` (auth:admin), same slug as Filament. Player accounts cannot subscribe.
| Channel options use the admin guard — `retrieveUser()` must not fall back to the default `web` guard (would be null).
*/
Broadcast::channel('tenants.{tenantSlug}.crash-ops', function ($user, string $tenantSlug) {
    if (! $user instanceof Admin) {
        return false;
    }

    return [
        'id' => (string) $user->getKey(),
        'name' => $user->name,
    ];
}, ['guards' => ['admin']]);

/*
| Filament notifications subscribe to private-App.Models.Admin.{id} when
| broadcasting is enabled globally for the panel. Keep this admin-only and
| bind it to the logged-in admin's own ID.
*/
Broadcast::channel('App.Models.Admin.{adminId}', function ($user, string $adminId) {
    if (! $user instanceof Admin) {
        return false;
    }

    return (string) $user->getKey() === $adminId;
}, ['guards' => ['admin']]);

/*
| Player support thread (Pusher: private-support-inquiries.{inquiryId}). Logged-in ticket owners
| use /api/broadcasting/auth. Guest tickets use subscribe_token via /api/v1/support/broadcast-auth.
*/
Broadcast::channel('support-inquiries.{supportInquiryId}', function ($user, string $supportInquiryId) {
    if (! $user instanceof User) {
        return false;
    }

    $inquiry = SupportInquiry::query()
        ->whereKey($supportInquiryId)
        ->where('tenant_id', $user->tenant_id)
        ->first();

    if (! $inquiry || $inquiry->user_id === null) {
        return false;
    }

    if ((string) $inquiry->user_id !== (string) $user->getKey()) {
        return false;
    }

    return [
        'id' => (string) $user->getKey(),
        'name' => $user->name,
    ];
});
