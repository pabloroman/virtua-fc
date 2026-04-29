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
 *
 * Odd inputs: every team must appear in the output. When the input size is
 * odd, the surplus team falls into the lower half and is appended at the
 * end of the result, leaving it unpaired for the caller to handle.
 * Earlier versions used `slice($half, $half)` for the lower half, which
 * silently dropped the median team and propagated downstream as missing
 * cup ties (one team disappeared per draw with an odd team pool).
 */
class CrossCategoryPairing implements CupDrawPairingStrategy
{
    public function pairTeams(Collection $teams, array $teamTierMap): Collection
    {
        $sorted = $teams
            ->sort(fn ($a, $b) => ($teamTierMap[$a] ?? 99) <=> ($teamTierMap[$b] ?? 99))
            ->values();

        $count = $sorted->count();
        $upperSize = intdiv($count, 2);

        // Higher-ranked teams (lower tier numbers) populate the upper half.
        // Slice without an explicit length so the lower half absorbs every
        // remaining team — including the median when $count is odd.
        $higherHalf = $sorted->slice(0, $upperSize)->shuffle()->values();
        $lowerHalf = $sorted->slice($upperSize)->shuffle()->values();

        $paired = collect();

        for ($i = 0; $i < $upperSize; $i++) {
            $paired->push($higherHalf[$i]);
            $paired->push($lowerHalf[$i]);
        }

        // Append any surplus from the lower half (only triggers when
        // $count is odd — exactly one team remains).
        for ($i = $upperSize; $i < $lowerHalf->count(); $i++) {
            $paired->push($lowerHalf[$i]);
        }

        return $paired;
    }
}
