<?php

namespace App\Http\Views;

use App\Game\Services\AlertService;
use App\Game\Services\CalendarService;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GameStanding;

class ShowGame
{
    public function __construct(
        private readonly AlertService $alertService,
        private readonly CalendarService $calendarService,
    ) {}

    public function __invoke(string $gameId)
    {
        $game = Game::with('team')->findOrFail($gameId);

        // Redirect to onboarding if not completed
        if ($game->needsOnboarding()) {
            return redirect()->route('game.onboarding', $gameId);
        }

        $nextMatch = $this->loadNextMatch($game);

        return view('game', [
            'game' => $game,
            'nextMatch' => $nextMatch,
            'homeStanding' => $nextMatch ? $this->getTeamStanding($game, $nextMatch->home_team_id) : null,
            'awayStanding' => $nextMatch ? $this->getTeamStanding($game, $nextMatch->away_team_id) : null,
            'playerForm' => $this->calendarService->getTeamForm($game->id, $game->team_id),
            'opponentForm' => $this->getOpponentForm($game, $nextMatch),
            'upcomingFixtures' => $this->calendarService->getUpcomingFixtures($game),
            'squadAlerts' => $this->alertService->getSquadAlerts($game, $nextMatch),
            'transferAlerts' => $this->alertService->getTransferAlerts($game),
            'scoutReport' => $game->activeScoutReport,
            'finances' => $game->currentFinances,
            'investment' => $game->currentInvestment,
        ]);
    }

    private function loadNextMatch(Game $game): ?GameMatch
    {
        $nextMatch = $game->next_match;

        if ($nextMatch) {
            $nextMatch->load(['homeTeam', 'awayTeam', 'competition']);
        }

        return $nextMatch;
    }

    private function getTeamStanding(Game $game, string $teamId): ?GameStanding
    {
        return GameStanding::where('game_id', $game->id)
            ->where('competition_id', $game->competition_id)
            ->where('team_id', $teamId)
            ->first();
    }

    private function getOpponentForm(Game $game, ?GameMatch $nextMatch): array
    {
        if (!$nextMatch) {
            return [];
        }

        $opponentId = $nextMatch->home_team_id === $game->team_id
            ? $nextMatch->away_team_id
            : $nextMatch->home_team_id;

        return $this->calendarService->getTeamForm($game->id, $opponentId);
    }
}
