<?php

namespace App\Modules\Competition\Exceptions;

use RuntimeException;

/**
 * Thrown when the promotion/relegation snapshot builder finds a playable
 * tier with no standings to read. Without standings the planner would
 * relegate teams INTO an empty tier without any compensating promoters out
 * of it, producing an unbalanced plan that fails much later in validation
 * with a confusing "ends with N teams" error.
 *
 * Failing fast at snapshot build surfaces the real precondition violation:
 * GameStanding (played > 0) is empty and SimulatedSeason has no fallback
 * results for this competition/season. Operator action is needed (seed the
 * tier, run the unstick command, etc.) before retrying the season transition.
 */
class TierStandingsMissingException extends RuntimeException
{
    public static function forCompetition(string $competitionId): self
    {
        return new self(
            "Cannot build promotion/relegation snapshot: tier competition '{$competitionId}' "
            . 'has no standings. Both GameStanding (played > 0) and SimulatedSeason are empty '
            . 'for this game and season.'
        );
    }
}
