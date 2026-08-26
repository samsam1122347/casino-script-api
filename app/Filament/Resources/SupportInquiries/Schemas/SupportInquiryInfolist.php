<?php

namespace App\Filament\Resources\SupportInquiries\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class SupportInquiryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('created_at')->dateTime(),
                TextEntry::make('tenant.slug')->label(__('Tenant')),
                TextEntry::make('user.username')
                    ->label(__('User'))
                    ->placeholder('—'),
                TextEntry::make('request_id')->placeholder('—'),
                TextEntry::make('client_message_id')->placeholder('—'),
                TextEntry::make('email')->placeholder('—'),
                TextEntry::make('message')
                    ->columnSpanFull(),
                TextEntry::make('ip_address')->placeholder('—'),
                TextEntry::make('user_agent')
                    ->columnSpanFull()
                    ->placeholder('—'),
            ]);
    }
}
