<?php

namespace App\Filament\Resources\SupportInquiries\Pages;

use App\Filament\Resources\SupportInquiries\SupportInquiryResource;
use Filament\Resources\Pages\ListRecords;

class ListSupportInquiries extends ListRecords
{
    protected static string $resource = SupportInquiryResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
