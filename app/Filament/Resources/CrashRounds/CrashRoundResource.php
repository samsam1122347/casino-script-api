<?php

namespace App\Filament\Resources\CrashRounds;

use App\Filament\Resources\CrashRounds\Pages\ListCrashRounds;
use App\Filament\Resources\CrashRounds\Pages\ViewCrashRound;
use App\Filament\Resources\CrashRounds\Schemas\CrashRoundForm;
use App\Filament\Resources\CrashRounds\Schemas\CrashRoundInfolist;
use App\Filament\Resources\CrashRounds\Tables\CrashRoundsTable;
use App\Models\CrashRound;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CrashRoundResource extends Resource
{
    protected static ?string $model = CrashRound::class;

    protected static ?string $navigationLabel = 'Crash rounds';

    protected static ?int $navigationSort = 20;

    protected static string|\UnitEnum|null $navigationGroup = 'Gaming';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBolt;

    /** @return Builder<CrashRound> */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['tenant']);
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
        return CrashRoundForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CrashRoundInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CrashRoundsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCrashRounds::route('/'),
            'view' => ViewCrashRound::route('/{record}'),
        ];
    }
}
