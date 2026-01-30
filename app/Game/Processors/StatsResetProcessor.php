<?php

namespace App\Game\Processors;

use App\Game\Contracts\SeasonEndProcessor;
use App\Game\DTO\SeasonTransitionData;
use App\Models\Game;
use App\Models\GamePlayer;

/**
 * Resets player and game stats for the new season.
 * Priority: 20 (runs second)
 */
class StatsResetProcessor implements SeasonEndProcessor
{
    public function priority(): int
    {
        return 20;
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        // Reset all player stats for the game
        // We need to iterate because fitness/morale need random values
        $players = GamePlayer::where('game_id', $game->id)->get();

        foreach ($players as $player) {
            $player->update([
                'appearances' => 0,
                'goals' => 0,
                'own_goals' => 0,
                'assists' => 0,
                'yellow_cards' => 0,
                'red_cards' => 0,
                'season_appearances' => 0,
                'suspended_until_matchday' => null,
                'injury_until' => null,
                'injury_type' => null,
                'fitness' => rand(90, 100),
                'morale' => rand(65, 80),
            ]);
        }

        // Reset game state
        $game->update([
            'current_matchday' => 0,
            'cup_round' => 0,
            'cup_eliminated' => false,
            'season' => $data->newSeason,
        ]);

        return $data;
    }
}
