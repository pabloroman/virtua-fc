<?php

namespace App\Modules\Competition\Contracts;

use App\Models\Game;

interface PromotionRelegationRule
{
    /**
     * The top division ID (e.g., 'ESP1')
     */
    public function getTopDivision(): string;

    /**
     * The bottom division ID (e.g., 'ESP2')
     */
    public function getBottomDivision(): string;

    /**
     * Positions in top division that get relegated (e.g., [18, 19, 20])
     */
    public function getRelegatedPositions(): array;

    /**
     * Positions in bottom division that get directly promoted (e.g., [1, 2])
     */
    public function getDirectPromotionPositions(): array;

    /**
     * Get playoff generator if this rule includes playoffs, null otherwise
     */
    public function getPlayoffGenerator(): ?PlayoffGenerator;

    /**
     * Get all teams to be promoted (direct + playoff winner if applicable).
     *
     * $incomingByDivision describes teams scheduled to land in each division
     * via other rules' relegations in the same season. The reserve-team filter
     * consults this to block a reserve from being promoted into a division
     * that its parent club is about to be relegated INTO — a parent/reserve
     * collision the per-rule filter can't otherwise see because the parent
     * is still in its old division at read time. Keyed by destination
     * competition_id; value is a list of team UUIDs.
     *
     * @param  array<string, array<int, string>>  $incomingByDivision
     * @return array<array{teamId: string, position: int|string, teamName: string}>
     */
    public function getPromotedTeams(Game $game, array $incomingByDivision = []): array;

    /**
     * Get all teams to be relegated
     *
     * @return array<array{teamId: string, position: int, teamName: string}>
     */
    public function getRelegatedTeams(Game $game): array;

    /**
     * Competition IDs that this rule's relegated teams will land in. Used by
     * PromotionRelegationProcessor to build the $incomingByDivision context
     * shared across rules. Standard rules return a single destination
     * (bottom_division). Rules whose relegated teams fan out across multiple
     * sibling competitions (e.g. PrimeraRFEFPromotionRule splitting between
     * ESP3A and ESP3B) return all candidate destinations.
     *
     * @return string[]
     */
    public function getRelegationDestinations(): array;
}
