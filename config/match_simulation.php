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
    | CPU, memory, and database load for background batch processing. It also
    | applies to sibling AI matches in a player-involved batch — only the
    | user's match runs the full simulator, siblings fast-resolve while still
    | emitting the goal/card events the live-match ticker consumes.
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
    | Expected Goals (Difference-Based Goal Supremacy)
    |--------------------------------------------------------------------------
    |
    | xG is driven by the DIFFERENCE in team strength, not a ratio. Team strength
    | is mean(rating)/100 on the 0..100 overall scale, so the difference is read
    | back in rating points and mapped to a goal "supremacy" (home xG minus away
    | xG):
    |
    |   d         = (homeStrength − awayStrength) × 100      // rating-point gap
    |   supremacy = d / goal_supremacy_scale                 // goals of edge
    |   homeXG    = base_goals + supremacy/2 + homeAdvantage
    |   awayXG    = base_goals − supremacy/2
    |
    | Why a difference and not a ratio: a rating has no true zero (no real squad
    | rates below ~50), so ratios of ratings are meaningless near the top of the
    | scale and need a per-league "floor" to rescue them. A difference is immune
    | to where the zero sits — 90-vs-78 is a 12-point edge whether the floor is 0
    | or 50 — so no floor, no per-league band, no outlier sensitivity, and no
    | ratio clamp are needed. A genuinely dominant squad keeps pulling clear
    | (supremacy grows linearly with the gap) instead of being renormalised back
    | toward the field. The same mapping is correct cross-league for free: a
    | top-flight side really is N points better than a lower-league team in a cup.
    |
    | base_goals: per-team xG when evenly matched (supremacy 0 → both get this).
    | goal_supremacy_scale (D): rating points per goal of home-minus-away edge.
    |   Lower D → quality matters more (bigger gaps, fewer upsets); higher D →
    |   flatter, more upsets. Calibrated so the season-1 La Liga distribution
    |   keeps a realistic spread (champion ~85, strongest squad wins ~45%); verify
    |   with `php artisan app:diagnose-strength-realism`.
    |
    | Example (base_goals 1.5, D 8.5, neutral):
    |   gap  4 pts → supremacy 0.47 → xG 1.74 vs 1.27  (slight edge, frequent draws)
    |   gap 12 pts → supremacy 1.41 → xG 2.21 vs 0.79  (clear favourite — pulls clear)
    |   gap 20 pts → supremacy 2.35 → xG 2.68 vs 0.32  (rout)
    |
    */
    'base_goals' => 1.5,                // per-team xG when evenly matched (~3.0 total)
    'goal_supremacy_scale' => 8.5,      // rating points per goal of home-minus-away supremacy
    'home_advantage_goals' => 0.20,     // fixed home xG bonus
    'defensive_quality_damping' => 1.0, // how much quality advantage erodes defensive tactics (0=none, higher=more erosion)

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
    |   - 0.05 = low variance, lineup quality is decisive
    |   - 0.10 = moderate variance, meaningful rating spread (default)
    |   - 0.20 = high variance, many upsets
    |
    | performance_min/max: Absolute bounds for performance modifier
    |   - Default 0.75-1.25 means players can perform 25% below or above their rating
    |
    */
    'performance_std_dev' => 0.10,
    'performance_min' => 0.75,
    'performance_max' => 1.25,

    /*
    |--------------------------------------------------------------------------
    | Team Strength Weights
    |--------------------------------------------------------------------------
    |
    | Weights for combining player attributes into team strength.
    | Must sum to 1.0. Used by both MatchSimulator and AIMatchResolver.
    |
    */
    'strength_weight_overall' => 0.95,
    'strength_weight_morale' => 0.05,

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
    'score_concentration' => 1.2,       // 1.0 = standard, >1 = results cluster closer to xG mode

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
    'yellow_cards_per_team' => 1.6,     // Average yellow cards per team per match
    'direct_red_chance' => 0.4,         // % chance of direct red card per team
    'injury_chance' => 0.8,             // % chance of injury per player per match
    'training_injury_chance' => 1,      // % chance of training injury per player per matchday (all squad members)
    'penalties_per_game' => 0.25,       // Average penalties awarded per game (both teams combined)
    'penalty_scored_chance' => 85.0,    // % chance a penalty is scored

    // Minutes at which substitutions don't consume a window (half-time, pre-ET, ET half-time)
    'free_sub_window_minutes' => [45, 90, 105],

    /*
    |--------------------------------------------------------------------------
    | Stoppage Time
    |--------------------------------------------------------------------------
    |
    | Stoppage durations are computed *from* the actual event mix the
    | simulator produced (subs, cards, injuries, goals), not sampled blindly.
    | This mirrors how a real referee accounts for stoppage on the touchline.
    |
    |   stoppage_seconds = baseline
    |       + seconds_per_substitution × subs
    |       + seconds_per_card         × bookings
    |       + seconds_per_injury       × injuries
    |       + seconds_per_goal         × goals
    |
    | Then ceil()'d to whole minutes and clamped to [min_minutes, max_minutes].
    | A quiet 0-0 gets ~1 min; a chaotic 4-3 with several subs and bookings
    | gets 6-8'.
    |
    */
    'stoppage' => [
        'baseline_seconds'         => 30,
        'seconds_per_substitution' => 30,
        'seconds_per_card'         => 30,
        'seconds_per_injury'       => 60,
        'seconds_per_goal'         => 30,
        'first_half_min_minutes'   => 0,
        'second_half_min_minutes'  => 1,
        'max_minutes'              => 12,
        'et_max_minutes'           => 4,
    ],

    /*
    |--------------------------------------------------------------------------
    | Player Energy (Unified Fitness/Stamina)
    |--------------------------------------------------------------------------
    |
    | Energy is unified with fitness: players start each match at their
    | current fitness level (not always 100). Drain is proportional to
    | starting energy, preventing death spirals in congested schedules.
    |
    | drain = (base_drain - (overallScore - 50) * overall_score_factor
    |          + max(0, age - age_threshold) * age_penalty_per_year)
    |         × (startingEnergy / 100)
    |
    | A typical player (overall 70, age 25) starting at 100 ends at ~60.
    | Goalkeepers drain at gk_drain_multiplier rate.
    |
    | Energy modifies player strength via:
    |   modifier = min_effectiveness + (energy/100) * (1 - min_effectiveness)
    |   Range: min_effectiveness (0.50) to 1.0
    |
    */
    'energy' => [
        'base_drain_per_minute' => 0.55,
        'overall_score_factor' => 0.004,
        'age_threshold' => 28,
        'age_penalty_per_year' => 0.015,
        'gk_drain_multiplier' => 0.5,
        'min_effectiveness' => 0.50,
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
        '5-3-2'   => ['attack' => 0.90, 'defense' => 0.90],   // Defensive, hard to break
        '5-4-1'   => ['attack' => 0.84, 'defense' => 0.86],   // Park the bus
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
        'defensive' => ['own_goals' => 0.88, 'opponent_goals' => 0.84],
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
        'counter_attack' => ['own_xg' => 0.97, 'opp_xg' => 0.88, 'energy_drain' => 1.05],
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
    | Defensive Fatigue (Time × Pressure)
    |--------------------------------------------------------------------------
    |
    | A sustained defensive shell tires. As a match wears on, a defending side
    | under pressure loses concentration and makes late defensive mistakes, so a
    | parked bus tends to crack in the closing stages. This adds a TIME dimension
    | to `defensive_quality_damping`: past `ramp_start_minute`, an additional
    | fraction of the opponent's defensive xG suppression is undone, ramping
    | linearly to `max_erosion` at minute 90. It only ever *lifts* the attacking
    | side's xG (a tiring shell concedes more, it never starts attacking) and only
    | applies where a quality edge already lets the stronger side break the block,
    | so evenly-matched games stay tight and low-scoring.
    |
    | This is what turns a raw quality advantage into late goals against packed
    | defences — the realism reason a much stronger side eventually pulls away
    | rather than being held to a 0-0 by a team that simply defends for 90'.
    |
    | ramp_start_minute: shell holds firm until this minute (no erosion before it).
    | max_erosion: at minute 90, up to this fraction of the surviving defensive
    |   suppression is undone (0 = fatigue off, 1 = the shell fully cracks).
    | pressure_scaled: when true, the shell tires faster the bigger the quality
    |   edge it is absorbing; when false, time is the only factor.
    | full_pressure_edge: the attacker's strength-ratio surplus (e.g. 0.30 = a
    |   30%-stronger side) at which pressure scaling reaches its full effect.
    |
    */
    'defensive_fatigue' => [
        'enabled' => true,
        'ramp_start_minute' => 60,
        'max_erosion' => 0.5,
        'pressure_scaled' => true,
        'full_pressure_edge' => 0.30,
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
        'missing_gk_xg_penalty' => 1.0,    // opponent xG multiplied by (1 + this) when no natural GK
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
        'halftime_sub_chance' => 10,        // % chance of making a sub at halftime (minute 45, free window)
        'window_grouping_minutes' => 3,     // subs within this many minutes = same window
        'energy_threshold' => 40,           // energy below this = strong sub candidate
        'yellow_card_weight' => 0.30,       // extra urgency score for yellowed players
        'losing_attack_bias' => 0.70,       // probability of preferring attackers when losing
    ],

];
