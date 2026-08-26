<?php

namespace App\Filament\Resources\WalletTransactions\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class WalletTransactionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('wallet_id')
                    ->relationship('wallet', 'id')
                    ->required(),
                TextInput::make('type')
                    ->required(),
                TextInput::make('amount_minor')
                    ->required()
                    ->numeric(),
                TextInput::make('balance_after_minor')
                    ->required()
                    ->numeric(),
                Textarea::make('meta')
                    ->columnSpanFull(),
            ]);
    }
}
