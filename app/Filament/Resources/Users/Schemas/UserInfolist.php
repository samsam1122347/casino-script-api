<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('username'),
                TextEntry::make('name'),
                TextEntry::make('email')
                    ->label('Email')
                    ->placeholder('—'),
                TextEntry::make('email_verified_at')
                    ->dateTime()
                    ->placeholder('—'),
                TextEntry::make('tenant.display_name')
                    ->label('Tenant'),
                TextEntry::make('wallet_balance')
                    ->label('Wallet balance (USD)')
                    ->state(function ($record): string {
                        if (! $record->wallet) {
                            return '0.00';
                        }

                        return number_format(((float) $record->wallet->balance_minor) / 100, 2);
                    }),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('—'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('—'),
            ]);
    }
}
