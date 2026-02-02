<?php

namespace App\Http\Views;

use App\Models\Game;
use App\Models\GameMatch;

class ShowMatchResults
{
    public function __invoke(string $gameId, string $competition, int $matchday)
    {
        $game = Game::with('team')->findOrFail($gameId);

        $matches = GameMatch::with(['homeTeam', 'awayTeam', 'events.gamePlayer.player', 'competition'])
            ->where('game_id', $gameId)
            ->where('competition_id', $competition)
            ->where('round_number', $matchday)
            ->orderBy('scheduled_date')
            ->get();

        // Find player's match
        $playerMatch = $matches->first(function ($match) use ($game) {
            return $match->home_team_id === $game->team_id
                || $match->away_team_id === $game->team_id;
        });

        return view('results', [
            'game' => $game,
            'competition' => $matches->first()?->competition,
            'matchday' => $matchday,
            'matches' => $matches,
            'playerMatch' => $playerMatch,
        ]);
    }
}
