<?php

namespace App\Filament\Resources\CrashBets\Pages;

use App\Filament\Resources\CrashBets\CrashBetResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditCrashBet extends EditRecord
{
    protected static string $resource = CrashBetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
