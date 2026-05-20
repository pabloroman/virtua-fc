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

    public static function sizeMismatch(string $competitionId, int $actual, int $expected): self
    {
        return new self(
            "Cannot build promotion/relegation snapshot: tier competition '{$competitionId}' "
            . "has {$actual} teams with played > 0 in standings, expected {$expected} per country "
            . 'config. The missing teams are most likely phantom rows from a prior incomplete '
            . 'season transition (registered in competition_entries / game_standings but with '
            . 'played = 0). The planner cannot produce a balanced plan against partial standings '
            . '— the relegation positions would resolve to slots that no team actually competed '
            . 'in. Resolve the standings (either by completing the missing matches or by removing '
            . 'the phantom rows) before retrying the season transition.'
        );
    }
}
