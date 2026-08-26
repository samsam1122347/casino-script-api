<?php

namespace App\Filament\Resources\CrashOperatorActions;

use App\Filament\Resources\CrashOperatorActions\Schemas\CrashOperatorActionsTable;
use App\Models\CrashOperatorAction;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CrashOperatorActionResource extends Resource
{
    protected static ?string $model = CrashOperatorAction::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Gaming';

    protected static ?string $navigationLabel = 'Crash operator log';

    protected static ?int $navigationSort = 24;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
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

    public static function table(Table $table): Table
    {
        return CrashOperatorActionsTable::configure($table);
    }

    /** @return Builder<CrashOperatorAction> */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['admin', 'tenant']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCrashOperatorActions::route('/'),
        ];
    }
}
