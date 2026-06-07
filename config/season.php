<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Current Base Season
    |--------------------------------------------------------------------------
    |
    | The base season for newly-seeded reference data and the season in which
    | freshly created careers begin. This governs which `data/{season}/` folder
    | the seeder and helper commands read from, and the default season for
    | template generation.
    |
    | At runtime, fixtures are generated from `Competition::season` (written to
    | the DB at seed time) and dates are offset by year as a game progresses, so
    | bumping this value and dropping in a matching `data/{season}/` folder is
    | all that is needed to move newly seeded databases to a new season.
    |
    | Note: each competition's `teams.json` carries its own `seasonID`, which is
    | the authority for the DB `competitions.season` column. Keep this value and
    | the `seasonID` in the season's data files in agreement.
    |
    */

    'current' => env('GAME_SEASON', '2025'),

];
