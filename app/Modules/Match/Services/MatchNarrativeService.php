<?php

namespace App\Modules\Match\Services;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\GameStanding;
use App\Modules\Competition\Contracts\HasSeasonGoals;
use App\Modules\Match\DTOs\MatchNarrative;

class MatchNarrativeService
{
    /**
     * Generate 1-2 pre-match narrative snippets for the next-match card.
     *
     * @return array<MatchNarrative>
     */
    public function generate(
        Game $game,
        GameMatch $nextMatch,
        ?GameStanding $playerStanding,
        ?GameStanding $opponentStanding,
        array $playerForm,
        array $opponentForm,
    ): array {
        if ($game->isTournamentMode()) {
            $candidates = [
                ...$this->cupCandidates($nextMatch, $game),
                ...$this->formCandidates($playerForm, $opponentForm, $nextMatch, $game),
                ...$this->moodCandidates($game),
            ];
        } else {
            $candidates = [
                ...$this->cupCandidates($nextMatch, $game),
                ...$this->formCandidates($playerForm, $opponentForm, $nextMatch, $game),
                ...$this->stakesCandidates($game, $playerStanding, $opponentStanding, $nextMatch),
                ...$this->moodCandidates($game),
                ...$this->scoutingCandidates($opponentStanding, $opponentForm, $nextMatch, $game),
            ];
        }

        return $this->selectTop($candidates, $game->current_matchday ?? 1);
    }

    // ── Category: Form & Streaks ────────────────────────────────────

    private function formCandidates(array $playerForm, array $opponentForm, GameMatch $match, Game $game): array
    {
        $candidates = [];

        if (empty($playerForm)) {
            return $candidates;
        }

        $winStreak = $this->detectStreak($playerForm, 'W');
        $loseStreak = $this->detectStreak($playerForm, 'L');
        $unbeaten = $this->detectUnbeatenRun($playerForm);
        $winless = $this->detectWinlessRun($playerForm);

        if ($winStreak >= 5) {
            $candidates[] = $this->candidate('form', 9, 'streak_win_long', ['count' => $winStreak]);
        } elseif ($winStreak >= 3) {
            $candidates[] = $this->candidate('form', 8, 'streak_win', ['count' => $winStreak]);
        }

        if ($loseStreak >= 3) {
            $candidates[] = $this->candidate('form', 8, 'streak_lose', ['count' => $loseStreak]);
        }

        if ($unbeaten >= 5 && $winStreak < 5) {
            $candidates[] = $this->candidate('form', 7, 'unbeaten', ['count' => $unbeaten]);
        }

        if ($winless >= 4 && $loseStreak < 3) {
            $candidates[] = $this->candidate('form', 7, 'winless', ['count' => $winless]);
        }

        return $candidates;
    }

    // ── Category: Cup / Knockout ────────────────────────────────────

