<?php

return [
    // ── Matchday gate ──────────────────────────────────────────────────

    // Matchday revenue per seat per season by reputation level (in cents).
    'revenue_per_seat' => [
        'elite'        => 70_000, // €700/seat
        'continental'  => 44_000, // €440/seat
        'established'  => 31_000, // €310/seat
        'modest'       => 21_000, // €210/seat
        'local'        =>  9_000, // €90/seat
    ],

    // ── Season tickets ─────────────────────────────────────────────────

    // Season-ticket pricing presets: a single global multiplier on each area's
    // baseline price. Accessible fills more seats at a lower per-seat price;
    // Premium does the reverse. Discrete presets (not free-number sliders)
    // keep the decision legible and the demand model un-gameable.
    'season_ticket_presets' => [
        'accessible' => 0.75,
        'standard'   => 1.00,
        'premium'    => 1.40,
    ],

    // Max share of match demand left for walk-up buyers, at zero loyalty.
    // Abono penetration is scaled to (1 − reserve_max × (1 − loyalty/100)) of
    // the loyalty fill, so the walk-up gap is widest for low-loyalty clubs and
    // tapers to ~0 for elites — who legitimately sell out via abonos and so
    // draw little walk-up. This is what stops a club's projected taquilla from
    // collapsing to €0 just because its season-ticket curve matched its
    // attendance curve (they used to be identical).
    'season_ticket_walkup_reserve_max' => 0.15,

    // Share of season-ticket holders who don't attend a given match. Lowers the
    // displayed/settled crowd (attending holders + walk-ups) but does NOT change
    // walk-up gate revenue — abonos are prepaid, so an empty paid seat earns
    // nothing extra and costs nothing.
    'season_ticket_noshow_rate' => 0.05,

    // ── Attendance demand ──────────────────────────────────────────────

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

    // ── Fan loyalty ────────────────────────────────────────────────────

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

    // ── Stadium upgrades ───────────────────────────────────────────────

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
];
