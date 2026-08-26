<?php

namespace App\Filament\Resources\CrashRounds\Pages;

use App\Filament\Resources\CrashRounds\CrashRoundResource;
use Filament\Resources\Pages\ListRecords;

class ListCrashRounds extends ListRecords
{
    protected static string $resource = CrashRoundResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
