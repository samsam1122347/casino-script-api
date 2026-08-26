<?php

namespace App\Filament\Resources\SupportInquiries;

use App\Filament\Resources\SupportInquiries\Pages\ListSupportInquiries;
use App\Filament\Resources\SupportInquiries\Pages\ViewSupportInquiry;
use App\Filament\Resources\SupportInquiries\RelationManagers\MessagesRelationManager;
use App\Filament\Resources\SupportInquiries\Schemas\SupportInquiryForm;
use App\Filament\Resources\SupportInquiries\Schemas\SupportInquiryInfolist;
use App\Filament\Resources\SupportInquiries\Tables\SupportInquiriesTable;
use App\Models\SupportInquiry;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SupportInquiryResource extends Resource
{
    protected static ?string $model = SupportInquiry::class;

    protected static ?string $navigationLabel = 'Support inbox';

    protected static ?int $navigationSort = 30;

    protected static string|\UnitEnum|null $navigationGroup = 'Support';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    /** @return Builder<SupportInquiry> */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['tenant', 'user']);
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

    public static function getRecordTitleAttribute(): ?string
    {
        return 'id';
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['id', 'email', 'message', 'request_id'];
    }

    public static function form(Schema $schema): Schema
    {
        return SupportInquiryForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SupportInquiryInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SupportInquiriesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            MessagesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSupportInquiries::route('/'),
            'view' => ViewSupportInquiry::route('/{record}'),
        ];
    }
}
