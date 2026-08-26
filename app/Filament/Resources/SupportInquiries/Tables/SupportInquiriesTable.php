<?php

namespace App\Filament\Resources\SupportInquiries\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SupportInquiriesTable
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
                TextColumn::make('user.username')
                    ->label('User')
                    ->placeholder('—'),
                TextColumn::make('message')
                    ->wrap()
                    ->limit(140)
                    ->searchable(),
                TextColumn::make('client_message_id')
                    ->label('Client msg ID')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('request_id')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->limit(36),
                TextColumn::make('ip_address')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', direction: 'desc')
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
