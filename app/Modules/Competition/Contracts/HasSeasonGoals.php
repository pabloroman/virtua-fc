<?php

namespace App\Modules\Competition\Contracts;

/**
 * Interface for competition configs that define season goals.
 * Only league competitions (where the player competes for standings) implement this.
 */
interface HasSeasonGoals
{
    /**
     * Get the season goal for a team based on reputation level.
     *
     * @param string $reputation One of ClubProfile::REPUTATION_* constants
     * @return string One of Game::GOAL_* constants
     */
    public function getSeasonGoal(string $reputation): string;

    /**
     * Get the target position for achieving a season goal.
     *
     * @param string $goal One of Game::GOAL_* constants
     * @return int The target position (finish at or above)
     */
    public function getGoalTargetPosition(string $goal): int;

    /**
     * Get the available season goals for this competition.
     *
     * @return array<string, array{targetPosition: int, label: string}>
     */
    public function getAvailableGoals(): array;
}
