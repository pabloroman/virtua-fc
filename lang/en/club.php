<?php

return [
    'hub_title' => 'Club',

    'nav' => [
        'finances' => 'Finances',
        'stadium' => 'Stadium',
        'reputation' => 'Reputation',
    ],

    'stadium' => [
        'home_ground' => 'Home ground',
        'stadium_name' => 'Stadium',
        'capacity' => 'Capacity',

        'fan_base' => 'Fan base',
        'fan_base_help' => 'Loyalty rises with trophies and strong finishes and dips after poor seasons. Together with reputation, it drives how full the stadium gets on matchday.',
        'fan_base_trend' => 'Fan trend',
        'current_loyalty' => 'Fan support',

        'last_attendance' => 'Last home match',
        'fill_rate' => 'Fill rate',
        'no_home_match_yet' => 'No home match has been played yet.',

        'no_finances_yet' => 'Season finances will appear once projections are generated.',

        'stadium_revenue' => [
            'title' => 'Stadium revenue',
            'season_tickets' => 'Season tickets',
            'matchday' => 'Matchday',
            'help' => 'Season tickets are pre-paid up front; matchday revenue accrues per home fixture.',
        ],

        'upgrades' => [
            'title' => 'Expansion & rebuild',
            'base_capacity' => 'Base capacity',
            'supplementary' => 'Temporary stands',
            'total' => 'Total capacity',
            'seats' => 'seats',
            'seats_total' => 'seats total',
            'seats_to_add' => 'Seats to add',
            'target_capacity' => 'Target capacity',
            'total_cost' => 'Total cost',
            'financing' => 'Financing',
            'financing_cash' => 'Cash up front',
            'financing_loan' => 'Bank loan',
            'financing_cash_hint' => 'Deducted from the available transfer budget on confirmation.',
            'financing_loan_hint' => 'Bank ceiling: :cap. Repaid in 10 annual instalments (flat principal + interest on the outstanding balance).',

            'project_supplementary' => 'Temporary stands under construction',
            'project_rebuild' => 'Stadium rebuild in progress',
            'ready_on' => 'Ready on :date',
            'ready_in_season' => 'Available from the :season season',
            'loan_remaining' => 'Loan outstanding: :amount',

            'cta_supplementary_label' => 'Quick expansion',
            'cta_supplementary_title' => 'Add temporary stands',
            'cta_supplementary_hint' => 'Up to :max extra seats at :cost per seat. Ready in 30 days.',
            'cta_supplementary_full' => 'Maximum temporary stands installed. Rebuild the stadium to free up more headroom.',

            'cta_rebuild_label' => 'Major project',
            'cta_rebuild_title' => 'Rebuild the stadium',
            'cta_rebuild_hint' => 'Build a new stadium up to :max seats at :cost per seat. One construction season at 40% capacity.',
            'cta_rebuild_reputation_lock' => 'You need :tier reputation to unlock stadium rebuilds. Compete and string together strong seasons to climb the ladder.',
            'cta_rebuild_locked_by_reputation' => 'Bank ceiling: :cap (max :max seats). To raise it, reach :tier reputation — it unlocks a higher loan ceiling.',
            'cta_rebuild_locked_by_affordability' => 'Bank ceiling: :cap (max :max seats). The bank only lends what your income can repay. Grow projected revenue to around :revenue per season to unlock a bigger stadium.',
            'cta_rebuild_locked_at_elite' => 'Bank ceiling: :cap (max :max seats). Your reputation is already maxed; grow projected revenue to unlock a larger project.',

            'reputation_tiers' => [
                'local' => 'Local',
                'modest' => 'Modest',
                'established' => 'Established',
                'continental' => 'Continental',
                'elite' => 'Elite',
            ],

            'modal_supplementary_title' => 'Add temporary stands',
            'modal_supplementary_description' => 'Modular installation, quick to fit. Cash payment, no financing.',
            'modal_rebuild_title' => 'Rebuild the stadium',
            'modal_rebuild_description' => 'Pick the target capacity and how to finance it. One construction season (40% capacity) before the new stadium opens.',
            'rebuild_disruption_warning' => 'During the construction season, home-match capacity drops to 40% of the current stadium. Matchday revenue falls accordingly.',
            'commit_supplementary' => 'Confirm stands',
            'commit_rebuild' => 'Start rebuild',
        ],

        'season_tickets' => [
            'title' => 'Pricing',
            'subtitle' => 'Set your season ticket prices for each seating area. Pricing is locked once your first league match has been played.',
            'deadline_notice' => 'Deadline: prices lock once the first league match of the season has been played.',
            'locked_notice' => 'Season tickets are locked for the season. New prices can be set in pre-season next year.',
            'tickets_sold' => 'Tickets sold',
            'predicted_fill' => 'predicted fill',
            'predicted_fill_tooltip' => 'Pricing and your fans\' support both shape season ticket demand.',
            'baseline_price' => 'Baseline',
            'capacity' => 'Capacity',
            'save_button' => 'Save prices',
            'reset_defaults' => 'Reset to defaults',

            'area' => [
                'general'       => 'General',
                'lateral'       => 'Lateral',
                'lateral_alta'  => 'Lateral upper',
                'lateral_baja'  => 'Lateral lower',
                'tribuna'       => 'Main stand',
                'tribuna_alta'  => 'Main stand upper',
                'tribuna_baja'  => 'Main stand lower',
                'fondo_norte'   => 'North end',
                'fondo_sur'     => 'South end',
                'vip'           => 'VIP',
                'palco'         => 'Skybox',
            ],
        ],
    ],

    'reputation' => [
        'current_tier' => 'Current tier',

        'tiers' => 'Reputation tiers',
        'tiers_help_toggle' => 'How do reputation tiers work?',
        'ladder_help' => 'Clubs move up the ladder by finishing high in their league. At the top tiers, reputation decays slightly each season unless backed up with results.',

        'current' => 'Current',

        'qualitative_distance' => [
            'one_strong_season' => 'A strong season would reach :tier.',
            'two_strong_seasons' => 'A couple of strong seasons from :tier.',
            'several_seasons' => 'Several solid seasons from :tier.',
            'long_road' => 'A long road to :tier.',
        ],

        'tier_descriptors' => [
            'local' => 'A small-market club with a devoted local following.',
            'modest' => 'A small club aiming to reach or stay in the top flight.',
            'established' => 'A historic club with years of top-flight experience.',
            'continental' => 'A fixture in European competitions.',
            'elite' => 'A reference point in European football.',
        ],

        'career' => [
            'title' => 'Career so far',
            'seasons_managed' => 'Seasons managed',
            'starting_tier' => 'Starting tier',
            'matches_managed' => 'Matches managed',
            'trophies' => 'Trophies',
        ],

        'trophy_cabinet' => [
            'title' => 'Trophy cabinet',
            'empty' => 'No trophies won with this club yet.',
        ],

        'path_title' => 'Path to the next tier',
        'path_also' => 'Cup titles and European runs also count at season close.',
        'maintenance_note' => 'At this tier, reputation decays slightly each season unless you back it up with results.',
        'projected' => 'Projected',

        'legend' => [
            'forward' => 'Step forward',
            'flat' => 'No progress',
            'setback' => 'Setback',
        ],

        'impact' => [
            'major_leap' => 'Major leap forward',
            'solid_step' => 'Solid step forward',
            'small_step' => 'Small step forward',
            'stalls' => 'Stalls progress',
            'setback' => 'Setback',
        ],

        'history' => [
            'title' => 'Performance history',
            'empty' => 'Your performance history will appear at the end of your first season.',
            'current_suffix' => '(so far)',
            'promoted' => 'Promotion',
            'relegated' => 'Relegation',
            'legend' => [
                'same_tier' => 'Same tier',
            ],
        ],

        'impact_title' => 'What reputation means for your club',
        'impact_signings_title' => 'Attracting signings',
        'impact_signings_body' => 'Higher-profile players are more willing to join more reputable clubs. Free agents, transfer targets and rival sellers all weigh your standing before sitting down to negotiate.',
        'impact_retain_title' => 'Retaining talent',
        'impact_retain_body' => 'Your own squad reacts to reputation too. A rising club holds on to its key players more easily; slipping down the ladder invites poachers and makes renewals harder.',
        'impact_economy_title' => 'Economic opportunities',
        'impact_economy_body' => 'Matchday attendance, ticket pricing and commercial revenue all scale with reputation. Climbing unlocks stronger income across the board; slipping tightens the budget.',

    ],
];
