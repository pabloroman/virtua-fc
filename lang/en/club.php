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
        'capacity_help' => 'Seat count snapshotted at each match for attendance calculations. Capacity expansion becomes a player decision in a later phase.',

        'fan_base' => 'Fan base',
        'fan_base_help' => 'Loyalty rises with trophies and strong finishes and dips after poor seasons. Together with reputation, it drives how full the stadium gets on matchday.',
        'fan_base_trend' => 'Fan trend',
        'current_loyalty' => 'Fan support',

        'last_attendance' => 'Last home match',
        'fill_rate' => 'Fill rate',
        'no_home_match_yet' => 'No home match has been played yet.',

        'matchday_revenue' => 'Matchday revenue',
        'matchday_revenue_help' => 'Projected figure uses the season budgeting formula; actuals land at season settlement. They will diverge once attendance drives matchday revenue directly.',
        'no_finances_yet' => 'Season finances will appear once projections are generated.',
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
