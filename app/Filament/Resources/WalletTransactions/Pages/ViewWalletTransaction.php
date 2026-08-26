<?php

namespace App\Filament\Resources\WalletTransactions\Pages;

use App\Filament\Resources\WalletTransactions\WalletTransactionResource;
use App\Models\WalletTransaction;
use App\Services\Wallet\WalletWithdrawalApprovalService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ViewWalletTransaction extends ViewRecord
{
    protected static string $resource = WalletTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->approveWithdrawalAction(),
            $this->rejectWithdrawalAction(),
        ];
    }

    private function approveWithdrawalAction(): Action
    {
        return Action::make('approveWithdrawal')
            ->label('Approve withdrawal')
            ->color('success')
            ->requiresConfirmation()
            ->visible(fn (): bool => $this->isPendingWithdrawal())
            ->action(function (): void {
                /** @var WalletTransaction $transaction */
                $transaction = $this->record;

                try {
                    app(WalletWithdrawalApprovalService::class)->approve(
                        $transaction,
                        auth('admin')->id() !== null ? (string) auth('admin')->id() : null,
                    );
                } catch (HttpException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title('Withdrawal approved')->success()->send();
                $this->record->refresh();
            });
    }

    private function rejectWithdrawalAction(): Action
    {
        return Action::make('rejectWithdrawal')
            ->label('Reject withdrawal')
            ->color('danger')
            ->modalHeading('Reject withdrawal')
            ->modalSubmitActionLabel('Reject and refund')
            ->schema([
                Textarea::make('reason')
                    ->label('Reason')
                    ->maxLength(500)
                    ->rows(3),
            ])
            ->visible(fn (): bool => $this->isPendingWithdrawal())
            ->action(function (array $data): void {
                /** @var WalletTransaction $transaction */
                $transaction = $this->record;

                try {
                    app(WalletWithdrawalApprovalService::class)->reject(
                        $transaction,
                        auth('admin')->id() !== null ? (string) auth('admin')->id() : null,
                        isset($data['reason']) ? (string) $data['reason'] : null,
                    );
                } catch (HttpException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title('Withdrawal rejected and refunded')->success()->send();
                $this->record->refresh();
            });
    }

    private function isPendingWithdrawal(): bool
    {
        /** @var WalletTransaction $transaction */
        $transaction = $this->record;

        return $transaction->type === 'withdrawal'
            && ($transaction->meta['status'] ?? null) === 'pending'
            && (int) $transaction->amount_minor < 0;
    }
}
