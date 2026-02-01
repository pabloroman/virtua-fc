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
    | Expected Goals Base Values
    |--------------------------------------------------------------------------
    |
    | These are the starting expected goals before strength is applied.
    | Higher values = more goals in matches.
    |
    | Real-world La Liga average: ~2.5 goals per match
    | Home teams score ~1.5 goals on average, away teams ~1.0
    |
    */
    'base_home_goals' => 1.4,
    'base_away_goals' => 0.9,

    /*
    |--------------------------------------------------------------------------
    | Strength Impact
    |--------------------------------------------------------------------------
    |
    | Controls how much team quality affects the result.
    |
    | strength_multiplier: How much strength adds to expected goals (0.5-2.0)
    |   - 1.0 = moderate impact (default)
    |   - 1.5 = stronger teams score significantly more
    |   - 0.5 = more parity, weaker teams can compete
    |
    | strength_exponent: Amplifies differences between strong and weak teams (1.0-2.0)
    |   - 1.0 = linear (default)
    |   - 1.5 = strong teams get bonus, weak teams get penalty
    |   - 2.0 = very strong amplification (top teams dominate)
    |
    | Example with 85 vs 78 rated teams:
    |   - exponent 1.0: ratio stays ~52% vs 48%
    |   - exponent 1.5: ratio becomes ~55% vs 45%
    |   - exponent 2.0: ratio becomes ~58% vs 42%
    |
    */
    'strength_multiplier' => 1.2,
    'strength_exponent' => 1.3,

    /*
    |--------------------------------------------------------------------------
    | Home Advantage
    |--------------------------------------------------------------------------
    |
    | Additional bonus for the home team beyond base goals.
    |
    | home_advantage_goals: Extra expected goals for home team (0.0-0.5)
    |   - 0.0 = no home advantage
    |   - 0.2 = slight advantage
    |   - 0.3 = moderate advantage (realistic)
    |   - 0.5 = strong advantage
    |
    | away_disadvantage_multiplier: Reduces away team's effectiveness (0.7-1.0)
    |   - 1.0 = no penalty (default)
    |   - 0.9 = 10% reduction in away team's strength contribution
    |   - 0.8 = 20% reduction (significant away disadvantage)
    |
    */
    'home_advantage_goals' => 0.15,
    'away_disadvantage_multiplier' => 0.8,

    /*
    |--------------------------------------------------------------------------
    | Match Performance Variance (Randomness)
    |--------------------------------------------------------------------------
    |
    | Controls the "form on the day" randomness for each player.
    | Each player gets a performance modifier that affects their contribution.
    |
    | performance_std_dev: Standard deviation of the bell curve (0.05-0.20)
    |   - 0.05 = very consistent, best team almost always wins
    |   - 0.12 = moderate variance (default)
    |   - 0.20 = high variance, more upsets
    |
    | performance_min/max: Absolute bounds for performance modifier
    |   - Default 0.70-1.30 means players can perform 30% below or above their rating
    |
    */
    'performance_std_dev' => 0.10,
    'performance_min' => 0.70,
    'performance_max' => 1.30,

    /*
    |--------------------------------------------------------------------------
    | Goal Distribution
    |--------------------------------------------------------------------------
    |
    | Controls the Poisson distribution for goal scoring.
    |
    | max_goals_cap: Maximum goals a team can score (prevents 10-0 results)
    |   - 0 = no cap
    |   - 7 = realistic cap
    |
    */
    'max_goals_cap' => 0,

    /*
    |--------------------------------------------------------------------------
    | Event Probabilities
    |--------------------------------------------------------------------------
    |
    | Probabilities for various match events.
    |
    */
    'own_goal_chance' => 2.0,           // % chance per goal is an own goal
    'assist_chance' => 60.0,            // % chance a goal has an assist
    'yellow_cards_per_team' => 1.7,     // Average yellow cards per team per match
    'direct_red_chance' => 1.5,         // % chance of direct red card per team
    'injury_chance' => 5.0,             // % chance of injury per team per match

];
