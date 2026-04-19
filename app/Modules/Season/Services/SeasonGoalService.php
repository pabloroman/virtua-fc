<?php

namespace App\Modules\Season\Services;

use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\Team;
use App\Models\TeamReputation;
use App\Modules\Competition\Contracts\HasSeasonGoals;
use Illuminate\Support\Facades\Lang;

class SeasonGoalService
{
    private const GRADE_ORDER = ['disaster', 'below', 'met', 'exceeded', 'exceptional'];

    /**
     * Determine the season goal for a team based on reputation and competition.
     */
    public function determineGoalForTeam(Team $team, Competition $competition, ?Game $game = null, bool $recentlyPromoted = false): string
    {
        $config = $competition->getConfig();

        if (!$config instanceof HasSeasonGoals) {
            return Game::GOAL_TOP_HALF;
        }

        $reputation = $game
            ? TeamReputation::resolveLevel($game->id, $team->id)
            : ($team->clubProfile->reputation_level ?? 'modest');

        $goal = $config->getSeasonGoal($reputation);

        if ($recentlyPromoted) {
            $goal = $this->downgradeGoal($goal, $config);
        }

        return $goal;
    }

    /**
     * Downgrade a season goal by one tier (less ambitious) for recently promoted teams.
     */
    private function downgradeGoal(string $goal, HasSeasonGoals $config): string
    {
        $goals = $config->getAvailableGoals();
        uasort($goals, fn ($a, $b) => $a['targetPosition'] <=> $b['targetPosition']);
        $keys = array_keys($goals);
        $index = array_search($goal, $keys);

        if ($index !== false && isset($keys[$index + 1])) {
            return $keys[$index + 1];
        }

        return $goal;
    }

    /**
     * Get the target position for a goal in a competition.
     */
    public function getTargetPosition(string $goal, Competition $competition): int
    {
        $config = $competition->getConfig();

        if (!$config instanceof HasSeasonGoals) {
            return 10;
        }

        return $config->getGoalTargetPosition($goal);
    }

    /**
     * Get the translation key for a goal label.
     */
    public function getGoalLabel(string $goal, Competition $competition): string
    {
        $config = $competition->getConfig();

        if (!$config instanceof HasSeasonGoals) {
            return 'game.goal_top_half';
        }

        $goals = $config->getAvailableGoals();

        return $goals[$goal]['label'] ?? 'game.goal_top_half';
    }

    /**
     * Evaluate the manager's performance against the season goal.
     */
    public function evaluatePerformance(Game $game, int $actualPosition, bool $promoted = false): array
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

        // Promotion is a major achievement — enforce a minimum grade floor
        if ($promoted) {
            $minGrade = match ($goal) {
                Game::GOAL_PROMOTION => 'met',
                Game::GOAL_PLAYOFF => 'exceeded',
                default => 'exceptional',
            };

            $currentIndex = array_search($grade, self::GRADE_ORDER);
            $minIndex = array_search($minGrade, self::GRADE_ORDER);

            if ($currentIndex < $minIndex) {
                $grade = $minGrade;
            }
        }

        // Titles won and finals reached in other competitions (cups, Europe)
        // bump the grade upwards — a Champions League win or a Copa del Rey
        // should offset a mediocre league campaign.
        $boost = $this->computeAchievementBoost($game);
        if ($boost['steps'] > 0) {
            $grade = $this->upgradeGrade($grade, $boost['steps']);
        }

