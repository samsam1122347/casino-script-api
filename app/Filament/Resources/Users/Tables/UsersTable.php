<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('username')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('tenant.slug')
                    ->label('Tenant')
                    ->badge()
                    ->sortable(),
                TextColumn::make('wallet_balance')
                    ->label('Wallet (USD)')
                    ->money('usd')
                    ->alignEnd()
                    ->getStateUsing(fn ($record): float => $record->wallet
                        ? round(((float) $record->wallet->balance_minor) / 100, 2)
                        : 0.0),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }
}
