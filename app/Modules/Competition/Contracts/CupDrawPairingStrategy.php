<?php

namespace App\Modules\Competition\Contracts;

use Illuminate\Support\Collection;

interface CupDrawPairingStrategy
{
    /**
     * Order teams for sequential pairing in a cup draw.
     *
     * Returns a collection where teams[0] vs teams[1], teams[2] vs teams[3], etc.
     * Implementations MUST include every input team exactly once in the
     * result — odd inputs must NOT silently discard a team. When the input
     * size is odd, the trailing team is unpaired and the caller is
     * responsible for handling it (e.g. assigning a bye or raising).
     *
     * @param  Collection<int, string>  $teams  Team IDs eligible for the round
     * @param  array<string, int>  $teamTierMap  Map of team ID → league tier
     * @param  array<string, int>  $teamSeedMap  Map of team ID → seed, for the
     *                                           competitions that seed their
     *                                           bracket instead of drawing it.
     *                                           Empty for everything else.
     * @return Collection<int, string>
     */
    public function pairTeams(Collection $teams, array $teamTierMap, array $teamSeedMap = []): Collection;
}
