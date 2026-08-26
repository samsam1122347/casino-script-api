<?php

namespace App\Filament\Resources\CrashRounds\Pages;

use App\Filament\Resources\CrashRounds\CrashRoundResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditCrashRound extends EditRecord
{
    protected static string $resource = CrashRoundResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
