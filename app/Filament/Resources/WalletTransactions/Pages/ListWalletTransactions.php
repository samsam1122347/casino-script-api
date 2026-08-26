<?php

namespace App\Filament\Resources\WalletTransactions\Pages;

use App\Filament\Resources\WalletTransactions\WalletTransactionResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListWalletTransactions extends ListRecords
{
    protected static string $resource = WalletTransactionResource::class;

    public function getTabs(): array
    {
        return [
            'all' => Tab::make(__('All')),
            'deposits' => Tab::make(__('Deposits'))
                ->modifyQueryUsing(fn ($query) => $query->where('type', 'deposit')),
            'withdrawals' => Tab::make(__('Withdrawals'))
                ->modifyQueryUsing(fn ($query) => $query->where('type', 'withdrawal')),
            'bonuses' => Tab::make(__('Bonuses'))
                ->modifyQueryUsing(fn ($query) => $query->whereIn('type', ['signup_bonus', 'first_deposit_bonus'])),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
