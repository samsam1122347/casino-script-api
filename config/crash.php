<?php

return [

    'engine' => [
        /*
        | When true, tenants with crash_tenant_settings.engine_enabled participate in
        | the scheduled `crash:tick` loop (multiplier/state from DB/broadcast envelope).
        | Legacy demo CLI ticks remain separate unless explicitly disabled elsewhere.
        */
        'enabled' => (bool) env('CRASH_ENGINE_ENABLED', true),

        /*
        | When true, the engine is driven by the long-running `crash:engine`
        | daemon (deploy `crash` container) instead of the once-per-second
        | scheduler. The scheduler floor (1 Hz) is too coarse for smooth
        | client-side interpolation; the daemon ticks at `daemon_hz`.
        | routes/console.php only registers the scheduled `crash:tick` fallback
        | when this is false (keeps local dev working with no extra process).
        */
        'daemon_enabled' => (bool) env('CRASH_ENGINE_DAEMON', false),
        'daemon_hz' => max(1, min(50, (int) env('CRASH_ENGINE_DAEMON_HZ', 15))),

        /*
        | If a `running` round exceeds ln(crash_mult)/growth × margin + grace_seconds, force-bust it.
        | This recovers corrupted clocks, rare float edge cases, and partial DB migrations.
        */
        'running_watchdog_margin' => (float) env('CRASH_RUNNING_WATCHDOG_MARGIN', 15),
        'running_watchdog_grace_seconds' => (float) env('CRASH_RUNNING_WATCHDOG_GRACE', 120),
        'running_watchdog_ceiling_seconds' => (float) env('CRASH_RUNNING_WATCHDOG_CEILING', 86_400),
        // Hold the final busted multiplier before opening the next betting window.
        'closed_hold_seconds' => (float) env('CRASH_CLOSED_HOLD_SECONDS', 2),
    ],

    'defaults' => [
        'house_edge_bp' => (int) env('CRASH_DEFAULT_HOUSE_EDGE_BP', 400),
        'min_bet_minor' => (int) env('CRASH_DEFAULT_MIN_BET_MINOR', 100),
        'max_bet_minor' => (int) env('CRASH_DEFAULT_MAX_BET_MINOR', 1_000_000),
        'max_win_minor_per_round' => (int) env('CRASH_DEFAULT_MAX_WIN_MINOR_PER_ROUND', 500_000_00),
        'max_multiplier_cap' => (float) env('CRASH_DEFAULT_MAX_MULT_CAP', 10000),
        // Aviator-paced: 10s betting window, multiplier reaches 2× at ~12.6s
        // (growth 0.055 → ln(2)/0.055). Counter ticks ~0.001 per frame at 60fps so
        // players feel every 1.01, 1.02, 1.03 increment — the suspense that makes
        // crash games compelling. Rounds average 30-45s total.
        'betting_duration_seconds' => (int) env('CRASH_DEFAULT_BETTING_SECS', 10),
        'growth_per_second' => (float) env('CRASH_DEFAULT_GROWTH_PER_SEC', 0.055),
        'tick_hz' => max(1, (int) env('CRASH_DEFAULT_TICK_HZ', 24)),
        'provably_fair_enabled' => (bool) env('CRASH_DEFAULT_PF', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Broadcast crash ticks immediately (ShouldBroadcastNow)
    |--------------------------------------------------------------------------
    |
    | When false (default), ticks use ShouldBroadcast and require a worker if
    | QUEUE_CONNECTION=redis. When true, each tick runs synchronously on the
    | request/CLI process (handy for quick Soketi checks without queue:work).
    |
    */
    'broadcast_immediately' => env('CRASH_BROADCAST_IMMEDIATE', true),

    /*
    |--------------------------------------------------------------------------
    | Demo round sticky TTL (seconds)
    |--------------------------------------------------------------------------
    |
    | `crash:emit-tick` reuses one external_round_id per tenant until the cache
    | entry expires or `crash:new-round` clears it.
    |
    */
    'demo_round_ttl_seconds' => (int) env('CRASH_DEMO_ROUND_TTL', 86400),

];
