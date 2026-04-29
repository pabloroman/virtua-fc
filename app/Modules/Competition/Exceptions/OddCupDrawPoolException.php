<?php

namespace App\Modules\Competition\Exceptions;

use RuntimeException;

/**
 * Thrown when a cup draw is attempted with an odd number of teams. A
 * knockout draw always pairs teams 2-by-2, so an odd input has no valid
 * resolution short of giving one team a bye — and our schema currently
 * has no representation for byes. We surface the failure here so the
 * upstream cause (cup-size invariant violated, supercup bump didn't
 * apply, etc.) can be diagnosed instead of silently dropping a team.
 *
 * Replaces a silent `for ($i + 1 < $count; $i += 2)` truncation in
 * CupDrawService::getPairedTeamsForRound that produced 93 broken Copa
 * del Rey draws in production (semifinals with one tie, missing
 * round-of-16 ties, etc.).
 */
class OddCupDrawPoolException extends RuntimeException
{
    public static function forRound(string $competitionId, int $roundNumber, int $teamCount): self
    {
        return new self(
            "Cannot draw {$competitionId} round {$roundNumber}: pool has {$teamCount} teams "
            . '(odd). Knockout draws require an even pool — check upstream rule '
            . '(cup_qualification target_size, supercup entry_round bump, or seed source).'
        );
    }
}
