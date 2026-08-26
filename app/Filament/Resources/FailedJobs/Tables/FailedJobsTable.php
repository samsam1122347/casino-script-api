<?php

namespace App\Filament\Resources\FailedJobs\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FailedJobsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->sortable(),
                TextColumn::make('failed_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('queue')
                    ->badge(),
                TextColumn::make('connection')
                    ->limit(32)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('uuid')
                    ->label('UUID')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('exception_preview')
                    ->label('Exception')
                    ->getStateUsing(function ($record): string {
                        $str = (string) $record->exception;

                        return strlen($str) > 140 ? substr($str, 0, 140).'…' : $str;
                    }),
            ])
            ->defaultSort('failed_at', direction: 'desc')
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
