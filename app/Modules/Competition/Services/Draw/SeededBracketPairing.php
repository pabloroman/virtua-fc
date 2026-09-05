<?php

namespace App\Modules\Competition\Services\Draw;

use App\Modules\Competition\Contracts\CupDrawPairingStrategy;
use Illuminate\Support\Collection;

/**
 * Supercup-style bracket: the ties are not drawn, they follow from how each
 * club qualified.
 *
 * Clubs carry a seed on their competition entry (1 = best). Standard bracket
 * pairing puts the top seed against the bottom one and works inwards:
 * 1 v 4, 2 v 3. For Spain's Supercopa, whose seeds are cup winner, cup
 * runner-up, league champion, league runner-up in that order, this produces
 * the RFEF fixtures — cup winner v league runner-up and cup runner-up v
 * league champion — without the pairing having to know any of those roles.
 *
 * Unseeded clubs sort last (and shuffled among themselves), so a field that
 * lost its seeds degrades to a random draw rather than a crash.
 */
class SeededBracketPairing implements CupDrawPairingStrategy
{
    public function pairTeams(Collection $teams, array $teamTierMap, array $teamSeedMap = []): Collection
    {
        $seeded = $teams->filter(fn (string $id) => isset($teamSeedMap[$id]))
            ->sortBy(fn (string $id) => $teamSeedMap[$id])
            ->values();

        $unseeded = $teams->reject(fn (string $id) => isset($teamSeedMap[$id]))
            ->shuffle()
            ->values();

        $ordered = $seeded->concat($unseeded);

        // Walk the bracket from both ends: best against worst, inwards.
        $paired = collect();
        $top = 0;
        $bottom = $ordered->count() - 1;
        while ($top < $bottom) {
            $paired->push($ordered[$top++]);
            $paired->push($ordered[$bottom--]);
        }

        // Odd field: the median club is left unpaired for the caller.
        if ($top === $bottom) {
            $paired->push($ordered[$top]);
        }

        return $paired;
    }
}
