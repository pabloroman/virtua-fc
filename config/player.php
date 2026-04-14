<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Condition (Unified Energy Recovery)
    |--------------------------------------------------------------------------
    |
    | Controls how energy (fitness) recovers between matches. Uses nonlinear
    | recovery: faster when far below 100, slower near the top.
    |
    | Match energy loss is calculated by EnergyCalculator (proportional to
    | starting energy, based on physical ability, age, and GK multiplier).
    | A typical outfielder at fitness 100 ends a match at ~60 energy.
    |
    | Recovery formula:
    |   recoveryRate = base × physicalMod × (1 + scaling × (100 − fitness) / 100)
    |
    | Weekly matches: stabilize around 88-92 starting energy (not full 100),
    | so even normal schedules reward rotation.
    | Congested periods (every 3-4 days): stabilize around 48-55 starting energy,
    | forcing squad rotation for optimal performance.
    |
    | Age modifies energy loss per match (veterans lose more).
    | Physical ability modifies both drain rate AND recovery speed.
    |
    */
    'condition' => [
        'base_recovery_per_day' => 4.0,         // recovery rate per day at fitness 100
        'recovery_scaling' => 0.6,              // how much faster recovery is at low fitness
        'max_recovery_days' => 7,               // cap recovery calculation at this many days

        'age_loss_modifier' => [                // multiplier on energy loss by age bracket (thresholds from PlayerAge)
            'young' => 0.92,                    // <= YOUNG_END: less fatigue per match
            'prime' => 1.0,                     // YOUNG_END+1 to MIN_RETIREMENT_OUTFIELD-1: baseline
            'veteran' => 1.12,                  // >= MIN_RETIREMENT_OUTFIELD: noticeably more
        ],

        'physical_recovery_modifier' => [       // multiplier on base recovery rate
            'high_threshold' => 80,
            'low_threshold' => 60,
            'high' => 1.05,                     // physical >= 80: faster recovery
            'medium' => 1.0,                    // 60-79: baseline
            'low' => 0.95,                      // < 60: slower recovery
        ],

        'ai_rotation_threshold' => 70,          // AI benches players below this energy
    ],

];