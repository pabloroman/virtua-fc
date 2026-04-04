<?php

namespace App\Modules\Competition\Services\Draw;

use App\Modules\Competition\Contracts\CupDrawPairingStrategy;
use Illuminate\Support\Collection;

class RandomPairing implements CupDrawPairingStrategy
{
    public function pairTeams(Collection $teams, array $teamTierMap): Collection
    {
        return $teams->shuffle()->values();
    }
}