    private function cupCandidates(GameMatch $match, Game $game): array
    {
        if (!$match->isCupMatch()) {
            return [];
        }

        $candidates = [];
        $roundName = $match->round_name ?? '';
        $competitionName = __($match->competition->name ?? '');

        $cupTie = $match->cupTie;
        $isSecondLeg = $cupTie && $cupTie->second_leg_match_id === $match->id;
        $firstLegPlayed = $cupTie && $cupTie->firstLegMatch?->played;

        // Final
        if (str_contains($roundName, 'final') && !str_contains($roundName, 'semi')) {
            $candidates[] = $this->candidate('cup', 10, 'cup_final', [
                'competition' => $competitionName,
            ]);

            return $candidates;
        }

        // Semi-finals
        if (str_contains($roundName, 'semi_finals')) {
            if ($isSecondLeg && $firstLegPlayed) {
                $candidates[] = $this->candidate('cup', 10, 'cup_semi_second_leg', [
                    'competition' => $competitionName,
                ]);
            } else {
                $candidates[] = $this->candidate('cup', 9, 'cup_semi', [
                    'competition' => $competitionName,
                ]);
            }

            return $candidates;
        }

        // Quarter-finals
        if (str_contains($roundName, 'quarter_finals')) {
            $candidates[] = $this->candidate('cup', 8, 'cup_quarter', [
                'competition' => $competitionName,
            ]);

            return $candidates;
        }

        // Round of 16
        if (str_contains($roundName, 'round_of_16')) {
            $candidates[] = $this->candidate('cup', 7, 'cup_round_of_16', [
                'competition' => $competitionName,
            ]);

            return $candidates;
        }

        // Second leg with first leg score context
        if ($isSecondLeg && $firstLegPlayed) {
            $fl = $cupTie->firstLegMatch;
            $userIsHome = $fl->home_team_id === $game->team_id;
            $userScore = $userIsHome ? $fl->home_score : $fl->away_score;
            $oppScore = $userIsHome ? $fl->away_score : $fl->home_score;

            if ($userScore > $oppScore) {
                $candidates[] = $this->candidate('cup', 7, 'cup_second_leg_ahead', [
                    'score' => $userScore . '-' . $oppScore,
                ]);
            } elseif ($userScore < $oppScore) {
                $candidates[] = $this->candidate('cup', 8, 'cup_second_leg_behind', [
                    'score' => $oppScore . '-' . $userScore,
                ]);
            } else {
                $candidates[] = $this->candidate('cup', 7, 'cup_second_leg_drawn');
            }

            return $candidates;
        }

        // Generic cup match
        $candidates[] = $this->candidate('cup', 6, 'cup_generic', [
            'competition' => $competitionName,
            'round' => __($roundName),
        ]);

        return $candidates;
    }

    // ── Category: League Position & Stakes ──────────────────────────

    private function stakesCandidates(Game $game, ?GameStanding $playerStanding, ?GameStanding $opponentStanding, GameMatch $match): array
    {
        $candidates = [];

        // Only applies to the primary league competition (not cups, European competitions, etc.)
        if ($game->pre_season || !$playerStanding || $match->competition_id !== $game->competition_id) {
            return $candidates;
        }

        $matchday = $game->current_matchday ?? 0;
        $position = $playerStanding->position;
        $points = $playerStanding->points;

        // Top of table (only meaningful after a few matchdays)
        if ($position === 1 && $matchday >= 5) {
            $candidates[] = $this->candidate('stakes', 9, 'top_of_table');
        }

        // Title race: top 3 but not 1st, within 6 points of leader
        if ($matchday >= 5 && $position > 1 && $position <= 3) {
            $leaderPoints = GameStanding::where('game_id', $game->id)
                ->where('competition_id', $game->competition_id)
                ->where('position', 1)
                ->value('points');

            if ($leaderPoints !== null) {
                $gap = $leaderPoints - $points;
                if ($gap <= 6) {
                    $candidates[] = $this->candidate('stakes', 9, 'title_race', ['points' => $gap]);
                }
            }
        }

        // Relegation zone: bottom 3 of the league (only after enough matchdays)
        $totalTeams = GameStanding::where('game_id', $game->id)
            ->where('competition_id', $game->competition_id)
            ->count();

        if ($matchday >= 5 && $totalTeams > 0 && $position > $totalTeams - 3) {
            $candidates[] = $this->candidate('stakes', 9, 'relegation', [
                'position' => $this->ordinalPosition($position),
            ]);
        }

        // Direct rival: opponent close in the table (skip early season when everyone is bunched)
        $matchday = $game->current_matchday ?? 0;
        if ($opponentStanding && $matchday >= 5) {
            $positionDiff = abs($position - $opponentStanding->position);
            $pointsDiff = abs($points - $opponentStanding->points);

            if ($positionDiff <= 3 && $pointsDiff <= 4) {
                $opponentName = $match->home_team_id === $game->team_id
                    ? $match->awayTeam->short_name ?? $match->awayTeam->name
                    : $match->homeTeam->short_name ?? $match->homeTeam->name;

                $candidates[] = $this->candidate('stakes', 8, 'direct_rival', [
                    'opponent' => $opponentName,
                    'points' => $pointsDiff,
                ]);
            }
        }

        // Season goal comparison (only meaningful after several matchdays)
        if ($matchday >= 8 && $game->season_goal) {
            $competition = Competition::find($game->competition_id);
            $config = $competition?->getConfig();

            if ($config instanceof HasSeasonGoals) {
                $targetPosition = $config->getGoalTargetPosition($game->season_goal);
                $diff = $position - $targetPosition;

                if ($diff >= 3) {
                    $candidates[] = $this->candidate('stakes', 7, 'goal_behind', [
                        'position' => $this->ordinalPosition($position),
                        'diff' => $diff,
                    ]);
                } elseif ($diff <= -3) {
                    $candidates[] = $this->candidate('stakes', 6, 'goal_ahead', [
                        'position' => $this->ordinalPosition($position),
                    ]);
                }
            }
        }

        // Final stretch of season
        $matchday = $game->current_matchday ?? 0;
        $totalMatchdays = ($totalTeams - 1) * 2;
        if ($totalMatchdays > 0 && $matchday > $totalMatchdays - 4) {
            $candidates[] = $this->candidate('stakes', 7, 'final_stretch');
        }

        return $candidates;
    }

