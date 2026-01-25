<?php

namespace App\Http\Views;

use App\Models\Game;
use App\Models\GamePlayer;

class ShowSquad
{
    public function __invoke(string $gameId)
    {
        $game = Game::with('team')->findOrFail($gameId);

        // Get all players for the user's team, grouped by position
        $players = GamePlayer::with('player')
            ->where('game_id', $gameId)
            ->where('team_id', $game->team_id)
            ->get()
            ->sortBy(fn ($p) => $this->positionSortOrder($p->position))
            ->groupBy(fn ($p) => $p->position_group);

        return view('squad', [
            'game' => $game,
            'goalkeepers' => $players->get('Goalkeeper', collect()),
            'defenders' => $players->get('Defender', collect()),
            'midfielders' => $players->get('Midfielder', collect()),
            'forwards' => $players->get('Forward', collect()),
        ]);
    }

    /**
     * Get sort order for positions within their group.
     */
    private function positionSortOrder(string $position): int
    {
        return match ($position) {
            // Goalkeepers
            'Goalkeeper' => 1,
            // Defenders
            'Centre-Back' => 10,
            'Left-Back' => 11,
            'Right-Back' => 12,
            // Midfielders
            'Defensive Midfield' => 20,
            'Central Midfield' => 21,
            'Left Midfield' => 22,
            'Right Midfield' => 23,
            'Attacking Midfield' => 24,
            // Forwards
            'Left Winger' => 30,
            'Right Winger' => 31,
            'Second Striker' => 32,
            'Centre-Forward' => 33,
            default => 99,
        };
    }
}
