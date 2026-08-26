<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('username')
                    ->required()
                    ->maxLength(32),
                TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->maxLength(255),
                DateTimePicker::make('email_verified_at'),
                TextInput::make('password')
                    ->password()
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->helperText(__('Leave blank to keep the current password.')),
                Select::make('tenant_id')
                    ->relationship('tenant', 'display_name')
                    ->searchable()
                    ->preload()
                    ->required(),
            ]);
    }
}
