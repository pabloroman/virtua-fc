<?php

namespace App\Modules\Match\Events;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GameMatch;
use Illuminate\Foundation\Events\Dispatchable;

class MatchFinalized
{
    use Dispatchable;

    public function __construct(
        public readonly GameMatch $match,
        public readonly Game $game,
        public readonly ?Competition $competition = null,
    ) {}
}
