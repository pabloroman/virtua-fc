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
        'fan_base_help' => 'Fan loyalty moves season to season based on on-pitch outcomes. It drives stadium occupancy alongside reputation, which sets ticket prices. The tick marks the curated anchor the club was seeded with.',
        'fan_base_trend' => 'Fan trend',
        'current_loyalty' => 'Current loyalty',
        'anchor' => 'Anchor',
        'loyalty_rising' => 'Rising',
        'loyalty_stable' => 'Stable',
        'loyalty_declining' => 'Declining',

        'last_attendance' => 'Last home match',
        'fill_rate' => 'Fill rate',
        'no_home_match_yet' => 'No home match has been played yet.',

        'matchday_revenue' => 'Matchday revenue',
        'matchday_revenue_help' => 'Projected figure uses the season budgeting formula; actuals land at season settlement. They will diverge once attendance drives matchday revenue directly.',
        'no_finances_yet' => 'Season finances will appear once projections are generated.',
    ],

    'reputation' => [
        'current_tier' => 'Current tier',
        'points' => 'Reputation points',
        'trend' => 'Projected trend',

        'tiers' => 'Reputation tiers',
        'ladder_help' => 'Clubs move up the ladder by finishing high in their league; elite and continental clubs must offset tier gravity every season or drift back down. A club can never fall more than two tiers below its seeded anchor.',

        'current' => 'Current',
        'anchor' => 'Anchor',
        'floor' => 'Floor',
        'threshold' => 'Threshold',

        'progress' => 'Tier progress',
        'points_to_next' => ':points points to :tier',
        'at_top_tier' => 'Top of the ladder — no tier above this.',

        'season_projection' => 'Season-end projection',
        'current_position' => 'Current position',
        'position_points' => 'Position points',
        'gravity' => 'Tier gravity',
        'net_change' => 'Net change',
        'projection_help' => 'Assumes the season ends with the club at its current league position. Trophies and cup runs add on top at season close.',
        'no_standing_yet' => 'League standings appear once the season is underway.',
    ],
];
