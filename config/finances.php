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

    // ── Stadium & Fan Loyalty (Phase 1 plumbing) ───────────────────────

    // Secondary floor on stadium occupancy. With the loyalty formula
    // (0.50 + loyalty/100 × 0.45), the natural minimum is 50% at loyalty 0.
    // These floors only trigger for elite/continental clubs whose loyalty
    // has collapsed below the level implied by their reputation — a marquee
    // brand still draws walk-ups and tourists even when the terraces have
    // thinned. For established and below the formula floor is sufficient.
    'reputation_fill_floor' => [
        'elite'        => 0.65, // kicks in at loyalty_points < 34
        'continental'  => 0.60, // kicks in at loyalty_points < 23
        'established'  => 0.55, // kicks in at loyalty_points < 12
        'modest'       => 0.50, // matches formula floor; effectively a no-op
        'local'        => 0.50,
    ],

    // Per-event nudges applied to loyalty_points by FanLoyaltyUpdateProcessor
    // at season close. Clamped to [0, 100] after summing; also floored at
    // base_loyalty - MAX_LOYALTY_DROP_BELOW_BASE so loyal clubs stay loyal.
    'loyalty_deltas' => [
        'league_title'        =>  5, // Won the top-tier league
        'cup'                 =>  3, // Per cup victory (CupTie winner)
        'top_four_finish'     =>  1, // Finished 1st-4th in any league
        'bottom_three_finish' => -2, // Finished in the bottom three of any league
        'gravity'             => -1, // Applied unconditionally each season
    ],

    // ── AI Team Financial Model ────────────────────────────────────────

    // Transfer spending envelopes per season by reputation level (in cents).
    // Represents the maximum an AI team can spend on incoming transfers per window.
    'ai_transfer_budgets' => [
        'elite'       => 120_000_000_00, // €120M
        'continental' =>  60_000_000_00, // €60M
        'established' =>  25_000_000_00, // €25M
        'modest'      =>  10_000_000_00, // €10M
        'local'       =>   3_000_000_00, // €3M
    ],

    // How much of AI team sale proceeds become available for purchases (0.0-1.0).
    'ai_reinvestment_rate' => 0.70,

    // Estimated total annual revenue by reputation level (in cents).
    // Used to compute AI team financial pressure (wage-to-revenue ratio).
    'ai_estimated_revenue' => [
        'elite'       => 200_000_000_00, // €200M
        'continental' => 100_000_000_00, // €100M
        'established' =>  50_000_000_00, // €50M
        'modest'      =>  25_000_000_00, // €25M
        'local'       =>  10_000_000_00, // €10M
    ],

    // Per-team transfer activity count weights by reputation (summer window).
    // Key = number of transfers, value = weight (higher = more likely).
    'ai_transfer_count_weights_summer' => [
        'elite'       => [2 => 10, 3 => 25, 4 => 30, 5 => 25, 6 => 10],
        'continental' => [2 => 15, 3 => 30, 4 => 30, 5 => 20, 6 => 5],
        'established' => [1 => 15, 2 => 30, 3 => 30, 4 => 15, 5 => 10],
        'modest'      => [1 => 25, 2 => 35, 3 => 25, 4 => 15],
        'local'       => [1 => 40, 2 => 35, 3 => 25],
    ],

    // Per-team transfer activity count weights by reputation (winter window).
    'ai_transfer_count_weights_winter' => [
        'elite'       => [1 => 30, 2 => 40, 3 => 30],
        'continental' => [1 => 35, 2 => 40, 3 => 25],
        'established' => [1 => 50, 2 => 35, 3 => 15],
        'modest'      => [1 => 60, 2 => 30, 3 => 10],
        'local'       => [1 => 70, 2 => 30],
    ],

    // Teams (by slug) that will never sign players via the AI transfer market.
    // When not controlled by the user, these clubs rely exclusively on their
    // synthetic youth academy for squad replenishment. They can still sell
    // players, but cannot buy, sign free agents, or receive loan moves.
    'ai_excluded_from_signing' => [
        'athletic-club',
    ],
];
