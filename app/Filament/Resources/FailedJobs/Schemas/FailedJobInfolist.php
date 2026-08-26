<?php

namespace App\Filament\Resources\FailedJobs\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class FailedJobInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('uuid')
                    ->label('UUID'),
                TextEntry::make('connection')
                    ->columnSpanFull(),
                TextEntry::make('queue')
                    ->columnSpanFull(),
                TextEntry::make('payload')
                    ->columnSpanFull(),
                TextEntry::make('exception')
                    ->columnSpanFull(),
                TextEntry::make('failed_at')
                    ->dateTime(),
            ]);
    }
}
