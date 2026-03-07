<?php

return [
    // Points awarded at season end based on final league position.
    // Keyed by competition tier (1 = top division, 2 = second division).
    // Each entry maps a max position (inclusive) to a points delta.
    // Positions are checked top-down; the first matching range applies.
    'position_deltas' => [
        1 => [ // Top division (La Liga, Premier League, etc.)
            2  => 40,   // 1st-2nd: title contention
            4  => 30,   // 3rd-4th: Champions League places
            6  => 15,   // 5th-6th: Europa League
            10 => 5,    // 7th-10th: upper mid-table
            14 => 0,    // 11th-14th: mid-table (neutral)
            17 => -10,  // 15th-17th: relegation fight
            99 => -25,  // 18th+: relegated
        ],
        2 => [ // Second division (Segunda, Championship, etc.)
            1  => 15,   // 1st: champion
            2  => 12,   // 2nd: automatic promotion
            6  => 8,    // 3rd-6th: playoff places
            10 => 3,    // 7th-10th: upper mid-table
            16 => 0,    // 11th-16th: mid-table
            99 => -8,   // 17th+: lower table
        ],
    ],

    // Regression pull toward base tier each season (in points).
    // Applied as a drag toward the base tier's starting points.
    // e.g., if a team is above its base, this pulls it down slightly;
    // if below, it pulls it up slightly.
    'regression_rate' => 5,

    // Maximum number of tiers a team can drop below its seeded base.
    'max_tier_drop_below_base' => 2,
];
