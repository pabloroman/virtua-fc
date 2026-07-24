<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tournament Mode (World Cup 2026)
    |--------------------------------------------------------------------------
    |
    | Controls whether new saves can be created in tournament mode. Turned off
    | once the real-world World Cup 2026 ended. Existing tournament saves keep
    | working regardless of this flag — it only gates new-game creation. Flip
    | it on (and point tournament setup at a new competition) to bring a
    | tournament back in the future.
    |
    */

    'tournament_mode_enabled' => (bool) env('TOURNAMENT_MODE_ENABLED', false),

];
