<?php

namespace App\Game\Services;

use App\Models\ClubProfile;
use App\Models\Competition;
use App\Models\Game;
use App\Models\Team;

class SeasonGoalService
{
    /**
     * Determine the season goal for a team based on reputation and competition.
     */
    public function determineGoalForTeam(Team $team, Competition $competition): string
    {
        $reputation = $team->clubProfile?->reputation_level ?? ClubProfile::REPUTATION_MODEST;
        $config = $competition->getConfig();

        return $config->getSeasonGoal($reputation);
    }

    /**
     * Get the target position for a goal in a competition.
     */
    public function getTargetPosition(string $goal, Competition $competition): int
    {
        $config = $competition->getConfig();

        return $config->getGoalTargetPosition($goal);
    }

    /**
     * Get the translation key for a goal label.
     */
    public function getGoalLabel(string $goal, Competition $competition): string
    {
        $config = $competition->getConfig();
        $goals = $config->getAvailableGoals();

        return $goals[$goal]['label'] ?? 'game.goal_top_half';
    }

    /**
     * Evaluate the manager's performance against the season goal.
     */
    public function evaluatePerformance(Game $game, int $actualPosition): array
    {
        $goal = $game->season_goal ?? Game::GOAL_TOP_HALF;
        $competition = Competition::find($game->competition_id);

        if (!$competition) {
            return $this->buildEvaluationResult('met', $actualPosition, 10, $goal, 'game.goal_top_half', true, 0);
        }

        $targetPosition = $this->getTargetPosition($goal, $competition);
        $goalLabel = $this->getGoalLabel($goal, $competition);
        $positionDiff = $targetPosition - $actualPosition; // Positive = better than target
        $achieved = $actualPosition <= $targetPosition;

        // Determine grade based on goal achievement
        if ($achieved && $positionDiff >= 5) {
            $grade = 'exceptional';
        } elseif ($achieved && $positionDiff >= 2) {
            $grade = 'exceeded';
        } elseif ($achieved || $positionDiff >= -1) {
            $grade = 'met';
        } elseif ($positionDiff >= -4) {
            $grade = 'below';
        } else {
            $grade = 'disaster';
        }

        return $this->buildEvaluationResult($grade, $actualPosition, $targetPosition, $goal, $goalLabel, $achieved, $positionDiff);
    }

    /**
     * Build the evaluation result array.
     */
    private function buildEvaluationResult(
        string $grade,
        int $actualPosition,
        int $targetPosition,
        string $goal,
        string $goalLabel,
        bool $achieved,
        int $positionDiff
    ): array {
        $titleKey = "season.evaluation_{$grade}";
        $messageKey = "season.evaluation_{$grade}_message";

        return [
            'grade' => $grade,
            'title' => __($titleKey),
            'message' => __($messageKey, [
                'target' => $targetPosition,
                'actual' => $actualPosition,
                'diff' => abs($positionDiff),
            ]),
            'actualPosition' => $actualPosition,
            'targetPosition' => $targetPosition,
            'goal' => $goal,
            'goalLabel' => __($goalLabel),
            'achieved' => $achieved,
            'positionDiff' => $positionDiff,
        ];
    }

    /**
     * Get all available goals for a competition with their details.
     */
    public function getAvailableGoals(Competition $competition): array
    {
        $config = $competition->getConfig();
        $goals = $config->getAvailableGoals();

        return array_map(fn ($key, $data) => [
            'key' => $key,
            'label' => __($data['label']),
            'targetPosition' => $data['targetPosition'],
        ], array_keys($goals), $goals);
    }
}
