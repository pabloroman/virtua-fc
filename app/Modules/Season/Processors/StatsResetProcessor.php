<?php

namespace App\Modules\Season\Processors;

use App\Modules\Season\Contracts\SeasonEndProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Models\Game;
use App\Models\GameNotification;
use App\Models\GamePlayer;
use App\Models\PlayerSuspension;
use Illuminate\Support\Facades\DB;

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
        // Clear all competition-specific suspensions for this game's players
        PlayerSuspension::whereIn('game_player_id', function ($query) use ($game) {
            $query->select('id')->from('game_players')->where('game_id', $game->id);
        })->delete();

        GamePlayer::where('game_id', $game->id)->update([
            'appearances' => 0,
            'goals' => 0,
            'own_goals' => 0,
            'assists' => 0,
            'yellow_cards' => 0,
            'red_cards' => 0,
            'goals_conceded' => 0,
            'clean_sheets' => 0,
            'season_appearances' => 0,
            'suspended_until_matchday' => null,
            'injury_until' => null,
            'injury_type' => null,
            'fitness' => 80,
            'morale' => 80,
        ]);

        // Mark all previous-season notifications as read so the new season starts clean
        GameNotification::where('game_id', $game->id)
            ->unread()
            ->update(['read_at' => now()]);

        // Reset game state
        $game->update([
            'current_matchday' => 0,
            'season' => $data->newSeason,
        ]);

        return $data;
    }
}
