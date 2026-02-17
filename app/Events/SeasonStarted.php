<?php

namespace App\Events;

use App\Models\Game;

class SeasonStarted
{
    public function __construct(
        public readonly Game $game,
    ) {}
}
