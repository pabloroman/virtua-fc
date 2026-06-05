<?php

return [
    // Annual operating expenses by reputation level (in cents)
    // Covers: non-playing staff, admin, travel, insurance, legal, etc.
    'operating_expenses' => [
        'elite'        =>  9_500_000_000, // €95M
        'continental'  =>  5_500_000_000, // €55M
        'established'  =>  2_700_000_000, // €27M
        'modest'       =>  1_000_000_000, // €10M
        'local'        =>    600_000_000, // €6M
    ],

    // Squad wage-bill ceiling as a fraction of projected RECURRING revenue
    // ("Límite de Coste de Plantilla"). The cap is tied to recurring income, so
    // one-time cash (unspent transfer budget, player-sale proceeds, carried
    // surplus) can never inflate the wage ceiling — this closes the free-signing
    // exploit. ~0.70 mirrors UEFA's squad-cost ratio and leaves a thin-but-
    // positive surplus for transfers/infrastructure even at maximum wages across
    // every tier. May be replaced with a per-reputation array (keyed
    // elite/continental/established/modest/local) if tuning calls for it.
    'wage_cap_ratio' => 0.70,

    // Wage-demand "market rate" comparison. A player renewing (or being flagged
    // as underpaid) is compared against squadmates of similar CURRENT ability —
    // overall_score within ±this band — rather than against their market-value
    // tier. Grouping by ability stops a benchwarmer or a developing youngster
    // (whose market value is inflated by potential) from demanding a star's wage.
    'wage_peer_ability_band' => 5,

    // How far a renewal demand is pulled toward that peer median when the
    // player is paid below it. A *partial* pull (not a hard floor): 0 ignores
    // peers entirely, 1.0 matches the median exactly. 0.5 closes half the gap,
    // so equally-able players don't fully converge and a manager can still run
    // a salary scale.
    'renewal_peer_pull_factor' => 0.5,

    // Release clauses (cláusulas de rescisión). The amount is a "golden
    // handcuffs" model anchored to market value. Mandatory for Spanish (ES)
    // clubs, optional elsewhere. Multipliers are bare floats (multiples of
    // market value), NOT cents.
    'release_clause' => [
        // Countries whose clubs MUST carry a release clause on every contract
        // (mirrors Royal Decree 1006/1985 — buyout clauses are mandatory in
        // Spain). In these countries the clause, not the market value, is the
        // figure the transfer market operates on, so list surfaces display it
        // in place of market value. Team.country stores uppercase 2-char codes.
        'mandatory_countries' => ['ES'],

        // Mandatory minimum clause for ES clubs, as a multiple of market value.
        // The derived default at every agreement equals this floor.
        'es_floor_multiplier' => 1.25,

        // Player resistance to a high clause, expressed as a CAP (multiple of
        // market value) that rises with the wage premium offered over the
        // player's demand. The manager may raise the clause up to this cap by
        // paying a bigger wage; going higher requires a bigger wage still.
        //   tolerance(ratio) = clamp(base + premium_slope * max(0, ratio - 1), base, hard_cap)
        //   where ratio = offered_wage / wage_demand.
        'tolerance' => [
            'base'          => 1.25, // cap at wage parity (offered == demand)
            'premium_slope' => 2.5,  // extra MV multiple per +1.0 of wage premium
            'hard_cap'      => 2.5,  // absolute ceiling on the MV multiple
        ],

        // Per-matchday probability that an AI club triggers the clause on one of
        // the user's players (Phase 3), keyed by player tier (5 = world class …
        // 1 = low). Deliberately well below the unsolicited-offer chances: a
        // forced buyout is rare and dramatic. Tune from season simulation.
        'ai_trigger_chance_by_tier' => [
            5 => 0.003,
            4 => 0.004,
            3 => 0.003,
            2 => 0.0015,
            1 => 0.0005,
        ],

        // Minimum SquadNeedService desire (0..1) for an AI club to be willing to
        // pay the premium clause: it must genuinely need/upgrade with the player,
        // with affordability headroom factored in. Below this, no club triggers
        // the clause even if one could afford it. Tune from season simulation.
        'ai_trigger_min_desire' => 0.55,
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
    // Tier 1 (La Liga) = full cost, lower tiers scale down to match their
    // much smaller revenue footprint (Primera RFEF TV tops out ~€1.5M).
    'operating_expense_tier_multiplier' => [
        1 => 1.0,   // La Liga: full operating expenses
        2 => 0.70,  // Segunda: 70% of base operating expenses
        3 => 0.25,  // Primera RFEF: ~1/4 of base, keeps floors under typical revenue
    ],

    // Commercial revenue multiplier by competition tier (season 1 only).
    // Reflects the sharp drop in sponsor/merchandising deals the further a club
    // sits from La Liga. Real-world Primera RFEF commercial income is typically
    // €200K–€800K, a fraction of what Segunda clubs pull in.
    'commercial_tier_multiplier' => [
        1 => 1.0,   // La Liga: full commercial rate
        2 => 0.75,  // Segunda: 75%
        3 => 0.25,  // Primera RFEF: 25%
    ],

    // Budget loan configuration.
    // Allows the user to borrow against projected revenue to boost transfer budget.
    'loan' => [
        'max_percentage' => 0.10,       // 10% of projected total revenue
        'interest_rate' => 1500,        // 15% interest (in basis points: 1500 = 15%)
        'minimum' => 50_000_000,        // €500K minimum loan (in cents)
    ],

    // Stadium upgrade pricing.
    'stadium_costs' => [
        // Modular bleachers — temporary feel, fast install. Per-seat cost
        // anchored on real-world temp-stadium contracts (e.g. Ibercaja
        // Estadio for the Zaragoza relocation: ~€6M for ~14k seats).
        'supplementary_per_seat_cents' => 400_000, // €4,000/seat
        'supplementary_max_seats_per_project' => 8_000,
        // Supplementary stands take this many in-game days to install.
        'supplementary_construction_days' => 30,

        // Permanent single-stand rebuild — Anfield Road / Selhurst Park
        // scope (€30–80M for 3–8k seats ≈ €4–10k/seat). Construction takes
        // a fixed in-game duration; the rest of the stadium stays open
        // during the build (no capacity drop).
        'stand_expansion_per_seat_cents' => 800_000, // €8,000/seat
        'stand_expansion_min_seats' => 3_000,
        'stand_expansion_max_seats' => 12_000,
        'stand_expansion_construction_days' => 270, // ~9 months / one football season

        // Full rebuild — cumulative bracket pricing (tax-bracket style).
        // Per-seat marginal cost grows with target size; total cost stays
        // continuous as the slider crosses bracket boundaries.
        // Anchors: Boston United / FC Andorra (≤10k, €3k/seat);
        // Wildparkstadion (≤30k, €5k); Europa-Park Stadion (≤50k, €7k);
        // Metropolitano (≤80k, €10k); Wembley / Bernabéu (>80k, €15k).
        'rebuild_per_seat_bands' => [
            ['up_to' =>  10_000, 'per_seat_cents' =>   300_000],
            ['up_to' =>  30_000, 'per_seat_cents' =>   500_000],
            ['up_to' =>  50_000, 'per_seat_cents' =>   700_000],
            ['up_to' =>  80_000, 'per_seat_cents' => 1_000_000],
            ['up_to' =>    null, 'per_seat_cents' => 1_500_000],
        ],
        'rebuild_construction_days' => 540, // ~18 months / two football seasons

        // UEFA category upgrade — facility-tier renovation (covered seats,
        // floodlights, broadcast booths, media rooms, dressing rooms, etc.)
        // to meet the next UEFA category's infrastructure requirements.
        // One-level-at-a-time; each transition costs the amount listed
        // below (key = source level). No capacity change while the
        // facilities are being fitted out.
        // Reference: UEFA Cat 4 fit-outs run ~€20–80M depending on the
        // starting state; the values here sit at the lower end so multiple
        // projects across a long save remain affordable.
        'uefa_upgrade_cost_cents' => [
            1 => 500_000_000,    // 1 → 2: €5M
            2 => 2_000_000_000,  // 2 → 3: €20M
            3 => 5_000_000_000,  // 3 → 4: €50M
        ],
        'uefa_upgrade_construction_days' => 270, // ~9 months / one football season
    ],

    // Stadium rebuild loan configuration.
    // Flat-principal: principal/term_years constant principal per year,
    // plus interest on the outstanding balance — total payment is highest
    // in year 1 and declines over the term.
    'stadium_loan' => [
        'term_years' => 10,
        'interest_rate_bps' => 400,            // 4% interest (basis points)
        // Maximum share of projected operating revenue that can go to
        // year-1 debt service. The bank refuses to lend beyond this.
        'max_debt_service_pct' => 0.25,
        // Reputation-tier ceilings on loan principal (in cents). Ambition cap.
        'reputation_caps' => [
            'local'        =>  10_000_000_000, // €100M
            'modest'       =>  25_000_000_000, // €250M
            'established'  =>  60_000_000_000, // €600M
            'continental'  => 120_000_000_000, // €1.2B
            'elite'        => 250_000_000_000, // €2.5B
        ],
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
