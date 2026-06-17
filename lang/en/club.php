<?php

return [
    'hub_title' => 'Club',

    'nav' => [
        'finances' => 'Finances',
        'investment' => 'Personnel',
        'stadium' => 'Stadium',
        'commercial' => 'Commercial',
        'reputation' => 'Reputation',
    ],

    'commercial' => [
        'title' => 'Commercial sponsorships',
        'intro' => 'Seek sponsors to grow recurring income that strengthens the club budget.',
        'naming_rights_title' => 'Stadium naming rights',
        'seek_explainer' => 'Hire an agency to canvass sponsors. Each search costs :fee and you must wait :days days between searches.',
        'seek_button' => 'Seek sponsors (:fee)',
        'seek_cooldown' => '{1} You can search again in :days day.|[2,*] You can search again in :days days.',
        'seek_unaffordable' => 'You can\'t afford the agency fee (:fee).',
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
            'completion_date' => 'Completion date',
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

            'tier_label' => 'Tier :n',
            'from_total' => 'From :total',
            'per_seat_inline' => ':cost / seat',
            'time_days_inline' => ':days days',
            'time_months_inline' => ':count month|:count months',
            'status_available' => 'Available',
            'status_locked' => 'Locked',
            'status_in_progress' => 'Under works',
            'cta_planificar' => 'Plan →',
            'unlock_with_revenue' => 'Unlock with :revenue in annual revenue',
            'unlock_with_reputation' => 'Unlock at :tier reputation',
            'unlock_progress_label' => 'Current revenue: :current',

            'cta_supplementary_full_short' => 'Temporary stands maxed out. Rebuild the stadium to free up headroom.',
            'cta_locked_no_budget' => 'Unlock with :cost. Available budget: :budget.',

            'budget_caps_slider' => 'Available budget (:budget) is capping the batch — without it you could reach :natural seats.',
            'financing_cash_hint_budget' => 'Deducted from the available transfer budget (:budget) on confirmation.',

            'cta_supplementary_label' => 'Expansion',
            'cta_supplementary_title' => 'Add temporary stands',

            'cta_stand_expansion_label' => 'Expansion',
            'cta_stand_expansion_title' => 'Expand a stand',

            'cta_rebuild_label' => 'Rebuild',
            'cta_rebuild_title' => 'Rebuild the stadium',

            'reputation_tiers' => [
                'local' => 'Local',
                'modest' => 'Modest',
                'established' => 'Established',
                'continental' => 'Continental',
                'elite' => 'Elite',
            ],

            'modal_supplementary_title' => 'Add temporary stands',
            'modal_supplementary_description' => 'Temporary modular bleachers: quick (30 days) and cash-only, but add no commercial space and are removed when you rebuild.',
            'modal_stand_expansion_title' => 'Expand a stand',
            'modal_stand_expansion_description' => 'Demolish one stand and rebuild it bigger. The new seats are permanent, unlike supplementary bleachers.',
            'modal_rebuild_title' => 'Rebuild the stadium',
            'modal_rebuild_description' => 'Knock down the existing ground and build a new one. Per-seat cost rises in brackets: the bigger the stadium, the more each additional seat costs. The new stadium ships at the highest UEFA category its size qualifies for, at no extra cost.',
            'rebuild_marginal_rate_prefix' => 'Per seat at this size:',
            'rebuild_marginal_rate_suffix' => '',
            'rebuild_cap_explainer_reputation' => 'The cap comes from the largest stadium loan your club qualifies for (:cap). Your current reputation (:tier) sets that ceiling — climb to the next tier to unlock a larger loan.',
            'rebuild_cap_explainer_affordability' => 'The cap comes from the largest stadium loan your club qualifies for (:cap), sized against your projected annual revenue. Grow your revenue to unlock a larger loan.',
            'commit_project' => 'Start construction',

            'cta_disabled_by_active_project' => 'A project is already in progress. See the history below.',

            'cta_uefa_label' => 'Refit',
            'cta_uefa_title' => 'Upgrade to UEFA Category :to (from :from)',
            'cta_uefa_title_generic' => 'Upgrade UEFA category',
            'cta_uefa_button' => 'Upgrade facilities',
            'cta_uefa_tagline' => 'Refit the stadium to reach UEFA Category :target. Flat cost :cost, ~9 months of works, no capacity disruption.',
            'cta_uefa_capacity_floor' => 'To qualify for UEFA Category :target the stadium must exceed :min_cap seats. Expand the capacity first.',
            'cta_uefa_already_max' => 'Your stadium is already at the top UEFA category. No further levels to unlock.',
            'cta_uefa_no_base_level' => 'Your stadium has no UEFA category yet. Expand the capacity first to enter the classification.',

            'modal_uefa_title' => 'Upgrade to UEFA Category :to',
            'modal_uefa_description' => 'Facility refit to meet the next UEFA category requirements (floodlights, dressing rooms, media areas, hospitality and accessibility). Stadium capacity is not affected during construction: the new category is registered at the start of next season.',
            'uefa_transition_label' => 'Category',
        ],

        'history' => [
            'title' => 'Renovation history',
            'empty' => 'No stadium projects yet.',
            'empty_hint' => 'Past and ongoing renovations will appear here.',
            'col_type' => 'Project',
            'col_detail' => 'Details',
            'col_cost' => 'Cost',
            'col_status' => 'Status',
            'detail_seats' => ':count seats',
            'detail_rebuild' => ':count seats (full stadium)',
            'detail_uefa_upgrade' => 'UEFA Category :from → :to',
            'status_completed' => 'Completed',
            'status_in_progress' => 'In progress',
            'season_label' => 'Season :season',
            'ready_label' => 'Ready :date',
        ],

        'season_tickets' => [
            'title' => 'Pricing',
            'subtitle' => 'Pick a pricing policy for your season tickets. Lower prices fill more of the ground; higher prices earn more per seat. Locked once your first league match has been played.',
            'deadline_notice' => 'Deadline: prices lock once the first league match of the season has been played.',
            'locked_notice' => 'Season tickets are locked for the season. New prices can be set in pre-season next year.',
            'tickets_sold' => 'Tickets sold',
            'projected_season_tickets' => 'Projected season tickets',
            'projected_season_tickets_tooltip' => 'Season tickets you expect to sell (paid up front). Match-day attendance differs — it adds walk-up buyers and subtracts no-show holders.',
            'of_capacity' => 'of capacity',
            'matchday_occupancy' => 'match-day occupancy',
            'save_button' => 'Save',
            'preset' => [
                'accessible' => 'Accessible',
                'standard' => 'Standard',
                'premium' => 'Premium',
            ],
            'preset_hint' => [
                'accessible' => 'Cheaper, fuller stadium.',
                'standard' => 'Baseline prices.',
                'premium' => 'Pricier, lower attendance.',
            ],
        ],

        'identity' => [
            'subtitle' => 'Rename your ground for free (once per season, in pre-season). Selling the name to a sponsor is handled on the Commercial page.',
            'sponsor_owns_name' => 'A sponsor (:sponsor) owns the stadium name until the deal expires, so it can\'t be renamed.',
            'manage_in_commercial' => 'Manage in Commercial',
            'sell_naming_rights' => 'Sell the naming rights',
        ],

        'naming_rights' => [
            'title' => 'Stadium identity & naming rights',
            'current_name' => 'Current name',
            'source_historic' => 'Historic',
            'source_custom' => 'Renamed',
            'source_sponsor' => 'Sponsored',

            'seasons_remaining' => '{1} :count season left|[2,*] :count seasons left',

            'offers_title' => 'Sponsor offers',
            'becomes' => 'Stadium becomes “:name”',
            'annual_value' => 'Annual value',
            'contract_length' => 'Contract',
            'seasons' => '{1} :count season|[2,*] :count seasons',
            'accept_button' => 'Accept deal',
            'accept_confirm' => 'Sell the naming rights to :sponsor? This locks the stadium name for the contract and dents fan support.',
            'renewal_badge' => 'Renewal',
            'renew_button' => 'Renew deal',
            'renew_confirm' => 'Renew the deal with :sponsor? It keeps the stadium name with no fan-support cost.',

            'rename_button' => 'Rename stadium',
            'rename_placeholder' => 'New stadium name',
            'rename_save' => 'Save name',
            'rename_locked_season' => 'The stadium has already been renamed this season.',

            'window_closed_notice' => 'Stadium identity is set in pre-season. Naming deals and renames reopen before next season’s first league match.',
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
