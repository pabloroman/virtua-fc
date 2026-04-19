<?php

namespace App\Modules\Competition\Exceptions;

use RuntimeException;

/**
 * Thrown when promotion/relegation logic is invoked while a playoff is still
 * in progress for one of the divisions involved. Callers (season transition
 * job, season-end view) are expected to catch this, abort cleanly, and wait
 * for the playoff to finish before retrying.
 *
 * Replaces a silent fallback in ConfigDrivenPromotionRule::getPromotedTeams
 * that used to promote the next league position whenever no playoff winner
 * could be resolved — masking the real cause (an in-flight playoff) and
 * producing the "playoff loser promoted" bug.
 */
class PlayoffInProgressException extends RuntimeException
{
    public static function forCompetition(string $competitionId): self
    {
        return new self(
            "Cannot resolve promotion for {$competitionId}: playoff is still in progress. "
            . 'Wait for the final to complete before running the season transition.'
        );
    }
}
