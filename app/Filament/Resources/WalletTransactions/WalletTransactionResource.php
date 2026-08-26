<?php

namespace App\Filament\Resources\WalletTransactions;

use App\Filament\Resources\WalletTransactions\Pages\ListWalletTransactions;
use App\Filament\Resources\WalletTransactions\Pages\ViewWalletTransaction;
use App\Filament\Resources\WalletTransactions\Schemas\WalletTransactionForm;
use App\Filament\Resources\WalletTransactions\Schemas\WalletTransactionInfolist;
use App\Filament\Resources\WalletTransactions\Tables\WalletTransactionsTable;
use App\Models\WalletTransaction;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class WalletTransactionResource extends Resource
{
    protected static ?string $model = WalletTransaction::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Finance';

    protected static ?string $navigationLabel = 'Ledger';

    protected static ?int $navigationSort = 14;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyDollar;

    /** @return Builder<WalletTransaction> */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['wallet.user.tenant']);
    }

    // The financial ledger is append-only — mutating rows here would desync wallet balances.
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = WalletTransaction::query()
            ->where('type', 'withdrawal')
            ->where('meta->status', 'pending')
            ->where('amount_minor', '<', 0)
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return __('Pending withdrawals awaiting approval');
    }

    public static function getRecordTitleAttribute(): ?string
    {
        return 'id';
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['id', 'wallet.user.username', 'wallet.user.email'];
    }

    public static function form(Schema $schema): Schema
    {
        return WalletTransactionForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return WalletTransactionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WalletTransactionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWalletTransactions::route('/'),
            'view' => ViewWalletTransaction::route('/{record}'),
        ];
    }
}
