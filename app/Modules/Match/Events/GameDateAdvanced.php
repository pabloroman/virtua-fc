<?php

namespace App\Modules\Match\Events;

use App\Models\Game;
use Carbon\Carbon;
use Illuminate\Foundation\Events\Dispatchable;

class GameDateAdvanced
{
    use Dispatchable;

    public function __construct(
        public readonly Game $game,
        public readonly Carbon $previousDate,
        public readonly Carbon $newDate,
    ) {}
}
