<?php

namespace App\Http\Views;

use App\Models\Game;
use App\Models\GameStanding;

class ShowGame
{
    public function __invoke(string $gameId)
    {
        $game = Game::with('team')->findOrFail($gameId);

        // Get next match for player's team
        $nextMatch = $game->next_match;
        if ($nextMatch) {
            $nextMatch->load(['homeTeam', 'awayTeam']);
        }

        // Get standings (top 6 + player's position if not in top 6)
        $standings = GameStanding::with('team')
            ->where('game_id', $gameId)
            ->where('competition_id', $game->competition_id)
            ->orderBy('position')
            ->limit(6)
            ->get();

        // Check if player's team is in top 6
        $playerStanding = GameStanding::with('team')
            ->where('game_id', $gameId)
            ->where('competition_id', $game->competition_id)
            ->where('team_id', $game->team_id)
            ->first();

        $showPlayerSeparately = $playerStanding && $playerStanding->position > 6;

        // Get recent results (last 3 played matches)
        $recentResults = $game->matches()
            ->with(['homeTeam', 'awayTeam'])
            ->where('played', true)
            ->orderByDesc('played_at')
            ->limit(3)
            ->get();

        return view('game', [
            'game' => $game,
            'nextMatch' => $nextMatch,
            'standings' => $standings,
            'playerStanding' => $showPlayerSeparately ? $playerStanding : null,
            'recentResults' => $recentResults,
        ]);
    }
}
