<?php

namespace App\Modules\Match\Events;

use App\Models\Competition;
use App\Models\Game;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired once per (game, competition) when the league phase of a Swiss-format
 * UEFA competition finishes — i.e. all 8 matchdays have been played and final
 * standings are known. Used to award the qualification bonus before the
 * knockout phase begins.
 */
class LeaguePhaseCompleted
{
    use Dispatchable;

    public function __construct(
        public readonly Game $game,
        public readonly Competition $competition,
    ) {}
}
