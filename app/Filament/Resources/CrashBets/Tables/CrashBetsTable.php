<?php

namespace App\Filament\Resources\CrashBets\Tables;

use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CrashBetsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('round.tenant.slug')
                    ->label('Tenant')
                    ->badge(),
                TextColumn::make('user.username')
                    ->label('User')
                    ->searchable(),
                TextColumn::make('stake_major')
                    ->label('Stake')
                    ->money('usd')
                    ->alignEnd()
                    ->weight(FontWeight::Bold)
                    ->getStateUsing(fn ($record): float => round(((float) $record->stake_minor) / 100, 2)),
                TextColumn::make('cashout_multiplier')
                    ->numeric(decimalPlaces: 2)
                    ->placeholder('—'),
                TextColumn::make('payout_major')
                    ->label('Payout')
                    ->money('usd')
                    ->alignEnd()
                    ->getStateUsing(function ($record): ?float {
                        return $record->payout_minor !== null
                            ? round(((float) $record->payout_minor) / 100, 2)
                            : null;
                    }),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('round.external_round_id')
                    ->label('Round')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->limit(36),
            ])
            ->defaultSort('created_at', direction: 'desc')
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
