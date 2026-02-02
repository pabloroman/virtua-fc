<?php

namespace App\Http\Views;

use App\Models\Game;
use App\Models\GameMatch;

class ShowCalendar
{
    public function __invoke(string $gameId)
    {
        $game = Game::with('team')->findOrFail($gameId);

        // Get all matches for player's team
        $matches = GameMatch::with(['homeTeam', 'awayTeam', 'competition'])
            ->where('game_id', $gameId)
            ->where(function ($query) use ($game) {
                $query->where('home_team_id', $game->team_id)
                    ->orWhere('away_team_id', $game->team_id);
            })
            ->orderBy('scheduled_date')
            ->get();

        // Group by month
        $calendar = $matches->groupBy(function ($match) {
            return $match->scheduled_date->format('F Y');
        });

        return view('calendar', [
            'game' => $game,
            'calendar' => $calendar,
        ]);
    }
}
