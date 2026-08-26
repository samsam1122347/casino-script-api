<?php

namespace App\Services\Tenant;

use App\Models\Tenant;
use App\Models\User;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class TenantUserGuard
{
    /**
     * @throws HttpException
     */
    public static function assertUserBelongsToTenant(User $user, Tenant $tenant): void
    {
        if ((string) $user->tenant_id !== (string) $tenant->id) {
            abort(403, 'Tenant mismatch.');
        }
    }
}
