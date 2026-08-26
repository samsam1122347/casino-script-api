<?php

namespace App\Services\Wallet;

use App\Models\User;

final class WalletProvisionService
{
    public function provision(User $user): void
    {
        $user->wallet()->create([
            'currency' => 'USD',
            'balance_minor' => 0,
        ]);
    }
}
