<?php

namespace App\Filament\Resources\WalletTransactions\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class WalletTransactionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('id')
                    ->label('ID'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('—'),
                TextEntry::make('wallet.user.tenant.slug')
                    ->label(__('Tenant')),
                TextEntry::make('wallet.user.username')
                    ->label(__('User')),
                TextEntry::make('wallet.user.email')
                    ->label(__('Email'))
                    ->placeholder('—'),
                TextEntry::make('type')
                    ->badge(),
                TextEntry::make('meta.status')
                    ->label(__('Status'))
                    ->badge()
                    ->placeholder('—'),
                TextEntry::make('amount_major')
                    ->label(__('Amount'))
                    ->state(fn ($record): string => number_format(((float) $record->amount_minor) / 100, 2)),
                TextEntry::make('balance_after_major')
                    ->label(__('Balance after'))
                    ->state(fn ($record): string => number_format(((float) $record->balance_after_minor) / 100, 2)),
                TextEntry::make('meta')
                    ->placeholder('—')
                    ->columnSpanFull(),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('—'),
            ]);
    }
}
