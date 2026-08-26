<?php

namespace App\Services\Crash;

use App\Models\CrashOperatorAction;

final class CrashOperatorAudit
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public static function log(int $adminId, string $tenantId, string $action, array $payload = []): void
    {
        CrashOperatorAction::query()->create([
            'admin_id' => $adminId,
            'tenant_id' => $tenantId,
            'action' => $action,
            'payload' => $payload,
        ]);
    }
}
