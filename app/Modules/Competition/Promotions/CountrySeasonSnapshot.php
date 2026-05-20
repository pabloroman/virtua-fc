<?php

namespace App\Modules\Competition\Promotions;

use App\Modules\Competition\Enums\PlayoffState;

/**
 * In-memory snapshot of one country's end-of-season state, fed to
 * CountryPromotionRelegationPlanner.
 *
 * Decoupling the read side from the planner serves two purposes:
 *
 *   1. The planner becomes a pure function (snapshot → plan). It never
 *      touches the DB, so unit tests can drive every reserve/parent/playoff
 *      combination from synthetic data without seeding fixtures.
 *
 *   2. All reconciliation work (stale SimulatedSeason rows, missing
 *      standings) happens once in the snapshot builder, not scattered
 *      across rule branches as it was in the previous design.
 */
final class CountrySeasonSnapshot
{
    /**
     * @param  array<string, list<string>>  $standingsByCompetition  Per-competition
     *     team IDs ordered by finishing position (index 0 = position 1).
     * @param  array<string, string>  $reserveToParent  Reserve team_id ⇒
     *     parent team_id. Only reserve teams appear as keys. Both teams
     *     must belong to this country.
     * @param  array<string, PlayoffState>  $playoffStates  Playoff competition_id
     *     ⇒ lifecycle state. Missing entry = no playoff configured.
     * @param  array<string, list<string>>  $playoffWinners  Playoff competition_id
     *     ⇒ ordered list of winner team IDs (used when state == Completed).
     * @param  ?string  $userTeamId  The user's managed team for this country,
     *     used by the executor (not the planner) to update Game.competition_id
     *     when the user is moved.
     */
    public function __construct(
        public readonly string $countryCode,
        public readonly array $standingsByCompetition,
        public readonly array $reserveToParent,
        public readonly array $playoffStates = [],
        public readonly array $playoffWinners = [],
        public readonly ?string $userTeamId = null,
    ) {}

    /**
     * @return list<string>
     */
    public function standings(string $competitionId): array
    {
        return $this->standingsByCompetition[$competitionId] ?? [];
    }

    /**
     * Locate which competition a team is currently in. Returns null if the
     * team is not in any of the snapshot's tracked competitions.
     */
    public function competitionOf(string $teamId): ?string
    {
        foreach ($this->standingsByCompetition as $competitionId => $teams) {
            if (in_array($teamId, $teams, true)) {
                return $competitionId;
            }
        }

        return null;
    }

    public function parentOf(string $teamId): ?string
    {
        return $this->reserveToParent[$teamId] ?? null;
    }

    public function isReserve(string $teamId): bool
    {
        return isset($this->reserveToParent[$teamId]);
    }

    public function playoffState(string $playoffCompetitionId): PlayoffState
    {
        return $this->playoffStates[$playoffCompetitionId] ?? PlayoffState::NotStarted;
    }

    /**
     * @return list<string>
     */
    public function playoffWinners(string $playoffCompetitionId): array
    {
        return $this->playoffWinners[$playoffCompetitionId] ?? [];
    }
}
