<?php

namespace App\Filament\Resources\SupportInquiries\Pages;

use App\Filament\Resources\SupportInquiries\SupportInquiryResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditSupportInquiry extends EditRecord
{
    protected static string $resource = SupportInquiryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
