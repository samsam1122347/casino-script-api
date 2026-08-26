<?php

namespace App\Filament\Resources\CrashOperatorActions\Schemas;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CrashOperatorActionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime(),
                TextColumn::make('tenant.slug')
                    ->label('Tenant')
                    ->badge(),
                TextColumn::make('admin.name')
                    ->label('Operator'),
                TextColumn::make('action')->badge()->searchable(),
                TextColumn::make('payload')
                    ->formatStateUsing(fn ($state): string => is_array($state) ? (string) json_encode($state) : '')
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', direction: 'desc');
    }
}
