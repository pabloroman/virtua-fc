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
        'uefa_category' => 'UEFA category',
        'uefa_category_short' => 'UEFA',
        'uefa_category_tooltip' => 'UEFA classifies stadiums into four categories (1 to 4). Moving up a category requires upgrading the facilities (floodlights, dressing rooms, media booths, hospitality areas) and a capacity that clears the next category\'s minimum.',

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

            'project_supplementary' => 'Temporary stands',
            'project_stand_expansion' => 'Stand expansion',
            'project_rebuild' => 'Stadium rebuild',
            'project_uefa_upgrade' => 'UEFA upgrade',
            'ready_on' => 'Ready on :date',
            'ready_in_season' => 'Available from the :season season',
            'loan_remaining' => 'Loan outstanding: :amount',

            'chip_per_seat' => ':cost / seat',
            'chip_per_seat_from' => 'from :cost / seat',
            'chip_time_days' => ':days days',
            'chip_time_seasons' => ':count season|:count seasons',

            'cta_supplementary_label' => 'Quick expansion',
            'cta_supplementary_title' => 'Add temporary stands',
            'cta_supplementary_tagline' => 'Modular bleachers, up to :max extra seats. Removed when you rebuild.',
            'cta_supplementary_full' => 'Maximum temporary stands installed. Rebuild the stadium to free up more headroom.',
            'cta_supplementary_no_budget' => 'Not enough budget for the minimum batch (:minimum). Available budget: :budget.',
            'budget_caps_slider' => 'Available budget (:budget) is capping the batch — without it you could reach :natural seats.',
            'financing_cash_hint_budget' => 'Deducted from the available transfer budget (:budget) on confirmation.',

            'cta_stand_expansion_label' => 'Mid-scale project',
            'cta_stand_expansion_title' => 'Expand a stand',
            'cta_stand_expansion_tagline' => 'Rebuild a single stand for :min–:max permanent seats. No mid-season disruption.',
            'cta_stand_expansion_no_budget' => 'Not enough budget or bank credit for the minimum project (:minimum). Available budget: :budget.',

            'cta_rebuild_label' => 'Major project',
            'cta_rebuild_title' => 'Rebuild the stadium',
            'cta_rebuild_tagline' => 'Brand-new stadium, up to :max seats. Price grows with size; one season at 40% capacity.',
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
            'modal_supplementary_description' => 'Modular bleachers fitted around the existing pitch. Quick to install (30 days) and cash-only, but they look temporary, add no commercial space, and are removed when you rebuild the stadium. Reference: temporary stands used during a stadium reform (≈€6M for ~14k seats).',
            'modal_stand_expansion_title' => 'Expand a stand',
            'modal_stand_expansion_description' => 'Demolish one stand and rebuild it bigger to gain 3,000–12,000 permanent seats. The rest of the stadium stays open during construction, and the new stand is ready at the start of next season. Reference: Liverpool\'s Anfield Road expansion (+7,000 seats).',
            'stand_expansion_disruption_note' => 'The rest of the stadium stays open during construction — no capacity reduction. The new seats go live at the start of next season.',
            'modal_rebuild_title' => 'Rebuild the stadium',
            'modal_rebuild_description' => 'Knock down the existing ground and build a new one. Larger stadiums cost more per seat (built progressively, like tax brackets). One off-season + one construction season (40% capacity) before the new stadium opens. Reference: Metropolitano (Atlético, 70k, €240M), Bernabéu refit (€1.35B).',
            'rebuild_disruption_warning' => 'During the construction season, home-match capacity drops to 40% of the current stadium. Matchday revenue falls accordingly.',
            'rebuild_marginal_rate_prefix' => 'Per seat at this size:',
            'rebuild_marginal_rate_suffix' => '',
            'commit_supplementary' => 'Confirm stands',
            'commit_stand_expansion' => 'Start expansion',
            'commit_rebuild' => 'Start rebuild',
            'commit_uefa_upgrade' => 'Start upgrade',

            'cta_disabled_by_active_project' => 'A project is already in progress. See the history below.',

            'cta_uefa_label' => 'Facility upgrade',
            'cta_uefa_title' => 'Upgrade to UEFA Category :to (from :from)',
            'cta_uefa_title_generic' => 'Upgrade UEFA category',
            'cta_uefa_button' => 'Upgrade facilities',
            'cta_uefa_tagline' => 'Refit the stadium to reach UEFA Category :target. Flat cost :cost, one construction season, no capacity disruption.',
            'cta_uefa_no_budget' => 'Upgrade cost: :cost. Neither budget nor bank credit covers it. Available budget: :budget.',
            'cta_uefa_capacity_floor' => 'To qualify for UEFA Category :target the stadium must exceed :min_cap seats. Expand the capacity first.',
            'cta_uefa_already_max' => 'Your stadium is already at the top UEFA category. No further levels to unlock.',
            'cta_uefa_no_base_level' => 'Your stadium has no UEFA category yet. Expand the capacity first to enter the classification.',

            'modal_uefa_title' => 'Upgrade to UEFA Category :to',
            'modal_uefa_description' => 'Facility refit to meet the next UEFA category requirements (floodlights, dressing rooms, media areas, hospitality and accessibility). Stadium capacity is not affected during construction: the new category is registered at the start of next season.',
            'uefa_transition_label' => 'Category',
            'uefa_no_capacity_change_note' => 'The stadium remains fully operational during construction. The new UEFA category is registered at the start of next season.',
        ],

        'history' => [
            'title' => 'Renovation history',
            'empty' => 'No stadium projects yet.',
            'empty_hint' => 'Past and ongoing renovations will appear here.',
            'col_type' => 'Project',
            'col_detail' => 'Detail',
            'col_cost' => 'Cost',
            'col_status' => 'Status',
            'detail_rebuild' => ':count seats (full stadium)',
            'detail_uefa_upgrade' => 'UEFA Category :from → :to',
            'status_completed' => 'Completed',
            'status_in_progress' => 'In progress',
            'season_label' => 'Season :season',
            'ready_label' => 'Ready :date',
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
