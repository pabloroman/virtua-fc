<?php

namespace App\Game\Processors;

use App\Game\Contracts\SeasonEndProcessor;
use App\Game\DTO\SeasonTransitionData;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\PlayerSuspension;

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
        $playerIds = $players->pluck('id')->toArray();

        // Clear all competition-specific suspensions
        PlayerSuspension::whereIn('game_player_id', $playerIds)->delete();

        foreach ($players as $player) {
            $player->update([
                'appearances' => 0,
                'goals' => 0,
                'own_goals' => 0,
                'assists' => 0,
                'yellow_cards' => 0,
                'red_cards' => 0,
                'goals_conceded' => 0,
                'clean_sheets' => 0,
                'season_appearances' => 0,
                'suspended_until_matchday' => null, // Legacy field, clear just in case
                'injury_until' => null,
                'injury_type' => null,
                'fitness' => rand(90, 100),
                'morale' => rand(65, 80),
            ]);
        }

        // Reset game state
        $game->update([
            'current_matchday' => 0,
            'season' => $data->newSeason,
        ]);

        return $data;
    }
}
