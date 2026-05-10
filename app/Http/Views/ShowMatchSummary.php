<?php

namespace App\Http\Views;

use App\Models\Game;
use App\Models\GameMatch;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ShowMatchSummary
{
    public function __invoke(string $gameId, string $matchId)
    {
        $game = Game::findOrFail($gameId);

        $match = GameMatch::with(['homeTeam', 'awayTeam', 'competition', 'events.gamePlayer', 'mvpPlayer'])
            ->where('game_id', $game->id)
            ->where('played', true)
            ->findOrFail($matchId);

        if ($match->home_team_id !== $game->team_id && $match->away_team_id !== $game->team_id) {
            throw new NotFoundHttpException();
        }

        return view('partials.match-summary', [
            'match' => $match,
            'showHeader' => true,
            'mode' => 'full',
        ]);
    }
}
