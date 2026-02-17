<?php

namespace App\Game\Events;

use App\Models\Competition;
use App\Models\CupTie;
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
        public readonly ?CupTie $resolvedCupTie = null,
        public readonly ?string $cupTieWinnerId = null,
    ) {}
}
