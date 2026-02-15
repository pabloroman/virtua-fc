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
            'cup_entry_round' => 3,
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
                'UCL' => [1, 2, 3, 4, 5],
                'UEL' => [6, 7],
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

        /*
        |----------------------------------------------------------------------
        | Support teams: non-playable teams needed for competition and transfers
        |----------------------------------------------------------------------
        |
        | Categories (initialized in this order during game setup):
        |   1. transfer_pool — foreign league teams for scouting/transfers/loans
        |   2. continental   — opponents in UEFA competitions (reuse pool rosters)
        |
        | Domestic cup teams (ESPCUP lower-division) are linked at seeding time
        | but don't need GamePlayer rosters — early rounds are auto-simulated.
        */
        'support' => [
            'transfer_pool' => [
                // Foreign leagues — full rosters from JSON, eagerly loaded at game setup
                'ENG1' => ['role' => 'foreign', 'handler' => 'league', 'country' => 'GB'],
                'DEU1' => ['role' => 'foreign', 'handler' => 'league', 'country' => 'DE'],
                'FRA1' => ['role' => 'foreign', 'handler' => 'league', 'country' => 'FR'],
                'ITA1' => ['role' => 'foreign', 'handler' => 'league', 'country' => 'IT'],
                // EUR club pool — individual team files, includes NLD/POR teams
                'EUR'  => ['role' => 'foreign', 'handler' => 'team_pool', 'country' => 'EU'],
            ],
            'continental' => [
                // Teams needed for European competitions — rosters reused from
                // tiers + transfer_pool where possible, gaps filled from EUR pool
                'UCL' => ['handler' => 'swiss_format', 'country' => 'EU'],
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
