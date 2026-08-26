<?php

namespace App\Filament\Resources\CrashBets;

use App\Filament\Resources\CrashBets\Pages\ListCrashBets;
use App\Filament\Resources\CrashBets\Pages\ViewCrashBet;
use App\Filament\Resources\CrashBets\Schemas\CrashBetForm;
use App\Filament\Resources\CrashBets\Schemas\CrashBetInfolist;
use App\Filament\Resources\CrashBets\Tables\CrashBetsTable;
use App\Models\CrashBet;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CrashBetResource extends Resource
{
    protected static ?string $model = CrashBet::class;

    protected static ?string $navigationLabel = 'Crash bets';

    protected static ?int $navigationSort = 22;

    protected static string|\UnitEnum|null $navigationGroup = 'Gaming';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRocketLaunch;

    /** @return Builder<CrashBet> */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['round.tenant', 'user']);
    }

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

    public static function form(Schema $schema): Schema
    {
        return CrashBetForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CrashBetInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CrashBetsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCrashBets::route('/'),
            'view' => ViewCrashBet::route('/{record}'),
        ];
    }
}
