<?php

namespace App\Filament\Resources\CrashTenantSettings;

use App\Filament\Resources\CrashTenantSettings\Schemas\CrashTenantSettingsForm;
use App\Filament\Resources\CrashTenantSettings\Schemas\CrashTenantSettingsTable;
use App\Models\CrashTenantSettings;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CrashTenantSettingsResource extends Resource
{
    protected static ?string $model = CrashTenantSettings::class;

    /** @return Builder<CrashTenantSettings> */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['tenant']);
    }

    protected static string|\UnitEnum|null $navigationGroup = 'Gaming';

    protected static ?string $navigationLabel = 'Crash settings';

    protected static ?string $recordTitleAttribute = 'tenant_id';

    protected static ?int $navigationSort = 19;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    public static function form(Schema $schema): Schema
    {
        return CrashTenantSettingsForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CrashTenantSettingsTable::configure($table);
    }

    public static function canCreate(): bool
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

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCrashTenantSettings::route('/'),
            'edit' => Pages\EditCrashTenantSettings::route('/{record}/edit'),
        ];
    }
}
