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
    | AI-vs-AI Match Resolution
    |--------------------------------------------------------------------------
    |
    | When enabled, AI-vs-AI matches use a lightweight statistical resolver
    | instead of the full MatchSimulator pipeline. This dramatically reduces
    | CPU, memory, and database load for background batch processing.
    |
    | The resolver uses the same Dixon-Coles model and xG formula but skips:
    | - Full lineup generation (FormationRecommender, tactical instructions)
    | - Energy model and minute-by-minute simulation periods
    | - AI substitution windows and bench management
    |
    | Rotation is preserved: players below the fitness threshold are penalized
    | so fresher alternatives rotate in, spreading stats realistically.
    |
    */
    'ai_resolver_enabled' => true,

    /*
    |--------------------------------------------------------------------------
    | Expected Goals (Ratio-Based Formula)
    |--------------------------------------------------------------------------
    |
    | The xG formula uses strength RATIOS rather than shares:
    |
    |   homeXG = (strengthRatio ^ skill_dominance) × baseGoals + homeAdvantage
    |   awayXG = (1/strengthRatio ^ skill_dominance) × baseGoals
    |
    | When teams are equal (ratio=1.0), both get base_goals (1.3 xG).
    | The stronger team is ALWAYS favored regardless of venue.
    |
    | Real-world La Liga average: ~2.5 goals per match
    |
    | skill_dominance: Controls how much team quality determines match outcomes.
    | Higher values widen the xG gap between strong and weak teams, meaning
    | the better team wins more often. Lower values compress the gap, leading
    | to more upsets and tighter leagues.
    |
    |   1.0 = linear, minimal skill advantage → frequent upsets (~60/40 skill/luck)
    |   1.5 = moderate, noticeable quality gap → some upsets (~70/30)
    |   2.3 = default, strong quality gap → realistic La Liga feel (~80/20)
    |   3.0 = high, dominant teams rarely lose (~90/10)
    |   4.0 = extreme, top teams almost never drop points (~95/5)
    |
    | Example with elite (str 0.72) vs bottom (str 0.55), ratio ≈ 1.31:
    |   skill_dominance 1.0 → xG: 1.70 vs 0.99 (upset ~25% of the time)
    |   skill_dominance 2.3 → xG: 2.36 vs 0.72 (upset ~10% of the time)
    |   skill_dominance 4.0 → xG: 3.53 vs 0.48 (upset ~2% of the time)
    |
    */
    'base_goals' => 1.3,                // avg xG per team when evenly matched (~2.6 total)
    'skill_dominance' => 2.3,           // how much team quality widens the xG gap (see above)
    'home_advantage_goals' => 0.20,     // fixed home xG bonus

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
    | Goal Distribution (Dixon-Coles Model)
    |--------------------------------------------------------------------------
    |
    | Goals are generated using the Dixon-Coles model, an improvement over
    | independent Poisson that correlates home and away goals. This produces
    | more realistic scoreline distributions, especially for low-scoring games.
    |
    | dixon_coles_rho: Correlation between home and away goals (-0.25 to 0.00)
    |   -0.00 = no correction (equivalent to independent Poisson)
    |   -0.10 = mild correction, slightly more draws
    |   -0.13 = default, matches real football data (recommended)
    |   -0.20 = strong correction, noticeably more 0-0 and 1-1 draws
    |   -0.25 = extreme, very draw-heavy results
    |
    |   Negative rho increases 0-0 and 1-1 probabilities while slightly
    |   decreasing 1-0 and 0-1 results. This matches the real-world pattern
    |   where teams "cancel each other out" more often than Poisson predicts.
    |
    | max_goals_cap: Maximum goals a team can score (prevents 10-0 results)
    |   - 0 = no cap
    |   - 7 = realistic cap (historical max in La Liga is 9-0)
    |
    | score_concentration: How tightly results cluster around the most likely
    |   scoreline. Raises each probability to this power, then renormalizes.
    |   This is an inverse-temperature transform on the Dixon-Coles distribution.
    |
    |   1.0 = standard Dixon-Coles (default)
    |   1.5 = moderately sharper, fewer blowouts and freak results
    |   2.0 = noticeably sharper, results strongly favor the mode
    |   3.0 = very sharp, almost always the 1-2 most likely scorelines
    |   <1.0 = flatter distribution, more random (not recommended)
    |
    |   This does NOT change xG — it only changes how the final scoreline
    |   is sampled from the probability distribution.
    |
    */
    'dixon_coles_rho' => -0.05,         // goal correlation: 0 = independent Poisson, -0.13 = realistic
    'max_goals_cap' => 6,
    'score_concentration' => 1.4,       // 1.0 = standard, >1 = results cluster closer to xG mode

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
    'injury_chance' => 1.0,             // % chance of injury per player per match
    'training_injury_chance' => 1.05,   // % chance of training injury per player per matchday (all squad members)
    'penalties_per_game' => 0.25,       // Average penalties awarded per game (both teams combined)
    'penalty_scored_chance' => 85.0,    // % chance a penalty is scored

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
        '4-3-3'   => ['attack' => 1.06, 'defense' => 1.06],   // Attacking, equally open
        '4-2-3-1' => ['attack' => 1.03, 'defense' => 1.02],   // Slight attack, slight opening
        '3-4-3'   => ['attack' => 1.12, 'defense' => 1.10],   // Very attacking, very exposed
        '3-5-2'   => ['attack' => 0.97, 'defense' => 0.96],   // Midfield control, conservative
        '4-1-4-1' => ['attack' => 0.95, 'defense' => 0.93],   // Defensive midfield shield
        '5-3-2'   => ['attack' => 0.90, 'defense' => 0.88],   // Defensive, hard to break
        '5-4-1'   => ['attack' => 0.84, 'defense' => 0.82],   // Park the bus
        '4-1-2-3' => ['attack' => 1.08, 'defense' => 1.07],   // Attacking with DM anchor
        '4-3-2-1' => ['attack' => 1.04, 'defense' => 1.03],   // Creative, narrow attack
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
        'defensive' => ['own_goals' => 0.82, 'opponent_goals' => 0.78],
        'balanced'  => ['own_goals' => 1.00, 'opponent_goals' => 1.00],
        'attacking' => ['own_goals' => 1.15, 'opponent_goals' => 1.12],
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
        'possession'     => ['own_xg' => 0.97, 'opp_xg' => 0.90, 'energy_drain' => 0.90],
        'balanced'       => ['own_xg' => 1.00, 'opp_xg' => 1.00, 'energy_drain' => 1.00],
        'counter_attack' => ['own_xg' => 0.93, 'opp_xg' => 0.88, 'energy_drain' => 1.05],
        'direct'         => ['own_xg' => 1.08, 'opp_xg' => 1.08, 'energy_drain' => 1.04],
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
        'high_press' => ['own_xg' => 1.08, 'opp_xg' => 0.88, 'energy_drain' => 1.30, 'fade_after' => 55, 'fade_opp_xg' => 1.04],
        'standard'   => ['own_xg' => 1.00, 'opp_xg' => 1.00, 'energy_drain' => 1.00, 'fade_after' => null, 'fade_opp_xg' => null],
        'low_block'  => ['own_xg' => 0.92, 'opp_xg' => 0.87, 'energy_drain' => 0.85, 'fade_after' => null, 'fade_opp_xg' => null],
    ],

    /*
    |--------------------------------------------------------------------------
    | Defensive Line Height (Out-of-Possession)
    |--------------------------------------------------------------------------
    |
    | own_xg: multiplier on YOUR expected goals (high line compresses space)
    | opp_xg: multiplier on OPPONENT's expected goals against you
    |
    */
    'defensive_line' => [
        'high_line' => ['own_xg' => 1.06, 'opp_xg' => 0.94],
        'normal'    => ['own_xg' => 1.00, 'opp_xg' => 1.00],
        'deep'      => ['own_xg' => 0.93, 'opp_xg' => 0.88],
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
        // Existing interactions
        'counter_vs_attacking_high_line' => 1.16,       // Counter-Attack bonus vs Attacking mentality + High Line
        'possession_disrupted_by_high_press' => 0.95,   // Possession own xG penalty vs opponent High Press
        'direct_bypasses_high_press' => 1.06,            // Direct own xG bonus vs opponent High Press

        // New interactions
        'high_press_vs_deep' => 0.96,                   // High Press defensive benefit reduced vs opponent Deep line (press has nowhere to win ball)
        'counter_vs_low_block' => 1.06,                  // Counter-Attack team more vulnerable vs opponent Low Block (can't exploit space)
        'possession_vs_deep_low_block' => 0.93,          // Possession own xG penalty vs opponent Deep + Low Block (can't break the wall)
        'direct_vs_deep' => 1.08,                        // Direct own xG bonus vs opponent Deep line (long balls bypass deep block)
        'high_line_high_press_synergy' => 1.06,          // Own xG bonus when using both High Line + High Press (coordinated pressing)
        'attacking_high_line_vulnerability' => 1.04,     // Opponent own xG bonus vs team using Attacking mentality + High Line (general exposure)
    ],

    /*
    |--------------------------------------------------------------------------
    | Possession Calculation (Cosmetic)
    |--------------------------------------------------------------------------
    |
    | Possession % is derived from tactical choices and team strength.
    | Each factor adds/subtracts from a base of 50. The raw scores for both
    | teams are then normalized so they sum to 100%.
    |
    | Possession also has a small gameplay effect: teams with dominant
    | possession get a slight xG bonus (more territory = more chances),
    | while teams with very low possession get a small penalty.
    |
    | noise_range: random ± variation (seeded per match for determinism)
    |
    */
    'possession' => [
        'playing_style' => [
            'possession' => 7,
            'balanced' => 0,
            'counter_attack' => -5,
            'direct' => -2,
        ],
        'pressing' => [
            'high_press' => 3,
            'standard' => 0,
            'low_block' => -3,
        ],
        'mentality' => [
            'defensive' => -2,
            'balanced' => 0,
            'attacking' => 2,
        ],
        'formation_midfield' => [
            '4-4-2' => 1,
            '4-3-3' => 0,
            '4-2-3-1' => 2,
            '3-4-3' => 1,
            '3-5-2' => 3,
            '4-1-4-1' => 2,
            '5-3-2' => 0,
            '5-4-1' => 1,
            '4-1-2-3' => 1,
            '4-3-2-1' => 2,
        ],
        'strength_max_bonus' => 5,
        'noise_range' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Possession xG Effect
    |--------------------------------------------------------------------------
    |
    | Small xG modifier based on possession differential. Teams with dominant
    | possession get a slight attacking bonus (more territory = more chances).
    | This prevents possession from being purely cosmetic.
    |
    | max_bonus: maximum xG multiplier bonus at 65%+ possession (e.g. 0.08 = +8%)
    | max_penalty: maximum xG penalty at 35%- possession (e.g. -0.05 = -5%)
    | neutral_band: possession range where no modifier is applied
    |
    */
    'possession_xg_effect' => [
        'enabled' => true,
        'max_bonus' => 0.08,
        'max_penalty' => -0.05,
        'neutral_band' => [47, 53],
    ],

    /*
    |--------------------------------------------------------------------------
    | Goalkeeper Quality
    |--------------------------------------------------------------------------
    |
    | When a team has no natural goalkeeper in their lineup (e.g. an outfield
    | player in the GK slot), the opponent's xG is increased. This reflects
    | the massive defensive disadvantage of playing without a proper keeper.
    |
    */
    'goalkeeper' => [
        'missing_gk_xg_penalty' => 0.25,   // opponent xG multiplied by (1 + this) when no natural GK
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Substitutions
    |--------------------------------------------------------------------------
    |
    | Controls when and how AI teams make substitutions during a match.
    |
    | mode: Controls which matches get AI substitutions:
    |   - "all"      — AI subs in all matches (AI-vs-AI and user-vs-AI)
    |   - "ai_only"  — AI subs only in AI-vs-AI matches (not in user's live match)
    |   - "off"      — AI subs disabled entirely
    |
    | Substitution timing uses a Poisson distribution: minute = min_minute + Poisson(λ).
    | With λ=10 and min_minute=60, most subs cluster around minute 70 (range 60-85).
    |
    | The AI decides WHO to sub based on energy levels, yellow card risk, and
    | bench quality. Match situation (score) biases replacements toward
    | attackers (when losing) or defenders (when protecting a lead).
    |
    | Halftime substitutions happen independently with a fixed probability,
    | representing tactical half-time adjustments.
    |
    */
    'ai_substitutions' => [
        'mode' => 'all',                     // 'all', 'ai_only', or 'off'
        'min_subs' => 3,                    // minimum subs per match (target, not guaranteed)
        'max_subs' => 5,                    // hard limit (matches SubstitutionService::MAX_SUBSTITUTIONS)
        'poisson_lambda' => 10,             // Poisson λ for timing offset (peak at min_minute + λ)
        'min_minute' => 60,                 // earliest normal sub minute
        'max_minute' => 85,                 // latest sub minute
        'halftime_sub_chance' => 25,        // % chance of making a sub at halftime (minute 46)
        'window_grouping_minutes' => 3,     // subs within this many minutes = same window
        'energy_threshold' => 40,           // energy below this = strong sub candidate
        'yellow_card_weight' => 0.30,       // extra urgency score for yellowed players
        'losing_attack_bias' => 0.70,       // probability of preferring attackers when losing
    ],

];
