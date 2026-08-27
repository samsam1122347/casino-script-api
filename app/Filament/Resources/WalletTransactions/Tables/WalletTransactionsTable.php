<?php

namespace App\Filament\Resources\WalletTransactions\Tables;

use App\Models\WalletTransaction;
use App\Services\Wallet\WalletDepositApprovalService;
use App\Services\Wallet\WalletWithdrawalApprovalService;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpKernel\Exception\HttpException;

class WalletTransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('wallet.user.tenant.slug')
                    ->label('Tenant')
                    ->badge(),
                TextColumn::make('wallet.user.username')
                    ->label('User')
                    ->searchable(),
                TextColumn::make('type')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'deposit' => 'success',
                        'withdrawal' => 'warning',
                        'withdrawal_refund' => 'info',
                        'first_deposit_bonus', 'signup_bonus' => 'info',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('meta.status')
                    ->label('Status')
                    ->badge()
                    ->placeholder('—')
                    ->color(fn (?string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved', 'processed' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('amount_major')
                    ->label('Amount')
                    ->money('usd')
                    ->weight(FontWeight::Bold)
                    ->alignEnd()
                    ->getStateUsing(fn ($record): float => round(((float) $record->amount_minor) / 100, 2)),
                TextColumn::make('balance_major')
                    ->label('Balance after')
                    ->money('usd')
                    ->alignEnd()
                    ->toggleable()
                    ->getStateUsing(fn ($record): float => round(((float) $record->balance_after_minor) / 100, 2)),
                TextColumn::make('id')
                    ->label('ID')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->limit(36),
                TextColumn::make('wallet.user.email')
                    ->label('Email')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),
                TextColumn::make('meta')
                    ->label('Meta')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->getStateUsing(function ($record): ?string {
                        $m = $record->meta ?? null;
                        if (! is_array($m) || $m === []) {
                            return null;
                        }
                        $j = json_encode($m);

                        return is_string($j) ? substr($j, 0, 120).(strlen($j) > 120 ? '…' : '') : null;
                    }),
            ])
            ->defaultSort('created_at', direction: 'desc')
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        'deposit' => __('Deposit'),
                        'withdrawal' => __('Withdrawal'),
                        'withdrawal_refund' => __('Withdrawal refund'),
                        'signup_bonus' => __('Signup bonus'),
                        'first_deposit_bonus' => __('First deposit bonus'),
                    ]),
                SelectFilter::make('meta_status')
                    ->label(__('Status'))
                    ->options([
                        'pending' => __('Pending'),
                        'approved' => __('Approved'),
                        'processed' => __('Processed'),
                        'rejected' => __('Rejected'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;
                        if (! is_string($value) || $value === '') {
                            return $query;
                        }

                        return $query->where('meta->status', $value);
                    }),
            ])
            ->recordActions([
                self::approveDepositAction(),
                self::rejectDepositAction(),
                self::approveWithdrawalAction(),
                self::rejectWithdrawalAction(),
                ViewAction::make(),
            ]);
    }

    private static function approveWithdrawalAction(): Action
    {
        return Action::make('approveWithdrawal')
            ->label('Approve')
            ->color('success')
            ->requiresConfirmation()
            ->visible(fn (WalletTransaction $record): bool => self::isPendingWithdrawal($record))
            ->action(function (WalletTransaction $record): void {
                try {
                    app(WalletWithdrawalApprovalService::class)->approve(
                        $record,
                        auth('admin')->id() !== null ? (string) auth('admin')->id() : null,
                    );
                } catch (HttpException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title('Withdrawal approved')->success()->send();
            });
    }

    private static function rejectWithdrawalAction(): Action
    {
        return Action::make('rejectWithdrawal')
            ->label('Reject')
            ->color('danger')
            ->modalHeading('Reject withdrawal')
            ->modalSubmitActionLabel('Reject and refund')
            ->schema([
                Textarea::make('reason')
                    ->label('Reason')
                    ->maxLength(500)
                    ->rows(3),
            ])
            ->visible(fn (WalletTransaction $record): bool => self::isPendingWithdrawal($record))
            ->action(function (array $data, WalletTransaction $record): void {
                try {
                    app(WalletWithdrawalApprovalService::class)->reject(
                        $record,
                        auth('admin')->id() !== null ? (string) auth('admin')->id() : null,
                        isset($data['reason']) ? (string) $data['reason'] : null,
                    );
                } catch (HttpException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title('Withdrawal rejected and refunded')->success()->send();
            });
    }

    private static function isPendingWithdrawal(WalletTransaction $record): bool
    {
        return $record->type === 'withdrawal'
            && ($record->meta['status'] ?? null) === 'pending'
            && (int) $record->amount_minor < 0;
    }

    private static function isPendingDeposit(WalletTransaction $record): bool
    {
        return $record->type === 'deposit'
            && ($record->meta['status'] ?? null) === 'pending'
            && (int) $record->amount_minor === 0;
    }

    private static function approveDepositAction(): Action
    {
        return Action::make('approveDeposit')
            ->label('Approve')
            ->color('success')
            ->modalHeading('Approve deposit claim')
            ->modalSubmitActionLabel('Approve and credit')
            ->schema([
                TextInput::make('amount_major')
                    ->label('Amount Received (USD)')
                    ->numeric()
                    ->required()
                    ->minValue(0.01)
                    ->step(0.01),
            ])
            ->visible(fn (WalletTransaction $record): bool => self::isPendingDeposit($record))
            ->action(function (array $data, WalletTransaction $record): void {
                try {
                    $amountMinor = (int) round((float) $data['amount_major'] * 100);
                    app(WalletDepositApprovalService::class)->approve(
                        $record,
                        $amountMinor,
                        auth('admin')->id() !== null ? (string) auth('admin')->id() : null,
                    );
                } catch (HttpException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title('Deposit approved and credited')->success()->send();
            });
    }

    private static function rejectDepositAction(): Action
    {
        return Action::make('rejectDeposit')
            ->label('Reject')
            ->color('danger')
            ->modalHeading('Reject deposit claim')
            ->modalSubmitActionLabel('Reject')
            ->schema([
                Textarea::make('reason')
                    ->label('Reason')
                    ->maxLength(500)
                    ->rows(3),
            ])
            ->visible(fn (WalletTransaction $record): bool => self::isPendingDeposit($record))
            ->action(function (array $data, WalletTransaction $record): void {
                try {
                    app(WalletDepositApprovalService::class)->reject(
                        $record,
                        auth('admin')->id() !== null ? (string) auth('admin')->id() : null,
                        isset($data['reason']) ? (string) $data['reason'] : null,
                    );
                } catch (HttpException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title('Deposit claim rejected')->success()->send();
            });
    }
}
