<?php

namespace App\Filament\Resources\CrashTenantSettings\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CrashTenantSettingsForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('tenant_id')
                    ->label('Tenant id')
                    ->disabled()
                    // Non-incrementing primary key — must never be written back on save.
                    ->dehydrated(false),

                TextInput::make('tenant_slug_view')
                    ->label('Tenant slug')
                    ->disabled()
                    ->dehydrated(false),

                TextInput::make('_open_stake_exposure_minor')
                    ->label('Open stake exposure (minor units)')
                    ->disabled()
                    ->dehydrated(false),

                Toggle::make('engine_enabled')
                    ->label('Engine enabled (CRASH_ENGINE_ENABLED must also be true)'),
                Toggle::make('game_enabled')
                    ->label('Game enabled'),
                Toggle::make('game_paused')
                    ->label('Game paused'),
                Toggle::make('provably_fair_enabled')
                    ->label('Provably fair (commitment + seed reveal)'),

                TextInput::make('house_edge_bp')
                    ->numeric()
                    ->required()
                    ->label('House edge (basis points)'),
                TextInput::make('min_bet_minor')
                    ->numeric()
                    ->required(),
                TextInput::make('max_bet_minor')
                    ->numeric()
                    ->required(),
                TextInput::make('max_win_minor_per_round')
                    ->numeric()
                    ->required(),
                TextInput::make('max_multiplier_cap')
                    ->numeric()
                    ->required(),
                TextInput::make('betting_duration_seconds')
                    ->numeric()
                    ->required(),
                TextInput::make('growth_per_second')
                    ->numeric()
                    ->required(),
                TextInput::make('tick_hz')
                    ->numeric()
                    ->required()
                    ->helperText('Broadcasts per second throttle (1–20 typical).'),

                TextInput::make('pending_operator_crash_multiplier')
                    ->numeric()
                    ->label('Pending operator crash multiplier (applied next run start)')
                    ->helperText('Clears automatically after it is consumed.'),

            ]);
    }
}
