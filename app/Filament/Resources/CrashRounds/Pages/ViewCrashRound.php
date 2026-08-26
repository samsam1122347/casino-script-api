<?php

namespace App\Filament\Resources\CrashRounds\Pages;

use App\Filament\Resources\CrashRounds\CrashRoundResource;
use Filament\Resources\Pages\ViewRecord;

class ViewCrashRound extends ViewRecord
{
    protected static string $resource = CrashRoundResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
