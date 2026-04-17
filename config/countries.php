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
            3 => [
                'competition' => 'ESP3A',
                'teams' => 20,
                'handler' => 'league_with_playoff',
                'config_class' => \App\Modules\Competition\Configs\PrimeraRFEFConfig::class,
                // Primera RFEF has two parallel groups of 20 teams. ESP3A is
                // the "primary" entry so existing call sites that expect one
                // competition per tier continue to work; ESP3B is enumerated
                // via CountryConfig::tierCompetitionIds()/siblings.
                'siblings' => [
                    [
                        'competition' => 'ESP3B',
                        'teams' => 20,
                        'handler' => 'league_with_playoff',
                        'config_class' => \App\Modules\Competition\Configs\PrimeraRFEFConfig::class,
                    ],
                ],
            ],
        ],

        'domestic_cups' => [
            'ESPCUP' => [
                'handler' => 'knockout_cup',
                'config_class' => \App\Modules\Competition\Configs\KnockoutCupConfig::class,
                'draw_pairing' => \App\Modules\Competition\Services\Draw\CrossCategoryPairing::class,
            ],
            'ESPSUP' => [
                'handler' => 'knockout_cup',
            ],
        ],

        // Promotion playoffs are knockout cup competitions populated at the
        // end of a regular season, used to decide the last promotion spots
        // in formats that don't fit a single-league-with-playoff shape.
        // Primera RFEF (ESP3) uses ESP3PO to mix the top finishers of its
        // two groups into a shared 8-team bracket.
        'promotion_playoffs' => [
            'ESP3PO' => [
                'handler' => 'knockout_cup',
                'config_class' => \App\Modules\Competition\Configs\PrimeraRFEFPlayoffConfig::class,
                'parent_tier' => 3,
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
            [
                // ESP2 ↔ Primera RFEF (ESP3A + ESP3B + ESP3PO).
                //
                // 'bottom_division' is nominal — the real swap logic lives in
                // PrimeraRFEFPromotionRule which implements SelfSwappingPromotionRule
                // and handles promotion from three feeder competitions plus
                // redistribution of relegated teams across two groups.
                'top_division' => 'ESP2',
                'bottom_division' => 'ESP3A',
                'relegated_positions' => [19, 20, 21, 22],
                'direct_promotion_positions' => [1],
                'playoff_positions' => [2, 3, 4, 5],
                'rule_class' => \App\Modules\Competition\Promotions\PrimeraRFEFPromotionRule::class,
                'playoff_generator' => \App\Modules\Competition\Playoffs\PrimeraRFEFPlayoffGenerator::class,
                // Register the same playoff generator under both groups so
                // whichever one the player is in triggers the playoff draw.
                'playoff_source_divisions' => ['ESP3A', 'ESP3B'],
                // Cup ties and match rows are stored under ESP3PO rather than
                // the feeder groups — that's how ESP3PO becomes a standalone
                // competition for the bracket phase.
                'playoff_competition' => 'ESP3PO',
            ],
        ],

        // Reserve teams that cannot be promoted to the same division as their parent.
        // Maps child transfermarkt_id => parent transfermarkt_id.
        'reserve_teams' => [
            9899 => 681,   // Real Sociedad B → Real Sociedad
            6767 => 418,   // Real Madrid Castilla → Real Madrid
            8516 => 331,   // CA Osasuna Promesas → CA Osasuna
            6688 => 621,   // Bilbao Athletic → Athletic Bilbao
            8733 => 940,   // RC Celta Fortuna → Celta de Vigo
            11972 => 1050, // Villarreal CF B → Villarreal CF
            8519 => 368,   // Sevilla Atlético → Sevilla FC
            3679 => 13,    // Atlético Madrileño → Atlético de Madrid
            2865 => 150,   // Betis Deportivo Balompié → Real Betis Balompié
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
            'UEFASUP' => [
                'config_class' => \App\Modules\Competition\Configs\UefaSuperCupConfig::class,
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
                'UECL' => ['handler' => 'swiss_format', 'country' => 'EU'],
                // One-off knockout: prior UCL winner vs prior UEL winner, single leg with ET+penalties
                'UEFASUP' => ['handler' => 'knockout_cup', 'country' => 'EU'],
            ],
        ],
    ],

    'EN' => [
        'name' => 'England',

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
            'UEFASUP' => [
                'config_class' => \App\Modules\Competition\Configs\UefaSuperCupConfig::class,
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
                'UECL' => ['handler' => 'swiss_format', 'country' => 'EU'],
                'UEFASUP' => ['handler' => 'knockout_cup', 'country' => 'EU'],
            ],
        ],
    ],

    'DE' => [
        'name' => 'Deutschland',

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
            'UEFASUP' => [
                'config_class' => \App\Modules\Competition\Configs\UefaSuperCupConfig::class,
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
                'UECL' => ['handler' => 'swiss_format', 'country' => 'EU'],
                'UEFASUP' => ['handler' => 'knockout_cup', 'country' => 'EU'],
            ],
        ],
    ],

    'IT' => [
        'name' => 'Italia',

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
            'UEFASUP' => [
                'config_class' => \App\Modules\Competition\Configs\UefaSuperCupConfig::class,
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
                'UECL' => ['handler' => 'swiss_format', 'country' => 'EU'],
                'UEFASUP' => ['handler' => 'knockout_cup', 'country' => 'EU'],
            ],
        ],
    ],

    'FR' => [
        'name' => 'France',

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
            'UEFASUP' => [
                'config_class' => \App\Modules\Competition\Configs\UefaSuperCupConfig::class,
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
                'UECL' => ['handler' => 'swiss_format', 'country' => 'EU'],
                'UEFASUP' => ['handler' => 'knockout_cup', 'country' => 'EU'],
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

];
