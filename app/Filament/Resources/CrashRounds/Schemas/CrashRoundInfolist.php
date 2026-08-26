<?php

namespace App\Filament\Resources\CrashRounds\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CrashRoundInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Round')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('tenant.slug')
                            ->label('Tenant')
                            ->badge()
                            ->placeholder('—'),
                        TextEntry::make('phase')
                            ->badge()
                            ->color(fn (?string $state): string => match ($state) {
                                'running' => 'warning',
                                'busted' => 'danger',
                                'cancelled' => 'success',
                                default => 'gray',
                            }),
                        TextEntry::make('generation_source')
                            ->label('Source')
                            ->badge()
                            ->placeholder('—'),
                        TextEntry::make('external_round_id')
                            ->label('Round UUID')
                            ->copyable()
                            ->columnSpan(2),
                        TextEntry::make('id')
                            ->label('Internal pk')
                            ->copyable(),
                    ]),

                Section::make('Multiplier')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('last_multiplier')
                            ->label('Last multiplier')
                            ->numeric(decimalPlaces: 4)
                            ->suffix('×'),
                        TextEntry::make('crash_point_multiplier')
                            ->label('Crash point')
                            ->numeric(decimalPlaces: 4)
                            ->suffix('×')
                            ->placeholder('— (hidden until busted)'),
                        TextEntry::make('tick_count')
                            ->label('Ticks')
                            ->numeric(),
                        TextEntry::make('growth_per_second_snapshot')
                            ->label('Growth / s (snapshot)')
                            ->numeric(decimalPlaces: 6)
                            ->placeholder('—'),
                        TextEntry::make('max_multiplier_cap_snapshot')
                            ->label('Max multiplier cap (snapshot)')
                            ->numeric(decimalPlaces: 4)
                            ->placeholder('—'),
                    ]),

                Section::make('Timeline')
                    ->schema([
                        Grid::make(3)->schema([
                            TextEntry::make('betting_opens_at')->dateTime()->placeholder('—'),
                            TextEntry::make('betting_closes_at')->dateTime()->placeholder('—'),
                            TextEntry::make('started_running_at')->dateTime()->placeholder('—'),
                            TextEntry::make('started_at')->dateTime()->placeholder('—'),
                            TextEntry::make('ended_at')->dateTime()->placeholder('—'),
                            TextEntry::make('last_tick_broadcast_at')->dateTime()->placeholder('—'),
                            TextEntry::make('created_at')->dateTime()->placeholder('—'),
                            TextEntry::make('updated_at')->dateTime()->placeholder('—'),
                        ]),
                    ]),

                Section::make('Provably fair')
                    ->collapsed()
                    ->schema([
                        TextEntry::make('hash_commitment')
                            ->label('Commitment (SHA-256)')
                            ->copyable()
                            ->placeholder('— (provably fair disabled)'),
                        TextEntry::make('pf_nonce')
                            ->label('Public nonce')
                            ->copyable()
                            ->placeholder('—'),
                        TextEntry::make('revealed_server_seed')
                            ->label('Revealed server seed')
                            ->copyable()
                            ->placeholder('— (revealed only after bust)'),
                    ]),
            ]);
    }
}
