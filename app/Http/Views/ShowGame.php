<?php

namespace App\Http\Views;

use App\Game\Services\AlertService;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GameStanding;

class ShowGame
{
    public function __construct(
        private readonly AlertService $alertService,
    ) {}

    public function __invoke(string $gameId)
    {
        $game = Game::with(['team', 'finances'])->findOrFail($gameId);
        $nextMatch = $this->loadNextMatch($game);

        return view('game', [
            'game' => $game,
            'nextMatch' => $nextMatch,
            'playerStanding' => $this->getPlayerStanding($game),
            'leaderStanding' => $this->getLeaderStanding($game),
            'playerForm' => $this->getTeamForm($game->id, $game->team_id),
            'opponentForm' => $this->getOpponentForm($game, $nextMatch),
            'upcomingFixtures' => $this->getUpcomingFixtures($game),
            'squadAlerts' => $this->alertService->getSquadAlerts($game, $nextMatch),
            'transferAlerts' => $this->alertService->getTransferAlerts($game),
            'finances' => $game->finances,
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

    private function getPlayerStanding(Game $game): ?GameStanding
    {
        return GameStanding::with('team')
            ->where('game_id', $game->id)
            ->where('competition_id', $game->competition_id)
            ->where('team_id', $game->team_id)
            ->first();
    }

    private function getLeaderStanding(Game $game): ?GameStanding
    {
        return GameStanding::where('game_id', $game->id)
            ->where('competition_id', $game->competition_id)
            ->orderBy('position')
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

        return $this->getTeamForm($game->id, $opponentId);
    }

    private function getUpcomingFixtures(Game $game)
    {
        return GameMatch::with(['homeTeam', 'awayTeam', 'competition'])
            ->where('game_id', $game->id)
            ->where('played', false)
            ->where(function ($query) use ($game) {
                $query->where('home_team_id', $game->team_id)
                    ->orWhere('away_team_id', $game->team_id);
            })
            ->orderBy('scheduled_date')
            ->limit(5)
            ->get();
    }

    private function getTeamForm(string $gameId, string $teamId, int $limit = 5): array
    {
        $matches = GameMatch::where('game_id', $gameId)
            ->where('played', true)
            ->where(function ($query) use ($teamId) {
                $query->where('home_team_id', $teamId)
                    ->orWhere('away_team_id', $teamId);
            })
            ->orderByDesc('played_at')
            ->limit($limit)
            ->get();

        $form = [];
        foreach ($matches as $match) {
            $form[] = $this->getMatchResult($match, $teamId);
        }

        return array_reverse($form);
    }

    private function getMatchResult(GameMatch $match, string $teamId): string
    {
        $isHome = $match->home_team_id === $teamId;
        $teamScore = $isHome ? $match->home_score : $match->away_score;
        $opponentScore = $isHome ? $match->away_score : $match->home_score;

        if ($teamScore > $opponentScore) {
            return 'W';
        }

        if ($teamScore < $opponentScore) {
            return 'L';
        }

        return 'D';
    }
}
