<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Football Country Configurations
    |--------------------------------------------------------------------------
    |
    | Each country declares its full football ecosystem: playable league tiers,
    | domestic cups, promotion/relegation rules, continental qualification slots,
    | and support teams needed for transfers and continental competitions.
    |
    | This config is the single source of truth for country-specific setup.
    | Processors, seeders, and game creation all read from here.
    |
    */

    'ES' => [
        'name' => 'España',
        'flag' => 'es',

        'tiers' => [
            1 => [
                'competition' => 'ESP1',
                'teams' => 20,
                'handler' => 'league',
                'config_class' => \App\Game\Competitions\LaLigaConfig::class,
            ],
            2 => [
                'competition' => 'ESP2',
                'teams' => 22,
                'handler' => 'league_with_playoff',
                'config_class' => \App\Game\Competitions\LaLiga2Config::class,
            ],
        ],

        'domestic_cups' => [
            'ESPCUP' => [
                'handler' => 'knockout_cup',
            ],
            'ESPSUP' => [
                'handler' => 'knockout_cup',
            ],
        ],

        'supercup' => [
            'competition' => 'ESPSUP',
            'cup' => 'ESPCUP',
            'league' => 'ESP1',
            'cup_final_round' => 7,
        ],

        'promotions' => [
            [
                'top_division' => 'ESP1',
                'bottom_division' => 'ESP2',
                'relegated_positions' => [18, 19, 20],
                'direct_promotion_positions' => [1, 2],
                'playoff_positions' => [3, 4, 5, 6],
                'playoff_generator' => \App\Game\Playoffs\ESP2PlayoffGenerator::class,
            ],
        ],

        'continental_slots' => [
            'ESP1' => [
                'UCL' => [1, 2, 3, 4],
                'UEL' => [5, 6],
            ],
        ],

        'continental_competitions' => [
            'UCL' => [
                'config_class' => \App\Game\Competitions\ChampionsLeagueConfig::class,
            ],
            'UEL' => [
                'config_class' => \App\Game\Competitions\EuropaLeagueConfig::class,
            ],
            'UECL' => [
                'config_class' => \App\Game\Competitions\ConferenceLeagueConfig::class,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Test Country (used by test suite)
    |--------------------------------------------------------------------------
    */

    'XX' => [
        'name' => 'Test Country',
        'flag' => 'xx',

        'tiers' => [
            1 => [
                'competition' => 'TEST1',
                'teams' => 4,
                'handler' => 'league',
            ],
        ],

        'domestic_cups' => [
            'TESTCUP' => [
                'handler' => 'knockout_cup',
            ],
        ],

        'promotions' => [],
        'continental_slots' => [],
    ],

];
