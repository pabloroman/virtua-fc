<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Condition (Between-Match Fatigue & Recovery)
    |--------------------------------------------------------------------------
    |
    | Controls how fitness changes between matches. Uses nonlinear recovery:
    | recovery is slow near fitness 100 and faster at lower fitness levels.
    | This creates natural equilibria based on match frequency:
    |
    |   recoveryRate = base × physicalMod × (1 + scaling × (100 − fitness) / 100)
    |
    | Players who play every week stabilize around 88-93 fitness (depending
    | on age and physical ability). Congested periods (2+ matches/week)
    | push fitness into the 70s-80s, forcing squad rotation.
    |
    | Age modifies fitness loss per match (veterans tire more).
    | Physical ability modifies recovery rate (fitter players recover faster).
    |
    */
    'condition' => [
        'base_recovery_per_day' => 1.2,         // recovery rate per day at fitness 100
        'recovery_scaling' => 2.0,              // how much faster recovery is at low fitness
        'max_recovery_days' => 5,               // cap recovery calculation at this many days

        'fitness_loss' => [                     // [min, max] fitness loss per match by position
            'Goalkeeper' => [3, 6],             // GKs barely tire
            'Defender' => [10, 14],              // moderate
            'Midfielder' => [10, 14],           // highest — midfielders run the most
            'Forward' => [10, 14],               // moderate
        ],

        'age_loss_modifier' => [                // multiplier on fitness loss by age bracket (thresholds from PlayerAge)
            'young' => 0.92,                    // <= YOUNG_END: less fatigue per match
            'prime' => 1.0,                     // YOUNG_END+1 to MIN_RETIREMENT_OUTFIELD-1: baseline
            'veteran' => 1.12,                  // >= MIN_RETIREMENT_OUTFIELD: noticeably more
        ],

        'physical_recovery_modifier' => [       // multiplier on base recovery rate
            'high_threshold' => 80,
            'low_threshold' => 60,
            'high' => 1.10,                     // physical >= 80: faster recovery
            'medium' => 1.0,                    // 60-79: baseline
            'low' => 0.90,                      // < 60: slower recovery
        ],

        'ai_rotation_threshold' => 80,          // AI benches players below this fitness
    ],

];