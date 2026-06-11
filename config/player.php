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
    |   recoveryRate = base × (1 + scaling × (100 − fitness) / 100)
    |
    | Weekly matches: stabilize around 88-92 starting energy (not full 100),
    | so even normal schedules reward rotation.
    | Congested periods (every 3-4 days): stabilize around 48-55 starting energy,
    | forcing squad rotation for optimal performance.
    |
    | Age modifies energy loss per match (veterans lose more).
    | Physical ability modifies drain rate during the match only; recovery
    | between matches is the same for all players.
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

        'ai_rotation_threshold' => 70,          // AI benches players below this energy

        // Injury layoff: sidelined players lose match sharpness instead of
        // recovering toward 100. Applied per matchday tick, scaled by days
        // elapsed (weekly cadence ≈ -weekly_decay; busy weeks decay less).
        'weekly_decay_when_injured' => 8,

        // Injured players are allowed below the regular floor (40) so long
        // absences (ACL, Achilles) register as deeper rust on the squad page.
        'injured_floor' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Market-Value Corrections (value -> overall fallback)
    |--------------------------------------------------------------------------
    |
    | PlayerValuationService::marketValueToOverallScore() reads a player's
    | overall ability out of their market value. It is used ONLY as a fallback
    | when a player has no imported SoFIFA score (lower leagues, European/
    | international squads, generated players). Market value is a *biased proxy*
    | for current ability: it over-punishes cheap players (a 10x price gap is
    | not a huge skill gap among professionals) and it falls with age faster
    | than skill does (price reflects resale value and contract, not ability).
    |
    | These two knobs each correct one of those distortions. They are NOT a
    | "match SoFIFA" dial — SoFIFA is just one less money-distorted estimate.
    | Each ranges 0.0 (today's raw proxy) to 1.0 (fully corrected); values in
    | between interpolate linearly. Changing them affects future seeds only.
    |
    */
    'market_value_corrections' => [
        // Where the bottom of the ability scale sits.
        // 0 = cheap means weak (current behaviour); 1 = every professional sits
        // on a competent baseline (the low end of the curve is lifted).
        'competence_floor' => 0.7,

        // How much a player's skill resists the age-driven decay in his value.
        // 0 = skill rating fades as an ageing player's market value falls
        // (current behaviour); 1 = skill persists, crediting the ability his
        // price shed to age rather than to decline. Does not affect youth.
        'skill_persistence' => 1.0,

        // How much of a young player's (<23) value-implied ability surfaces in
        // his STARTING overall instead of flowing entirely into potential.
        // A teenager's price signals his ceiling, so it is normally capped
        // (17yo→75 … 22yo→85) with the headroom routed to potential.
        // 0 = hard cap (current behaviour): an elite-priced prospect still
        // starts at the flat cap; 1 = no cap, his full value-implied ability is
        // his starting overall. Only lifts wonderkids priced above the age cap;
        // ordinary youngsters are unaffected.
        'youth_talent_credit' => 0.5,
    ],

];