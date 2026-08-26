<?php

namespace App\Filament\Resources\SupportInquiries\RelationManagers;

use App\Events\SupportInquiryMessageCreated;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Lists thread messages under a support inquiry. CreateAction posts staff replies; players receive them over
 * {@see SupportInquiryMessageCreated}. The table polls so Filament catches user follow-ups promptly
 * without wiring Filament Echo.
 */
class MessagesRelationManager extends RelationManager
{
    protected static string $relationship = 'messages';

    protected static ?string $title = 'Conversation';

    protected static bool $shouldSkipAuthorization = true;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Textarea::make('body')
                    ->required()
                    ->maxLength(5000)
                    ->rows(6)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->poll('4s')
            ->recordTitleAttribute('body')
            ->columns([
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
                IconColumn::make('is_from_admin')
                    ->label('Staff')
                    ->boolean(),
                TextColumn::make('admin.name')
                    ->label('Agent')
                    ->placeholder('—'),
                TextColumn::make('body')
                    ->wrap()
                    ->limit(400),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Reply')
                    ->modalHeading('Reply to player')
                    ->mutateFormDataUsing(fn (array $data): array => [
                        ...$data,
                        'is_from_admin' => true,
                        'admin_id' => auth('admin')->id(),
                    ]),
            ]);
    }
}
