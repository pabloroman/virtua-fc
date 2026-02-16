<?php

/**
 * Match Simulation Configuration
 *
 * Adjust these values to tune how matches are simulated.
 * After changing values, clear config cache: php artisan config:clear
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Expected Goals (Ratio-Based Formula)
    |--------------------------------------------------------------------------
    |
    | The xG formula uses strength RATIOS rather than shares:
    |
    |   homeXG = (strengthRatio ^ ratioExponent) × baseGoals + homeAdvantage
    |   awayXG = (1/strengthRatio ^ ratioExponent) × baseGoals
    |
    | When teams are equal (ratio=1.0), both get base_goals (1.3 xG).
    | When elite faces bottom (ratio ~1.30), elite gets ~2.20 xG vs ~0.77.
    | The stronger team is ALWAYS favored regardless of venue.
    |
    | Real-world La Liga average: ~2.5 goals per match
    |
    */
    'base_goals' => 1.3,                // avg xG per team when evenly matched (~2.6 total)
    'ratio_exponent' => 2.0,            // amplifies strength ratio into xG gap
    'home_advantage_goals' => 0.15,     // fixed home xG bonus

    /*
    |--------------------------------------------------------------------------
    | Match Performance Variance (Randomness)
    |--------------------------------------------------------------------------
    |
    | Controls the "form on the day" randomness for each player.
    | Each player gets a performance modifier that affects their contribution.
    |
    | performance_std_dev: Standard deviation of the bell curve (0.03-0.20)
    |   - 0.03 = very consistent, best team almost always wins
    |   - 0.05 = low variance, lineup quality is decisive (default)
    |   - 0.08 = moderate variance, some upsets
    |   - 0.20 = high variance, many upsets
    |
    | performance_min/max: Absolute bounds for performance modifier
    |   - Default 0.90-1.10 means players can perform 10% below or above their rating
    |
    */
    'performance_std_dev' => 0.05,
    'performance_min' => 0.90,
    'performance_max' => 1.10,

    /*
    |--------------------------------------------------------------------------
    | Goal Distribution
    |--------------------------------------------------------------------------
    |
    | Controls the Poisson distribution for goal scoring.
    |
    | max_goals_cap: Maximum goals a team can score (prevents 10-0 results)
    |   - 0 = no cap
    |   - 7 = realistic cap (historical max in La Liga is 9-0)
    |
    */
    'max_goals_cap' => 6,

    /*
    |--------------------------------------------------------------------------
    | Event Probabilities
    |--------------------------------------------------------------------------
    |
    | Probabilities for various match events.
    |
    */
    'own_goal_chance' => 1.0,           // % chance per goal is an own goal
    'assist_chance' => 60.0,            // % chance a goal has an assist
    'yellow_cards_per_team' => 1.5,     // Average yellow cards per team per match
    'direct_red_chance' => 1,           // % chance of direct red card per team
    'injury_chance' => 4.0,             // % chance of injury per team per match

    /*
    |--------------------------------------------------------------------------
    | Player Energy / Stamina
    |--------------------------------------------------------------------------
    |
    | Players lose energy over the match based on physical ability and age.
    | Tired players contribute less to team strength, making substitutions
    | tactically meaningful.
    |
    | drain = base_drain - (physicalAbility - 50) * physical_ability_factor
    |         + max(0, age - age_threshold) * age_penalty_per_year
    | Goalkeepers drain at gk_drain_multiplier rate.
    |
    | Energy modifies player strength via:
    |   modifier = min_effectiveness + (energy/100) * (1 - min_effectiveness)
    |   Range: min_effectiveness (0.6) to 1.0
    |
    */
    'energy' => [
        'base_drain_per_minute' => 0.75,
        'physical_ability_factor' => 0.005,
        'age_threshold' => 28,
        'age_penalty_per_year' => 0.015,
        'gk_drain_multiplier' => 0.5,
        'min_effectiveness' => 0.6,
    ],

];
