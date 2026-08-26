<?php

namespace App\Filament\Resources\Tenants\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class TenantInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('id')
                    ->label('ID'),
                TextEntry::make('slug'),
                TextEntry::make('display_name'),
                TextEntry::make('theme')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('tagline')
                    ->placeholder('-'),
                RepeatableEntry::make('crypto_assets')
                    ->label(__('Deposit wallets'))
                    ->placeholder(__('No deposit wallets configured.'))
                    ->table([
                        TableColumn::make(__('Asset id')),
                        TableColumn::make(__('Symbol')),
                        TableColumn::make(__('Name')),
                        TableColumn::make(__('Network')),
                        TableColumn::make(__('Address')),
                        TableColumn::make(__('Min dep. (USD)')),
                        TableColumn::make(__('Min wdr. (USD)')),
                    ])
                    ->schema([
                        TextEntry::make('id'),
                        TextEntry::make('symbol'),
                        TextEntry::make('name'),
                        TextEntry::make('network'),
                        TextEntry::make('address')
                            ->copyable()
                            ->wrap(),
                        TextEntry::make('min_deposit_usd')
                            ->placeholder('—')
                            ->formatStateUsing(fn ($state): string => $state === null || $state === '' ? '—' : '$'.number_format((float) $state, 2)),
                        TextEntry::make('min_withdraw_usd')
                            ->placeholder('—')
                            ->formatStateUsing(fn ($state): string => $state === null || $state === '' ? '—' : '$'.number_format((float) $state, 2)),
                    ])
                    ->columnSpanFull(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
