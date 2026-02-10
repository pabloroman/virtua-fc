<?php

return [
    // Annual operating expenses by reputation level (in cents)
    // Covers: non-playing staff, admin, travel, insurance, legal, etc.
    'operating_expenses' => [
        'elite'        => 10_000_000_000, // €100M
        'contenders'   =>  5_000_000_000, // €50M
        'continental'  =>  3_500_000_000, // €35M
        'established'  =>  2_500_000_000, // €25M
        'modest'       =>  1_500_000_000, // €15M
        'professional' =>    800_000_000, // €8M
        'local'        =>    400_000_000, // €4M
    ],

    // Commercial revenue per seat per season by reputation level (in cents).
    'commercial_per_seat' => [
        'elite'        => 170_000, // €1,700/seat
        'contenders'   => 150_000, // €1,500/seat
        'continental'  => 120_000, // €1,200/seat
        'established'  => 100_000, // €1,000/seat
        'modest'       =>  80_000, // €800/seat
        'professional' =>  50_000, // €500/seat
        'local'        =>  30_000, // €300/seat
    ],

    // Matchday revenue per seat per season by reputation level (in cents).
    'revenue_per_seat' => [
        'elite'        => 80_000, // €800/seat
        'contenders'   => 60_000, // €600/seat
        'continental'  => 42_500, // €425/seat
        'established'  => 35_000, // €350/seat
        'modest'       => 27_500, // €275/seat
        'professional' => 20_000, // €200/seat
        'local'        => 10_000, // €100/seat
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
