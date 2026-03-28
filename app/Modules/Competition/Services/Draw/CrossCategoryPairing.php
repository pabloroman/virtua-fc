<?php

namespace App\Modules\Competition\Services\Draw;

use App\Modules\Competition\Contracts\CupDrawPairingStrategy;
use Illuminate\Support\Collection;

/**
 * Copa del Rey–style draw: pair lower-category clubs against higher-category
 * clubs as much as possible.
 *
 * Teams are sorted by league tier, split into two halves (higher-category
 * and lower-category), shuffled within each half, then interleaved so that
 * sequential pairing produces cross-category matchups.
 */
class CrossCategoryPairing implements CupDrawPairingStrategy
{
    public function pairTeams(Collection $teams, array $teamTierMap): Collection
    {
        $sorted = $teams
            ->sort(fn ($a, $b) => ($teamTierMap[$a] ?? 99) <=> ($teamTierMap[$b] ?? 99))
            ->values();

        $half = intdiv($sorted->count(), 2);

        $higherHalf = $sorted->slice(0, $half)->shuffle()->values();
        $lowerHalf = $sorted->slice($half, $half)->shuffle()->values();

        $paired = collect();

        for ($i = 0; $i < $half; $i++) {
            $paired->push($higherHalf[$i]);
            $paired->push($lowerHalf[$i]);
        }

        return $paired;
    }
}
