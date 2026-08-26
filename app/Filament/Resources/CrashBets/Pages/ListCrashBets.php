<?php

namespace App\Filament\Resources\CrashBets\Pages;

use App\Filament\Resources\CrashBets\CrashBetResource;
use Filament\Resources\Pages\ListRecords;

class ListCrashBets extends ListRecords
{
    protected static string $resource = CrashBetResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
