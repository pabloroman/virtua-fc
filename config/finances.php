<?php

return [
    // Annual operating expenses by reputation level (in cents)
    // Covers: non-playing staff, admin, travel, insurance, legal, etc.
    'operating_expenses' => [
        'elite'        =>  7_500_000_000, // €75M
        'continental'  =>  4_500_000_000, // €45M
        'established'  =>  2_000_000_000, // €20M
        'modest'       =>  1_100_000_000, // €11M
        'local'        =>    500_000_000, // €5M
    ],

    // Commercial revenue per seat per season by reputation level (in cents).
    'commercial_per_seat' => [
        'elite'        => 220_000, // €2,200/seat
        'continental'  => 110_000, // €1,100/seat
        'established'  =>  80_000, // €800/seat
        'modest'       =>  55_000, // €550/seat
        'local'        =>  30_000, // €300/seat
    ],

    // Matchday revenue per seat per season by reputation level (in cents).
    'revenue_per_seat' => [
        'elite'        => 80_000, // €800/seat
        'continental'  => 50_000, // €500/seat
        'established'  => 35_000, // €350/seat
        'modest'       => 24_000, // €240/seat
        'local'        => 10_000, // €100/seat
    ],

    // Operating expense multiplier by competition tier.
    // Tier 1 (La Liga) = full cost, Tier 2 (Segunda) = reduced.
    'operating_expense_tier_multiplier' => [
        1 => 1.0,   // La Liga: full operating expenses
        2 => 0.70,  // Segunda: 70% of base operating expenses
    ],

    // Position-based commercial revenue growth multipliers.
    // Key = max position (inclusive), value = multiplier applied to projected commercial revenue.
    'commercial_growth' => [
        4  => 1.00,  // 1st-4th: +5%
        8  => 1.00,  // 5th-8th: +2%
        14 => 1.00,  // 9th-14th: flat
        17 => 1.00,  // 15th-17th: -2%
        20 => 1.00,  // 18th-20th: -5%
    ],
];
