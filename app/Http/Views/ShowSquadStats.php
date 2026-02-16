<?php

namespace App\Http\Views;

use App\Models\AcademyPlayer;
use App\Models\Game;
use App\Models\GamePlayer;

class ShowSquadStats
{
    public function __invoke(string $gameId)
    {
        $game = Game::with('team')->findOrFail($gameId);

        $players = GamePlayer::with('player')
            ->where('game_id', $gameId)
            ->where('team_id', $game->team_id)
            ->get()
            ->map(function ($player) {
                // Add calculated stats
                $player->setAttribute('goal_contributions', $player->goals + $player->assists);
                $player->setAttribute('goals_per_game', $player->appearances > 0
                    ? round($player->goals / $player->appearances, 2)
                    : 0);
                $player->setAttribute('assists_per_game', $player->appearances > 0
                    ? round($player->assists / $player->appearances, 2)
                    : 0);
                return $player;
            });

        // Calculate squad totals
        $totals = [
            'appearances' => $players->sum('appearances'),
            'goals' => $players->sum('goals'),
            'assists' => $players->sum('assists'),
            'own_goals' => $players->sum('own_goals'),
            'yellow_cards' => $players->sum('yellow_cards'),
            'red_cards' => $players->sum('red_cards'),
            'clean_sheets' => $players->where('position', 'Goalkeeper')->sum('clean_sheets'),
        ];

        $academyCount = AcademyPlayer::where('game_id', $gameId)->where('team_id', $game->team_id)->count();

        return view('squad-stats', [
            'game' => $game,
            'players' => $players,
            'totals' => $totals,
            'academyCount' => $academyCount,
        ]);
    }
}
