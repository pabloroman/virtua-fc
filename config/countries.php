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
                'config_class' => \App\Modules\Competition\Configs\LaLigaConfig::class,
            ],
            2 => [
                'competition' => 'ESP2',
                'teams' => 22,
                'handler' => 'league_with_playoff',
                'config_class' => \App\Modules\Competition\Configs\LaLiga2Config::class,
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
                'playoff_generator' => \App\Modules\Competition\Playoffs\ESP2PlayoffGenerator::class,
            ],
        ],

        'continental_slots' => [
            'ESP1' => [
                'UCL' => [1, 2, 3, 4, 5],
                'UEL' => [6],
                'UECL' => [7],
            ],
        ],

        'cup_winner_slot' => [
            'cup' => 'ESPCUP',
            'competition' => 'UEL',
            'league' => 'ESP1',
        ],

        'continental_competitions' => [
            'UCL' => [
                'config_class' => \App\Modules\Competition\Configs\ChampionsLeagueConfig::class,
            ],
            'UEL' => [
                'config_class' => \App\Modules\Competition\Configs\EuropaLeagueConfig::class,
            ],
            'UECL' => [
                'config_class' => \App\Modules\Competition\Configs\ConferenceLeagueConfig::class,
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
                // Other top-flight leagues — full rosters from JSON, eagerly loaded at game setup
                'ENG1' => ['role' => 'league', 'handler' => 'league', 'country' => 'EN'],
                'DEU1' => ['role' => 'league', 'handler' => 'league', 'country' => 'DE'],
                'FRA1' => ['role' => 'league', 'handler' => 'league', 'country' => 'FR'],
                'ITA1' => ['role' => 'league', 'handler' => 'league', 'country' => 'IT'],
                // EUR club pool — individual team files, includes NLD/POR teams
                'EUR'  => ['role' => 'team_pool', 'handler' => 'team_pool', 'country' => 'EU'],
            ],
            'continental' => [
                // Teams needed for European competitions — rosters reused from
                // tiers + transfer_pool where possible, gaps filled from EUR pool
                'UCL' => ['handler' => 'swiss_format', 'country' => 'EU'],
                'UEL' => ['handler' => 'swiss_format', 'country' => 'EU'],
            ],
        ],
    ],

    'EN' => [
        'name' => 'England',
        'flag' => 'gb-eng',

        'tiers' => [
            1 => [
                'competition' => 'ENG1',
                'teams' => 20,
                'handler' => 'league',
                'config_class' => \App\Modules\Competition\Configs\PremierLeagueConfig::class,
            ],
        ],

        'domestic_cups' => [],
        'promotions' => [],

        'continental_slots' => [
            'ENG1' => [
                'UCL' => [1, 2, 3, 4],
                'UEL' => [5],
                'UECL' => [6],
            ],
        ],

        'cup_winner_slot' => null,

        'continental_competitions' => [
            'UCL' => [
                'config_class' => \App\Modules\Competition\Configs\ChampionsLeagueConfig::class,
            ],
            'UEL' => [
                'config_class' => \App\Modules\Competition\Configs\EuropaLeagueConfig::class,
            ],
            'UECL' => [
                'config_class' => \App\Modules\Competition\Configs\ConferenceLeagueConfig::class,
            ],
        ],

        'support' => [
            'transfer_pool' => [
                'ESP1' => ['role' => 'league', 'handler' => 'league', 'country' => 'ES'],
                'DEU1' => ['role' => 'league', 'handler' => 'league', 'country' => 'DE'],
                'FRA1' => ['role' => 'league', 'handler' => 'league', 'country' => 'FR'],
                'ITA1' => ['role' => 'league', 'handler' => 'league', 'country' => 'IT'],
                'EUR'  => ['role' => 'team_pool', 'handler' => 'team_pool', 'country' => 'EU'],
            ],
            'continental' => [
                'UCL' => ['handler' => 'swiss_format', 'country' => 'EU'],
                'UEL' => ['handler' => 'swiss_format', 'country' => 'EU'],
            ],
        ],
    ],

    'DE' => [
        'name' => 'Deutschland',
        'flag' => 'de',

        'tiers' => [
            1 => [
                'competition' => 'DEU1',
                'teams' => 18,
                'handler' => 'league',
                'config_class' => \App\Modules\Competition\Configs\BundesligaConfig::class,
            ],
        ],

        'domestic_cups' => [],
        'promotions' => [],

        'continental_slots' => [
            'DEU1' => [
                'UCL' => [1, 2, 3, 4],
                'UEL' => [5, 6],
                'UECL' => [7],
            ],
        ],

        'cup_winner_slot' => null,

        'continental_competitions' => [
            'UCL' => [
                'config_class' => \App\Modules\Competition\Configs\ChampionsLeagueConfig::class,
            ],
            'UEL' => [
                'config_class' => \App\Modules\Competition\Configs\EuropaLeagueConfig::class,
            ],
            'UECL' => [
                'config_class' => \App\Modules\Competition\Configs\ConferenceLeagueConfig::class,
            ],
        ],

        'support' => [
            'transfer_pool' => [
                'ESP1' => ['role' => 'league', 'handler' => 'league', 'country' => 'ES'],
                'ENG1' => ['role' => 'league', 'handler' => 'league', 'country' => 'EN'],
                'FRA1' => ['role' => 'league', 'handler' => 'league', 'country' => 'FR'],
                'ITA1' => ['role' => 'league', 'handler' => 'league', 'country' => 'IT'],
                'EUR'  => ['role' => 'team_pool', 'handler' => 'team_pool', 'country' => 'EU'],
            ],
            'continental' => [
                'UCL' => ['handler' => 'swiss_format', 'country' => 'EU'],
                'UEL' => ['handler' => 'swiss_format', 'country' => 'EU'],
            ],
        ],
    ],

    'IT' => [
        'name' => 'Italia',
        'flag' => 'it',

        'tiers' => [
            1 => [
                'competition' => 'ITA1',
                'teams' => 20,
                'handler' => 'league',
                'config_class' => \App\Modules\Competition\Configs\SerieAConfig::class,
            ],
        ],

        'domestic_cups' => [],
        'promotions' => [],

        'continental_slots' => [
            'ITA1' => [
                'UCL' => [1, 2, 3, 4, 5],
                'UEL' => [6],
                'UECL' => [7],
            ],
        ],

        'cup_winner_slot' => null,

        'continental_competitions' => [
            'UCL' => [
                'config_class' => \App\Modules\Competition\Configs\ChampionsLeagueConfig::class,
            ],
            'UEL' => [
                'config_class' => \App\Modules\Competition\Configs\EuropaLeagueConfig::class,
            ],
            'UECL' => [
                'config_class' => \App\Modules\Competition\Configs\ConferenceLeagueConfig::class,
            ],
        ],

        'support' => [
            'transfer_pool' => [
                'ESP1' => ['role' => 'league', 'handler' => 'league', 'country' => 'ES'],
                'ENG1' => ['role' => 'league', 'handler' => 'league', 'country' => 'EN'],
                'DEU1' => ['role' => 'league', 'handler' => 'league', 'country' => 'DE'],
                'FRA1' => ['role' => 'league', 'handler' => 'league', 'country' => 'FR'],
                'EUR'  => ['role' => 'team_pool', 'handler' => 'team_pool', 'country' => 'EU'],
            ],
            'continental' => [
                'UCL' => ['handler' => 'swiss_format', 'country' => 'EU'],
                'UEL' => ['handler' => 'swiss_format', 'country' => 'EU'],
            ],
        ],
    ],

    'FR' => [
        'name' => 'France',
        'flag' => 'fr',

        'tiers' => [
            1 => [
                'competition' => 'FRA1',
                'teams' => 18,
                'handler' => 'league',
                'config_class' => \App\Modules\Competition\Configs\Ligue1Config::class,
            ],
        ],

        'domestic_cups' => [],
        'promotions' => [],

        'continental_slots' => [
            'FRA1' => [
                'UCL' => [1, 2, 3],
                'UEL' => [4],
                'UECL' => [5],
            ],
        ],

        'cup_winner_slot' => null,

        'continental_competitions' => [
            'UCL' => [
                'config_class' => \App\Modules\Competition\Configs\ChampionsLeagueConfig::class,
            ],
            'UEL' => [
                'config_class' => \App\Modules\Competition\Configs\EuropaLeagueConfig::class,
            ],
            'UECL' => [
                'config_class' => \App\Modules\Competition\Configs\ConferenceLeagueConfig::class,
            ],
        ],

        'support' => [
            'transfer_pool' => [
                'ESP1' => ['role' => 'league', 'handler' => 'league', 'country' => 'ES'],
                'ENG1' => ['role' => 'league', 'handler' => 'league', 'country' => 'EN'],
                'DEU1' => ['role' => 'league', 'handler' => 'league', 'country' => 'DE'],
                'ITA1' => ['role' => 'league', 'handler' => 'league', 'country' => 'IT'],
                'EUR'  => ['role' => 'team_pool', 'handler' => 'team_pool', 'country' => 'EU'],
            ],
            'continental' => [
                'UCL' => ['handler' => 'swiss_format', 'country' => 'EU'],
                'UEL' => ['handler' => 'swiss_format', 'country' => 'EU'],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | World Cup (Tournament Mode)
    |--------------------------------------------------------------------------
    |
    | The World Cup uses the main teams/players tables with type='national'.
    | Game creation in tournament mode reads from these teams and the WC2026
    | competition via competition_teams, sharing Player records with career mode.
    |
    */

    'WC' => [
        'name' => 'Copa del Mundo',
        'flag' => 'wc',
        'tournament' => true,

        'tiers' => [
            1 => [
                'competition' => 'WC2026',
                'teams' => 48,
                'handler' => 'world_cup',
            ],
        ],

        'domestic_cups' => [],
        'promotions' => [],
        'continental_slots' => [],
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
