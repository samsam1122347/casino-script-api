<?php

namespace App\Filament\Resources\SupportInquiries\Pages;

use App\Filament\Resources\SupportInquiries\SupportInquiryResource;
use App\Models\SupportInquiry;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewSupportInquiry extends ViewRecord
{
    protected static string $resource = SupportInquiryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reply')
                ->label('Reply')
                ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                ->color('primary')
                ->modalHeading('Reply to player')
                ->modalSubmitActionLabel('Send reply')
                ->schema([
                    Textarea::make('body')
                        ->label('Message')
                        ->required()
                        ->maxLength(5000)
                        ->rows(6),
                ])
                ->action(function (array $data): void {
                    /** @var SupportInquiry $inquiry */
                    $inquiry = $this->record;

                    $inquiry->messages()->create([
                        'body' => trim((string) $data['body']),
                        'is_from_admin' => true,
                        'admin_id' => auth('admin')->id(),
                    ]);

                    Notification::make()
                        ->title('Reply sent')
                        ->success()
                        ->send();
                }),
        ];
    }
}
