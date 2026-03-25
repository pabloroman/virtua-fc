<?php

return [
    // Annual operating expenses by reputation level (in cents)
    // Covers: non-playing staff, admin, travel, insurance, legal, etc.
    'operating_expenses' => [
        'elite'        =>  9_500_000_000, // €95M
        'continental'  =>  5_500_000_000, // €55M
        'established'  =>  2_700_000_000, // €27M
        'modest'       =>  1_500_000_000, // €15M
        'local'        =>    600_000_000, // €6M
    ],

    // Commercial revenue per seat per season by reputation level (in cents).
    'commercial_per_seat' => [
        'elite'        => 170_000, // €1,700/seat
        'continental'  =>  87_500, // €875/seat
        'established'  =>  62_500, // €625/seat
        'modest'       =>  45_000, // €450/seat
        'local'        =>  24_000, // €240/seat
    ],

    // Matchday revenue per seat per season by reputation level (in cents).
    'revenue_per_seat' => [
        'elite'        => 70_000, // €700/seat
        'continental'  => 44_000, // €440/seat
        'established'  => 31_000, // €310/seat
        'modest'       => 21_000, // €210/seat
        'local'        =>  9_000, // €90/seat
    ],

    // Operating expense multiplier by competition tier.
    // Tier 1 (La Liga) = full cost, Tier 2 (Segunda) = reduced.
    'operating_expense_tier_multiplier' => [
        1 => 1.0,   // La Liga: full operating expenses
        2 => 0.70,  // Segunda: 70% of base operating expenses
    ],

    // Budget loan configuration.
    // Allows the user to borrow against projected revenue to boost transfer budget.
    'loan' => [
        'max_percentage' => 0.10,       // 10% of projected total revenue
        'interest_rate' => 1500,        // 15% interest (in basis points: 1500 = 15%)
        'minimum' => 50_000_000,        // €500K minimum loan (in cents)
    ],

    // Position-based commercial revenue growth multipliers.
    // Key = max position (inclusive), value = multiplier applied to projected commercial revenue.
    'commercial_growth' => [
        4  => 1.03,  // 1st-4th: +3%
        8  => 1.01,  // 5th-8th: +1%
        14 => 1.00,  // 9th-14th: flat
        17 => 0.98,  // 15th-17th: -2%
        20 => 0.95,  // 18th-20th: -5%
    ],
];
