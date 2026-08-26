<?php

namespace App\Filament\Resources\Tenants\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class TenantForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('slug')
                    ->required()
                    ->maxLength(64),
                TextInput::make('display_name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('tagline')
                    ->maxLength(255),
                Textarea::make('theme')
                    ->helperText(__('JSON object for theme tokens. Leave empty or use valid JSON.'))
                    ->columnSpanFull(),
                Section::make(__('Deposit wallets (crypto)'))
                    ->description(__('Addresses and USD limits exposed via site config for player deposit and withdraw flows. The asset id is a stable key (e.g. btc, usdt).'))
                    ->icon(Heroicon::OutlinedWallet)
                    ->schema([
                        Repeater::make('crypto_assets')
                            ->label('')
                            ->addActionLabel(__('Add wallet'))
                            ->reorderable()
                            ->defaultItems(0)
                            ->itemLabel(
                                fn (array $state): ?string => filled($state['symbol'] ?? null)
                                    ? (string) $state['symbol'].' · '.((string) ($state['network'] ?? ''))
                                    : null,
                            )
                            ->schema([
                                TextInput::make('id')
                                    ->label(__('Asset id'))
                                    ->required()
                                    ->maxLength(32)
                                    ->regex('/^[a-z0-9][a-z0-9_-]*$/i')
                                    ->helperText(__('Lowercase slug, max 32 characters.')),
                                TextInput::make('symbol')
                                    ->label(__('Symbol'))
                                    ->required()
                                    ->maxLength(24),
                                TextInput::make('name')
                                    ->label(__('Name'))
                                    ->required()
                                    ->maxLength(128),
                                TextInput::make('network')
                                    ->label(__('Network'))
                                    ->required()
                                    ->maxLength(64),
                                TextInput::make('address')
                                    ->label(__('Deposit address'))
                                    ->required()
                                    ->maxLength(512)
                                    ->columnSpanFull(),
                                TextInput::make('icon_key')
                                    ->label(__('Icon key'))
                                    ->maxLength(64)
                                    ->helperText(__('Optional key for frontend icon mapping.')),
                                TextInput::make('min_deposit_usd')
                                    ->label(__('Min deposit (USD)'))
                                    ->numeric()
                                    ->minValue(0)
                                    ->step(0.01),
                                TextInput::make('min_withdraw_usd')
                                    ->label(__('Min withdraw (USD)'))
                                    ->numeric()
                                    ->minValue(0)
                                    ->step(0.01),
                            ])
                            ->columns(2)
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull()
                    ->collapsible(),
            ]);
    }
}
