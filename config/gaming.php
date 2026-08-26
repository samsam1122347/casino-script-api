<?php

return [
    /*
    | Wallet transaction `wallet_transactions.type` aliases used by Crash betting:
    | - crash_stake: negative amount_minor when a bet locks stake (meta: crash_round_id, bet id).
    | - crash_payout: positive amount_minor on successful manual/auto cash-out.
    | - crash_refund: positive amount_minor when a round is cancelled or stake is refunded.
    */

    /*
    | One-time welcome credit (minor units, e.g. cents), applied only when the first
    | verified deposit is recorded via WalletDepositCreditService (not at signup).
    */
    'first_deposit_bonus_minor' => (int) env(
        'FIRST_DEPOSIT_BONUS_MINOR',
        env('SIGNUP_BONUS_MINOR', 5000),
    ),

    'default_tenant_slug' => env('DEFAULT_TENANT_SLUG', 'crashx'),

    /*
    | Cap per withdraw request (minor units, cents). Default ≈ USD 50k.
    */
    'max_withdraw_minor_per_request' => (int) env('MAX_WITHDRAW_MINOR_PER_REQUEST', 50000_00),

    /*
    | Fallback minimum withdraw when asset has no min_withdraw_usd (USD major → minor).
    */
    'min_withdraw_minor_default' => (int) env('MIN_WITHDRAW_MINOR_DEFAULT', 1000),
];
