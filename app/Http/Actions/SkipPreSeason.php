<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Models\GameMatch;
use App\Modules\Match\Jobs\ProcessCareerActions;
use Illuminate\Support\Facades\Log;

class SkipPreSeason
{
    public function __invoke(string $gameId)
    {
        $game = Game::findOrFail($gameId);

        if (! $game->isInPreSeason()) {
            return redirect()->route('show-game', $gameId);
        }

        // Delete all unplayed pre-season matches
        GameMatch::where('game_id', $game->id)
            ->where('competition_id', 'PRESEASON')
            ->where('played', false)
            ->delete();

        // Advance current_date to the earliest competitive match
        $earliestMatch = GameMatch::where('game_id', $game->id)
            ->where('played', false)
            ->orderBy('scheduled_date')
            ->first();

        $updates = ['pre_season' => false];
        if ($earliestMatch) {
            $updates['current_date'] = $earliestMatch->scheduled_date->toDateString();
        }

        $game->update($updates);

        // Run career action ticks in the background to simulate pre-season transfer activity
        $updated = Game::where('id', $game->id)
            ->whereNull('career_actions_processing_at')
            ->update(['career_actions_processing_at' => now()]);

        if ($updated) {
            try {
                ProcessCareerActions::dispatch($game->id, 4);
            } catch (\Throwable $e) {
                Game::where('id', $game->id)->update(['career_actions_processing_at' => null]);
                Log::error('Failed to dispatch pre-season career actions', [
                    'game_id' => $game->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return redirect()->route('show-game', $gameId)
            ->with('info', __('game.pre_season_skipped'));
    }
}
