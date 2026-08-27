<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Table;
use Filament\Actions\DeleteAction;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                IconColumn::make('is_blocked')
                    ->label('Status')
                    ->icon(fn (string $state): string => match ($state) {
                        '1' => 'heroicon-o-no-symbol',
                        '0' => 'heroicon-o-check-circle',
                        default => 'heroicon-o-check-circle',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        '1' => 'danger',
                        '0' => 'success',
                        default => 'success',
                    })
                    ->boolean(),
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
                Action::make('block')
                    ->label(fn ($record) => $record->is_blocked ? 'Unblock' : 'Block')
                    ->color(fn ($record) => $record->is_blocked ? 'success' : 'danger')
                    ->icon(fn ($record) => $record->is_blocked ? 'heroicon-o-check-circle' : 'heroicon-o-no-symbol')
                    ->requiresConfirmation()
                    ->form(fn ($record) => $record->is_blocked ? [] : [
                        TextInput::make('blocked_reason')
                            ->label('Reason for blocking')
                            ->required(),
                    ])
                    ->action(function ($record, array $data) {
                        if ($record->is_blocked) {
                            $record->update(['is_blocked' => false, 'blocked_reason' => null]);
                        } else {
                            $record->update(['is_blocked' => true, 'blocked_reason' => $data['blocked_reason'] ?? null]);
                            $record->tokens()->delete();
                        }
                    }),
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