    // ── Category: Team Mood ─────────────────────────────────────────

    private function moodCandidates(Game $game): array
    {
        $candidates = [];

        $stats = GamePlayer::where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->selectRaw('AVG(morale) as avg_morale, AVG(fitness) as avg_fitness')
            ->first();

        $avgMorale = (int) round($stats->avg_morale ?? 50);
        $avgFitness = (int) round($stats->avg_fitness ?? 50);

        $injuryCount = GamePlayer::where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->where('injury_until', '>', $game->current_date)
            ->count();

        if ($injuryCount >= 4) {
            $candidates[] = $this->candidate('mood', 8, 'injury_crisis', ['count' => $injuryCount]);
        }

        if ($avgMorale < 40) {
            $candidates[] = $this->candidate('mood', 7, 'morale_low');
        } elseif ($avgMorale > 75) {
            $candidates[] = $this->candidate('mood', 6, 'morale_high');
        }

        if ($avgFitness < 55) {
            $candidates[] = $this->candidate('mood', 7, 'fitness_low');
        } elseif ($avgFitness > 80) {
            $candidates[] = $this->candidate('mood', 5, 'fitness_high');
        }

        return $candidates;
    }

    // ── Category: Opponent Scouting ─────────────────────────────────

    private function scoutingCandidates(?GameStanding $opponentStanding, array $opponentForm, GameMatch $match, Game $game): array
    {
        $candidates = [];

        $opponentName = $match->home_team_id === $game->team_id
            ? $match->awayTeam->short_name ?? $match->awayTeam->name
            : $match->homeTeam->short_name ?? $match->homeTeam->name;

        // Opponent form analysis
        if (!empty($opponentForm)) {
            $oppWins = count(array_filter($opponentForm, fn ($r) => $r === 'W'));
            $total = count($opponentForm);

            if ($oppWins <= 1 && $total >= 4) {
                $candidates[] = $this->candidate('scouting', 5, 'opponent_poor_form', [
                    'opponent' => $opponentName,
                    'wins' => $oppWins,
                    'total' => $total,
                ]);
            } elseif ($oppWins >= 4 && $total >= 5) {
                $candidates[] = $this->candidate('scouting', 5, 'opponent_hot', [
                    'opponent' => $opponentName,
                    'wins' => $oppWins,
                    'total' => $total,
                ]);
            }
        }

        // Opponent league position (only meaningful after enough games played)
        if ($opponentStanding && $opponentStanding->played >= 5) {
            $playerStanding = GameStanding::where('game_id', $game->id)
                ->where('competition_id', $game->competition_id)
                ->where('team_id', $game->team_id)
                ->first();

            if ($playerStanding && $playerStanding->played >= 5 && $opponentStanding->position) {
                $posDiff = $playerStanding->position - $opponentStanding->position;

                if ($posDiff > 8) {
                    $candidates[] = $this->candidate('scouting', 6, 'opponent_strong', [
                        'opponent' => $opponentName,
                        'position' => $this->ordinalPosition($opponentStanding->position),
                    ]);
                } elseif ($posDiff < -8) {
                    $candidates[] = $this->candidate('scouting', 4, 'opponent_weak', [
                        'opponent' => $opponentName,
                        'position' => $this->ordinalPosition($opponentStanding->position),
                    ]);
                }
            }
        }

        return $candidates;
    }

