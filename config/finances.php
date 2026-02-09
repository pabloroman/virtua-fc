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
        'elite'        => 350_000, // €3,500/seat
        'contenders'   => 140_000, // €1,400/seat
        'continental'  => 150_000, // €1,500/seat
        'established'  => 100_000, // €1,000/seat
        'modest'       =>  80_000, // €800/seat
        'professional' =>  50_000, // €500/seat
        'local'        =>  20_000, // €200/seat
    ],

    // Matchday revenue per seat per season by reputation level (in cents).
    'revenue_per_seat' => [
        'elite'        => 130_000, // €1,300/seat
        'contenders'   =>  80_000, // €800/seat
        'continental'  =>  50_000, // €500/seat
        'established'  =>  25_000, // €250/seat
        'modest'       =>  15_000, // €150/seat
        'professional' =>   8_000, // €80/seat
        'local'        =>   4_000, // €40/seat
    ],

    // Position-based commercial revenue growth multipliers.
    // Key = max position (inclusive), value = multiplier applied to projected commercial revenue.
    'commercial_growth' => [
        4  => 1.05,  // 1st-4th: +5%
        8  => 1.02,  // 5th-8th: +2%
        14 => 1.00,  // 9th-14th: flat
        17 => 0.98,  // 15th-17th: -2%
        20 => 0.95,  // 18th-20th: -5%
    ],
];
