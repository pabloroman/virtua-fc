<?php

namespace App\Modules\Competition\Contracts;

use Illuminate\Support\Collection;

interface CupDrawPairingStrategy
{
    /**
     * Order teams for sequential pairing in a cup draw.
     *
     * Returns a collection where teams[0] vs teams[1], teams[2] vs teams[3], etc.
     * The total count should be even (drop remainder if odd input).
     *
     * @param  Collection<int, string>  $teams  Team IDs eligible for the round
     * @param  array<string, int>  $teamTierMap  Map of team ID → league tier
     * @return Collection<int, string>
     */
    public function pairTeams(Collection $teams, array $teamTierMap): Collection;
}
