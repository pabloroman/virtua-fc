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
