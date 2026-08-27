<?php

namespace App\Observers;

use App\Models\Admin;
use App\Models\WalletTransaction;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class WalletTransactionObserver implements ShouldHandleEventsAfterCommit
{
    public function created(WalletTransaction $transaction): void
    {
        $type = $transaction->type;
        $amountMajor = round(abs((float) $transaction->amount_minor) / 100, 2);
        
        $title = '';
        $body = '';
        $icon = '';
        $color = '';

        if ($type === 'deposit') {
            if ((int) $transaction->amount_minor === 0 && ($transaction->meta['status'] ?? '') === 'pending') {
                $title = 'New Deposit Claim';
                $body = "A new deposit claim is pending approval.";
                $icon = 'heroicon-o-document-text';
                $color = 'warning';
            } else {
                $title = 'Deposit Credited';
                $body = "A deposit of \${$amountMajor} has been credited.";
                $icon = 'heroicon-o-arrow-down-tray';
                $color = 'success';
            }
        } elseif ($type === 'withdrawal') {
            $title = 'New Withdrawal Request';
            $body = "A withdrawal request for \${$amountMajor} is pending.";
            $icon = 'heroicon-o-arrow-up-tray';
            $color = 'danger';
        } elseif (in_array($type, ['first_deposit_bonus', 'signup_bonus'])) {
            $title = 'Bonus Awarded';
            $body = "A {$type} of \${$amountMajor} was awarded.";
            $icon = 'heroicon-o-gift';
            $color = 'info';
        } else {
            // Unhandled types
            return;
        }

        // Build the Filament notification
        $notification = Notification::make()
            ->title($title)
            ->body($body)
            ->icon($icon)
            ->color($color);

        // Fetch all admins and broadcast the database notification to them via Echo
        $admins = Admin::all();
        if ($admins->isNotEmpty()) {
            $notification->sendToDatabase($admins)->broadcast($admins);
        }
    }
}
