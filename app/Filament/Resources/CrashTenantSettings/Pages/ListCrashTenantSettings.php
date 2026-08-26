<?php

namespace App\Filament\Resources\CrashTenantSettings\Pages;

use App\Filament\Resources\CrashTenantSettings\CrashTenantSettingsResource;
use Filament\Resources\Pages\ListRecords;

class ListCrashTenantSettings extends ListRecords
{
    protected static string $resource = CrashTenantSettingsResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
