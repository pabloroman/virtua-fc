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
    'yellow_cards_per_team' => 1.4,     // Average yellow cards per team per match
    'direct_red_chance' => 0.5,         // % chance of direct red card per team
    'injury_chance' => 1.2,             // % chance of injury per player per match
    'training_injury_chance' => 1.5,    // % chance of training injury per player per matchday (all squad members)

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

    /*
    |--------------------------------------------------------------------------
    | Formation Modifiers
    |--------------------------------------------------------------------------
    |
    | Each formation has an attack and defense modifier applied to xG.
    | attack: multiplier on YOUR expected goals (1.0 = neutral)
    | defense: multiplier on OPPONENT's expected goals against you (< 1.0 = concede less)
    |
    */
    'formations' => [
        '4-4-2'   => ['attack' => 1.00, 'defense' => 1.00],   // Balanced baseline
        '4-3-3'   => ['attack' => 1.08, 'defense' => 1.04],   // Attacking, slightly open
        '4-2-3-1' => ['attack' => 1.03, 'defense' => 0.97],   // Solid and creative
        '3-4-3'   => ['attack' => 1.12, 'defense' => 1.08],   // Very attacking, exposed
        '3-5-2'   => ['attack' => 1.00, 'defense' => 0.96],   // Midfield control
        '4-1-4-1' => ['attack' => 0.95, 'defense' => 0.92],   // Defensive midfield shield
        '5-3-2'   => ['attack' => 0.88, 'defense' => 0.86],   // Defensive, hard to break
        '5-4-1'   => ['attack' => 0.80, 'defense' => 0.82],   // Park the bus
    ],

    /*
    |--------------------------------------------------------------------------
    | Mentality Modifiers
    |--------------------------------------------------------------------------
    |
    | Each mentality has two modifiers:
    | own_goals: multiplier on YOUR expected goals
    | opponent_goals: multiplier on OPPONENT's expected goals against you
    |
    */
    'mentalities' => [
        'defensive' => ['own_goals' => 0.80, 'opponent_goals' => 0.70],
        'balanced'  => ['own_goals' => 1.00, 'opponent_goals' => 1.00],
        'attacking' => ['own_goals' => 1.15, 'opponent_goals' => 1.10],
    ],

    /*
    |--------------------------------------------------------------------------
    | Playing Style (In-Possession)
    |--------------------------------------------------------------------------
    |
    | own_xg: multiplier on YOUR expected goals
    | opp_xg: multiplier on OPPONENT's expected goals against you
    | energy_drain: multiplier on energy drain rate (1.0 = normal)
    |
    */
    'playing_styles' => [
        'possession'     => ['own_xg' => 1.05, 'opp_xg' => 0.95, 'energy_drain' => 1.10],
        'balanced'       => ['own_xg' => 1.00, 'opp_xg' => 1.00, 'energy_drain' => 1.00],
        'counter_attack' => ['own_xg' => 0.92, 'opp_xg' => 0.95, 'energy_drain' => 0.95],
        'direct'         => ['own_xg' => 1.02, 'opp_xg' => 1.03, 'energy_drain' => 1.00],
    ],

    /*
    |--------------------------------------------------------------------------
    | Pressing Intensity (Out-of-Possession)
    |--------------------------------------------------------------------------
    |
    | own_xg: multiplier on YOUR expected goals (pressing can win ball high)
    | opp_xg: multiplier on OPPONENT's expected goals against you
    | energy_drain: multiplier on energy drain rate
    | fade_after: minute after which High Press starts fading (null = no fade)
    | fade_opp_xg: the opp_xg value it fades TO by minute 90
    |
    */
    'pressing' => [
        'high_press' => ['own_xg' => 1.00, 'opp_xg' => 0.90, 'energy_drain' => 1.15, 'fade_after' => 60, 'fade_opp_xg' => 0.97],
        'standard'   => ['own_xg' => 1.00, 'opp_xg' => 1.00, 'energy_drain' => 1.00, 'fade_after' => null, 'fade_opp_xg' => null],
        'low_block'  => ['own_xg' => 0.94, 'opp_xg' => 0.94, 'energy_drain' => 0.92, 'fade_after' => null, 'fade_opp_xg' => null],
    ],

    /*
    |--------------------------------------------------------------------------
    | Defensive Line Height (Out-of-Possession)
    |--------------------------------------------------------------------------
    |
    | own_xg: multiplier on YOUR expected goals (high line compresses space)
    | opp_xg: multiplier on OPPONENT's expected goals against you
    | physical_threshold: opponent forward physical ability above which
    |                     the high line bonus is nullified (0 = never)
    |
    */
    'defensive_line' => [
        'high_line' => ['own_xg' => 1.03, 'opp_xg' => 0.94, 'physical_threshold' => 80],
        'normal'    => ['own_xg' => 1.00, 'opp_xg' => 1.00, 'physical_threshold' => 0],
        'deep'      => ['own_xg' => 0.94, 'opp_xg' => 0.92, 'physical_threshold' => 0],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tactical Interaction Bonuses
    |--------------------------------------------------------------------------
    |
    | Multipliers applied when specific instruction combinations interact.
    |
    */
    'tactical_interactions' => [
        'counter_vs_attacking_high_line' => 1.16,       // Counter-Attack bonus vs Attacking mentality + High Line
        'possession_disrupted_by_high_press' => 0.95,   // Possession own xG penalty vs opponent High Press
        'direct_bypasses_high_press' => 1.06,            // Direct own xG bonus vs opponent High Press
    ],

    /*
    |--------------------------------------------------------------------------
    | Between-Match Fatigue
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
    'fatigue' => [
        'base_recovery_per_day' => 1.0,         // recovery rate per day at fitness 100
        'recovery_scaling' => 2.5,              // how much faster recovery is at low fitness
        'max_recovery_days' => 5,               // cap recovery calculation at this many days

        'fitness_loss' => [                     // [min, max] fitness loss per match by position
            'Goalkeeper' => [3, 6],             // GKs barely tire
            'Defender' => [9, 13],              // moderate
            'Midfielder' => [10, 15],           // highest — midfielders run the most
            'Forward' => [9, 13],               // moderate
        ],

        'age_loss_modifier' => [                // multiplier on fitness loss by age bracket
            'young_threshold' => 24,
            'peak_threshold' => 29,
            'veteran_threshold' => 32,
            'young' => 0.92,                    // < 24: less fatigue per match
            'peak' => 1.0,                      // 24-28: baseline
            'experienced' => 1.05,              // 29-31: slightly more
            'veteran' => 1.12,                  // 32+: noticeably more
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

    /*
    |--------------------------------------------------------------------------
    | Red Card Impact
    |--------------------------------------------------------------------------
    |
    | When a red card occurs during simulation, the match is split into two
    | periods at the red card minute. The second period recalculates team
    | strength with the reduced lineup, then applies these modifiers on top:
    |
    | attack_modifier: xG multiplier for the 10-man team's own attack
    | defense_modifier: xG multiplier for the opponent facing 10 men
    |
    | These represent the structural disadvantage of fewer players on the
    | pitch (less coverage, more space). They stack with the natural strength
    | reduction from recalculating with 10 players instead of 11.
    |
    */
    'red_card_impact' => [
        'attack_modifier' => 0.80,      // 20% reduction in xG for the 10-man team
        'defense_modifier' => 1.15,     // 15% boost in opponent xG when facing 10 men
    ],

];
