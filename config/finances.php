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

    // How strongly seeded/derived wages anchor to a player's ABILITY rather
    // than to his raw (age-deflated) market value. Market value falls with age
    // faster than skill, so a still-able 33-year-old's wage craters with his
    // price, then a discrete veteran modifier yanks 35+ back up — a cliff. This
    // knob blends the wage's value anchor from market value (0.0, today's
    // behaviour) toward the ability-derived `PlayerValuationService::
    // wageBaseValue()` (1.0), and smooths the veteran age curve in step.
    //
    // The blended model is normalized (see ContractService::WAGE_ANCHOR_*),
    // so the TOTAL population wage bill stays ~unchanged at every setting — the
    // knob reshapes who earns what (lifting under-paid prime/veteran players,
    // trimming others) without inflating the economy or the salary cap. It is
    // read by the shared wage path, so seeding, contract demands, and AI
    // signings all move together. 0.0 reproduces today exactly.
    'wage_ability_anchor' => 0.75,

    // Player-trading allowance ("plusvalías"). A trailing average of the club's
    // NET player-trading result (sales − purchases) over recent completed
    // seasons is added to the cap base — and ONLY the cap base, never the
    // projected surplus/budget (the sale cash already reaches the budget via
    // carried surplus). This lets a sustained selling club (Benfica / Brighton
    // model) support a higher wage bill, mirroring how real squad-cost rules
    // count net player trading, while a one-off windfall sale is smoothed away
    // and so can't reopen the free-signing exploit. Net buyers get nothing
    // (floored at 0) but pay no penalty.
    'trading_allowance' => [
        // Trailing window length, in completed seasons, for the net-trading average.
        'window_seasons' => 3,
        // Fraction of the trailing net average that counts toward the cap base.
        'weight' => 1.0,
        // Hard guard: the allowance can never exceed this fraction of recurring
        // revenue, so a pure-trading club can't run an unbounded wage cap.
        'max_fraction_of_recurring' => 0.50,
    ],

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

        // Default clause for ES clubs, as a multiple of market value. The derived
        // default at every agreement (seeding, untouched renewals/signings) equals
        // this floor, and it's where the negotiation slider starts.
        'es_floor_multiplier' => 1.25,

        // Absolute minimum a manager may set the clause to during a renewal or an
        // incoming signing, as a multiple of market value. Below es_floor_multiplier
        // (and below 1.0 = below market value) so managers can deliberately set a
        // cheap buyout — at the cost of making the player far easier for AI clubs to
        // poach (the underprice trigger multiplier ramps up as clause falls under MV).
        'es_min_multiplier' => 0.25,

        // Golden handcuffs: a clause raised above the mandatory floor isn't capped,
        // it raises the WAGE the player demands to be locked in. There is no
        // ceiling — the wage requirement keeps climbing with the clause:
        //   demand_factor = 1 + (clause - floor) / (premium_slope * market_value)
        // premium_slope is the market-value multiple of clause, above the floor, that
        // each +1.0 of wage premium buys (i.e. a clause of premium_slope × MV above
        // the floor doubles the player's wage demand).
        'tolerance' => [
            'premium_slope' => 2.5,
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

        // AI poach: a clause that has fallen below the player's current market
        // value is a bargain forced-buy (clauses don't ratchet, so a developing
        // player outgrows his stale clause), so AI clubs trigger it more often. The
        // per-matchday base chance above is multiplied by
        //   1 + ai_underprice_slope × max(0, market_value/clause − 1)
        // capped at ai_underprice_max_multiplier. A clause at/above market value
        // gets no boost (multiplier 1). Tune from season simulation.
        'ai_underprice_slope' => 1.0,
        'ai_underprice_max_multiplier' => 5.0,

        // Minimum SquadNeedService desire (0..1) for an AI club to be willing to
        // pay the premium clause: it must genuinely need/upgrade with the player,
        // with affordability headroom factored in. Below this, no club triggers
        // the clause even if one could afford it. Tune from season simulation.
        'ai_trigger_min_desire' => 0.55,
    ],

    // Homegrown loyalty. Players developed by the club's own pipeline — youth
    // academy or filial/reserve promotion (GamePlayer::isHomegrown()) — are more
    // willing to STAY: less greedy at renewal, more flexible at the table, and
    // happier to accept a higher release clause for a smaller wage bump. All
    // three are RENEWAL-scoped on purpose; applying them to transfers would make
    // the user's own academy product easier for a rival to poach.
    'homegrown' => [
        // Shaves this fraction off the renewal wage demand (0.15 = ask 15% less).
        // Applied before the "must get a raise" floor, so a pay cut is still
        // never demanded — only the size of the raise shrinks.
        'renewal_demand_discount' => 0.15,

        // Flat bonus added to the renewal negotiation disposition (the 0.10–0.95
        // willingness-to-accept-below-demand scale), so a slightly-low offer
        // lands where a bought player would counter.
        'disposition_bonus' => 0.10,

        // Multiplies the release-clause golden-handcuffs premium_slope for
        // homegrown players: a steeper slope means each € of clause above the
        // floor costs less in wages. 2.0 = the wage premium grows half as fast.
        'clause_slope_multiplier' => 2.0,
    ],

    // Commercial revenue per seat per season by reputation level (in cents).
    'commercial_per_seat' => [
        'elite'        => 170_000, // €1,700/seat
        'continental'  =>  87_500, // €875/seat
        'established'  =>  62_500, // €625/seat
        'modest'       =>  45_000, // €450/seat
        'local'        =>  24_000, // €240/seat
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

    // Brand-driven commercial floor by reputation level (in cents), expressed
    // at competition tier 1. Decouples a marquee club's commercial income from
    // its stadium size: a global brand earns sponsorship + merchandising far
    // beyond what `stadium_seats × commercial_per_seat` implies (e.g. PSG bills
    // ~€200M commercial off a 48K-seat ground). Applied as a FLOOR — the club
    // keeps the higher of the stadium-driven figure and this brand baseline —
    // so a club that builds a bigger stadium never *loses* commercial income.
    // Only the top two reputation tiers carry a brand premium; 'established'
    // and below stay purely stadium-driven, since their commercial income
    // really is gate-led. Tier-scaled by `commercial_tier_multiplier`, so a
    // relegated brand's commercial tapers with the division.
    'commercial_brand_floor' => [
        'elite'       => 20_000_000_000, // €200M
        'continental' =>  7_500_000_000, // €75M
    ],

    // Budget loan configuration.
    // Allows the user to borrow against projected revenue to boost transfer budget.
    'loan' => [
        'max_percentage' => 0.10,       // 10% of projected total revenue
        'interest_rate' => 1500,        // 15% interest (in basis points: 1500 = 15%)
        'minimum' => 50_000_000,        // €500K minimum loan (in cents)
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
