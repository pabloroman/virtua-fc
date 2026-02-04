<?php

namespace App\Http\Views;

use App\Game\Services\CalendarService;
use App\Models\Game;

class ShowCalendar
{
    public function __construct(
        private readonly CalendarService $calendarService,
    ) {}

    public function __invoke(string $gameId)
    {
        $game = Game::with('team')->findOrFail($gameId);

        $fixtures = $this->calendarService->getTeamFixtures($game);
        $calendar = $this->calendarService->groupByMonth($fixtures);

        // Calculate season stats from played fixtures
        $playedMatches = $fixtures->filter(fn($m) => $m->played);
        $seasonStats = $this->calculateSeasonStats($playedMatches, $game->team_id);

        return view('calendar', [
            'game' => $game,
            'calendar' => $calendar,
            'seasonStats' => $seasonStats,
        ]);
    }

    private function calculateSeasonStats($matches, string $teamId): array
    {
        $wins = 0;
        $draws = 0;
        $losses = 0;
        $goalsFor = 0;
        $goalsAgainst = 0;
        $homeWins = 0;
        $homeDraws = 0;
        $homeLosses = 0;
        $homeGoalsFor = 0;
        $homeGoalsAgainst = 0;
        $awayWins = 0;
        $awayDraws = 0;
        $awayLosses = 0;
        $awayGoalsFor = 0;
        $awayGoalsAgainst = 0;
        $form = [];

        foreach ($matches as $match) {
            $isHome = $match->home_team_id === $teamId;
            $yourScore = $isHome ? $match->home_score : $match->away_score;
            $oppScore = $isHome ? $match->away_score : $match->home_score;

            $goalsFor += $yourScore;
            $goalsAgainst += $oppScore;

            if ($yourScore > $oppScore) {
                $result = 'W';
                $wins++;
                if ($isHome) {
                    $homeWins++;
                    $homeGoalsFor += $yourScore;
                    $homeGoalsAgainst += $oppScore;
                } else {
                    $awayWins++;
                    $awayGoalsFor += $yourScore;
                    $awayGoalsAgainst += $oppScore;
                }
            } elseif ($yourScore < $oppScore) {
                $result = 'L';
                $losses++;
                if ($isHome) {
                    $homeLosses++;
                    $homeGoalsFor += $yourScore;
                    $homeGoalsAgainst += $oppScore;
                } else {
                    $awayLosses++;
                    $awayGoalsFor += $yourScore;
                    $awayGoalsAgainst += $oppScore;
                }
            } else {
                $result = 'D';
                $draws++;
                if ($isHome) {
                    $homeDraws++;
                    $homeGoalsFor += $yourScore;
                    $homeGoalsAgainst += $oppScore;
                } else {
                    $awayDraws++;
                    $awayGoalsFor += $yourScore;
                    $awayGoalsAgainst += $oppScore;
                }
            }

            $form[] = $result;
        }

        $totalMatches = $wins + $draws + $losses;
        $homeMatches = $homeWins + $homeDraws + $homeLosses;
        $awayMatches = $awayWins + $awayDraws + $awayLosses;

        return [
            'played' => $totalMatches,
            'wins' => $wins,
            'draws' => $draws,
            'losses' => $losses,
            'winPercent' => $totalMatches > 0 ? round(($wins / $totalMatches) * 100) : 0,
            'goalsFor' => $goalsFor,
            'goalsAgainst' => $goalsAgainst,
            'form' => array_slice(array_reverse($form), 0, 5),
            'home' => [
                'played' => $homeMatches,
                'wins' => $homeWins,
                'draws' => $homeDraws,
                'losses' => $homeLosses,
                'points' => ($homeWins * 3) + $homeDraws,
                'goalsFor' => $homeGoalsFor,
                'goalsAgainst' => $homeGoalsAgainst,
            ],
            'away' => [
                'played' => $awayMatches,
                'wins' => $awayWins,
                'draws' => $awayDraws,
                'losses' => $awayLosses,
                'points' => ($awayWins * 3) + $awayDraws,
                'goalsFor' => $awayGoalsFor,
                'goalsAgainst' => $awayGoalsAgainst,
            ],
        ];
    }
}
