<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->adjustBalanceAction(),
            ViewAction::make(),
        ];
    }

    /** Credit/debit a player's wallet and write a matching ledger entry (never edits balance_minor raw). */
    private function adjustBalanceAction(): Action
    {
        return Action::make('adjustBalance')
            ->label('Adjust balance')
            ->icon(Heroicon::OutlinedCurrencyDollar)
            ->color('warning')
            ->modalHeading('Adjust wallet balance')
            ->modalDescription('Credits (positive) or debits (negative) the wallet and writes an admin_adjustment ledger row.')
            ->modalSubmitActionLabel('Apply adjustment')
            ->schema([
                TextInput::make('amount')
                    ->label('Amount (USD)')
                    ->helperText('Negative deducts, e.g. -25.00')
                    ->numeric()
                    ->required(),
                Textarea::make('reason')
                    ->label('Reason')
                    ->required()
                    ->maxLength(255)
                    ->rows(2),
            ])
            ->action(function (array $data): void {
                /** @var User $user */
                $user = $this->record;
                $amountMinor = (int) round(((float) $data['amount']) * 100);

                if ($amountMinor === 0) {
                    Notification::make()->title('Amount must be a non-zero value.')->danger()->send();

                    return;
                }

                $adminId = auth('admin')->id();
                $reason = trim((string) $data['reason']);

                try {
                    $balanceAfter = DB::transaction(function () use ($user, $amountMinor, $reason, $adminId): int {
                        /** @var Wallet $wallet */
                        $wallet = Wallet::query()
                            ->where('user_id', $user->getKey())
                            ->lockForUpdate()
                            ->first()
                            ?? Wallet::query()->create([
                                'user_id' => $user->getKey(),
                                'currency' => 'USD',
                                'balance_minor' => 0,
                            ]);

                        $newBalance = (int) $wallet->balance_minor + $amountMinor;
                        if ($newBalance < 0) {
                            abort(422, 'Adjustment would put the wallet below zero.');
                        }

                        $wallet->balance_minor = $newBalance;
                        $wallet->save();

                        WalletTransaction::query()->create([
                            'wallet_id' => $wallet->id,
                            'type' => 'admin_adjustment',
                            'amount_minor' => $amountMinor,
                            'balance_after_minor' => $newBalance,
                            'meta' => [
                                'reason' => $reason,
                                'admin_id' => $adminId !== null ? (string) $adminId : null,
                            ],
                        ]);

                        return $newBalance;
                    });
                } catch (HttpException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()
                    ->title('Balance adjusted')
                    ->body('New balance: $'.number_format($balanceAfter / 100, 2))
                    ->success()
                    ->send();
            });
    }
}