    // ── Helpers ─────────────────────────────────────────────────────

    /**
     * Count consecutive results of a given type from the most recent match.
     * Form arrays are chronological (oldest first), so we reverse to start from the latest.
     */
    private function detectStreak(array $form, string $result): int
    {
        $count = 0;
        foreach (array_reverse($form) as $r) {
            if ($r === $result) {
                $count++;
            } else {
                break;
            }
        }

        return $count;
    }

    /**
     * Count consecutive matches without a loss from the most recent match.
     */
    private function detectUnbeatenRun(array $form): int
    {
        $count = 0;
        foreach (array_reverse($form) as $r) {
            if ($r !== 'L') {
                $count++;
            } else {
                break;
            }
        }

        return $count;
    }

    /**
     * Count consecutive matches without a win from the most recent match.
     */
    private function detectWinlessRun(array $form): int
    {
        $count = 0;
        foreach (array_reverse($form) as $r) {
            if ($r !== 'W') {
                $count++;
            } else {
                break;
            }
        }

        return $count;
    }

    private function candidate(string $category, int $priority, string $key, array $params = []): array
    {
        return [
            'category' => $category,
            'priority' => $priority,
            'key' => $key,
            'params' => $params,
        ];
    }

    /**
     * Select the top 1-2 candidates, ensuring category diversity.
     *
     * @return array<MatchNarrative>
     */
    private function selectTop(array $candidates, int $matchday): array
    {
        if (empty($candidates)) {
            return [];
        }

        // Sort by priority descending
        usort($candidates, fn ($a, $b) => $b['priority'] <=> $a['priority']);

        $first = $candidates[0];
        $result = [
            $this->toNarrative($first, $matchday),
        ];

        // Pick a second from a different category
        foreach ($candidates as $candidate) {
            if ($candidate['category'] !== $first['category']) {
                $result[] = $this->toNarrative($candidate, $matchday);
                break;
            }
        }

        return $result;
    }

    private function toNarrative(array $candidate, int $matchday): MatchNarrative
    {
        $variantKey = $this->pickVariant($candidate['key'], $matchday);

        return new MatchNarrative(
            text: __("narrative.{$variantKey}", $candidate['params']),
            category: $candidate['category'],
        );
    }

    /**
     * Pick a translation variant deterministically based on matchday.
     * Checks for _v1, _v2, etc. keys and rotates based on matchday.
     */
    private function pickVariant(string $baseKey, int $matchday): string
    {
        // Count how many variants exist (check up to 3)
        $variantCount = 0;
        for ($i = 1; $i <= 3; $i++) {
            if (__("narrative.{$baseKey}_v{$i}") !== "narrative.{$baseKey}_v{$i}") {
                $variantCount = $i;
            } else {
                break;
            }
        }

        if ($variantCount === 0) {
            return $baseKey;
        }

        $variant = ($matchday % $variantCount) + 1;

        return "{$baseKey}_v{$variant}";
    }

    /**
     * Format a position number with ordinal suffix for display.
     */
    private function ordinalPosition(int $position): string
    {
        if (app()->getLocale() === 'es') {
            return $position . 'º';
        }

        return match ($position % 10) {
            1 => $position . (($position % 100 === 11) ? 'th' : 'st'),
            2 => $position . (($position % 100 === 12) ? 'th' : 'nd'),
            3 => $position . (($position % 100 === 13) ? 'th' : 'rd'),
            default => $position . 'th',
        };
    }
}