        return $this->buildEvaluationResult(
            $grade,
            $actualPosition,
            $targetPosition,
            $goal,
            $goalLabel,
            $achieved,
            $positionDiff,
            $boost['achievements']
        );
    }

    /**
     * Walk up the grade ladder by $steps, capped at 'exceptional'.
     */
    private function upgradeGrade(string $grade, int $steps): string
    {
        $index = array_search($grade, self::GRADE_ORDER);

        if ($index === false) {
            return $grade;
        }

        $newIndex = min($index + $steps, count(self::GRADE_ORDER) - 1);

        return self::GRADE_ORDER[$newIndex];
    }

    /**
     * Compute how many grade-ladder steps to add because of titles won or
     * finals reached in competitions other than the team's primary league.
     *
     * Returns ['steps' => int, 'achievements' => string[]] where each entry
     * in `achievements` is an already-translated label like "Champions
     * League winner" or "Copa del Rey runner-up" — ready to interpolate
     * into the board message.
     */
    private function computeAchievementBoost(Game $game): array
    {
        $supercupIds = $this->getSupercupCompetitionIds();
        $achievements = [];
        $steps = 0;

        $entries = CompetitionEntry::with('competition')
            ->where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->where('competition_id', '!=', $game->competition_id)
            ->get();

        foreach ($entries as $entry) {
            $competition = $entry->competition;

            if (! $competition || in_array($competition->id, $supercupIds, true)) {
                continue;
            }

            if (! in_array($competition->role, [Competition::ROLE_EUROPEAN, Competition::ROLE_DOMESTIC_CUP], true)) {
                continue;
            }

            $finalTie = CupTie::where('game_id', $game->id)
                ->where('competition_id', $competition->id)
                ->where('completed', true)
                ->orderByDesc('round_number')
                ->first();

            if (! $finalTie) {
                continue;
            }

            $competitionName = __($competition->name);

            if ($finalTie->winner_id === $game->team_id) {
                $steps += $competition->role === Competition::ROLE_EUROPEAN ? 2 : 1;
                $achievements[] = __('season.achievement_winner', ['competition' => $competitionName]);
                continue;
            }

            $teamPlayedFinal = $finalTie->home_team_id === $game->team_id
                || $finalTie->away_team_id === $game->team_id;

            if ($teamPlayedFinal) {
                $steps += 1;
                $achievements[] = __('season.achievement_runner_up', ['competition' => $competitionName]);
            }
        }

        return ['steps' => $steps, 'achievements' => $achievements];
    }

    /**
     * Supercups are single matches, not full-season achievements — exclude
     * them from the board assessment. Mirrors the list used by
     * TrophyRecordingProcessor.
     */
    private function getSupercupCompetitionIds(): array
    {
        $ids = [];

        foreach (config('countries', []) as $country) {
            if (isset($country['supercup']['competition'])) {
                $ids[] = $country['supercup']['competition'];
            }
        }

        return $ids;
    }

    /**
     * Build the evaluation result array.
     *
     * @param  string[]  $achievements
     */
    private function buildEvaluationResult(
        string $grade,
        int $actualPosition,
        int $targetPosition,
        string $goal,
        string $goalLabel,
        bool $achieved,
        int $positionDiff,
        array $achievements = []
    ): array {
        $titleKey = "season.evaluation_{$grade}";
        $messageKey = "season.evaluation_{$grade}_message";
        $placeholders = [
            'target' => $targetPosition,
            'actual' => $actualPosition,
            'diff' => abs($positionDiff),
        ];

        if ($achievements !== []) {
            $withTrophiesKey = "{$messageKey}_with_trophies";
            if (Lang::has($withTrophiesKey)) {
                $messageKey = $withTrophiesKey;
                $placeholders['trophies'] = $this->joinAchievements($achievements);
            }
        }

        return [
            'grade' => $grade,
            'title' => __($titleKey),
            'message' => __($messageKey, $placeholders),
            'actualPosition' => $actualPosition,
            'targetPosition' => $targetPosition,
            'goal' => $goal,
            'goalLabel' => __($goalLabel),
            'achieved' => $achieved,
            'positionDiff' => $positionDiff,
            'achievements' => $achievements,
        ];
    }

    /**
     * Join achievement labels into a human-readable list, locale-aware:
     * "X", "X and Y", "X, Y and Z".
     *
     * @param  string[]  $achievements
     */
    private function joinAchievements(array $achievements): string
    {
        $count = count($achievements);

        if ($count === 0) {
            return '';
        }

        if ($count === 1) {
            return $achievements[0];
        }

        $last = array_pop($achievements);

        return implode(', ', $achievements) . ' ' . __('season.achievement_and') . ' ' . $last;
    }
}
