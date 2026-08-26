<?php

namespace App\Filament\Resources\CrashBets\Pages;

use App\Filament\Resources\CrashBets\CrashBetResource;
use Filament\Resources\Pages\ViewRecord;

class ViewCrashBet extends ViewRecord
{
    protected static string $resource = CrashBetResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
