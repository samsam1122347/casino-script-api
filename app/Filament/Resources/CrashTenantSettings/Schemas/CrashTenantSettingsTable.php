<?php

namespace App\Filament\Resources\CrashTenantSettings\Schemas;

use Filament\Actions\EditAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CrashTenantSettingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tenant.slug')
                    ->label('Tenant slug')
                    ->searchable(),
                TextColumn::make('tenant.display_name')
                    ->label('Display name')
                    ->searchable(),
                TextColumn::make('engine_enabled')
                    ->label('Engine')
                    ->formatStateUsing(fn ($state): string => $state ? 'on' : 'off')
                    ->badge(),
                TextColumn::make('game_enabled')
                    ->label('Game')
                    ->formatStateUsing(fn ($state): string => $state ? 'on' : 'off')
                    ->badge(),
                TextColumn::make('game_paused')
                    ->label('Paused')
                    ->formatStateUsing(fn ($state): string => $state ? 'yes' : 'no')
                    ->badge(),
                TextColumn::make('house_edge_bp')
                    ->label('Edge bp')
                    ->alignEnd()
                    ->weight(FontWeight::Bold),
            ])
            ->defaultSort('tenant_id')
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
