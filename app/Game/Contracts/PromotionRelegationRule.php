<?php

namespace App\Game\Contracts;

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
     * Get all teams to be promoted (direct + playoff winner if applicable)
     *
     * @return array<array{teamId: string, position: int|string, teamName: string}>
     */
    public function getPromotedTeams(Game $game): array;

    /**
     * Get all teams to be relegated
     *
     * @return array<array{teamId: string, position: int, teamName: string}>
     */
    public function getRelegatedTeams(Game $game): array;
}
