<?php

namespace App\Filament\Resources\CrashRounds\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CrashRoundsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('tenant.slug')
                    ->label('Tenant')
                    ->badge(),
                TextColumn::make('external_round_id')
                    ->label('Round UUID')
                    ->searchable()
                    ->limit(36),
                TextColumn::make('phase')
                    ->badge(),
                TextColumn::make('last_multiplier')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),
                TextColumn::make('tick_count')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('started_at')
                    ->dateTime()
                    ->toggleable(),
                TextColumn::make('ended_at')
                    ->dateTime()
                    ->toggleable(),
            ])
            ->defaultSort('created_at', direction: 'desc')
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
