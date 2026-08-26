<?php

/**
 * VIP ladder: XP scales from verified deposit volume (minor units → XP via xp_per_deposit_minor).
 *
 * tiers[]: ordered by min_xp ascending. Each row's next_level_bonus_minor is credited when advancing
 * to the next tier (product/marketing hint; payout is enforced elsewhere).
 */
return [
    'xp_per_deposit_minor' => (int) env('VIP_XP_PER_DEPOSIT_MINOR', 100),

    'tiers' => [
        [
            'id' => 'bronze_1',
            'label_key' => 'bronze_1',
            'min_xp' => 0,
            'next_level_bonus_minor' => 5_000,
        ],
        [
            'id' => 'bronze_2',
            'label_key' => 'bronze_2',
            'min_xp' => 600,
            'next_level_bonus_minor' => 7_500,
        ],
        [
            'id' => 'silver_1',
            'label_key' => 'silver_1',
            'min_xp' => 2_500,
            'next_level_bonus_minor' => 10_000,
        ],
        [
            'id' => 'gold_1',
            'label_key' => 'gold_1',
            'min_xp' => 8_000,
            'next_level_bonus_minor' => 0,
        ],
    ],
];
