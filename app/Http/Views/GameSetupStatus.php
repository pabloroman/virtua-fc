<?php

namespace App\Http\Views;

use App\Models\Game;
use App\Modules\Season\Jobs\ProcessSeasonTransition;
use App\Modules\Season\Jobs\SetupNewGame;
use Illuminate\Http\JsonResponse;

class GameSetupStatus
{
    public function __invoke(string $gameId): JsonResponse
    {
        $game = Game::findOrFail($gameId);

        // Recovery: re-dispatch if season transition is stuck for > 2 minutes
        if ($game->isTransitioningSeason() && $game->season_transitioning_at->lt(now()->subMinutes(2))) {
            ProcessSeasonTransition::dispatch($game->id);
            // Reset timer to prevent re-dispatching every 2s polling cycle
            $game->update(['season_transitioning_at' => now()]);
        }

        // Recovery: re-dispatch if initial game setup is stuck for > 2 minutes
        if (!$game->isSetupComplete() && $game->created_at->lt(now()->subMinutes(2))) {
            SetupNewGame::dispatch(
                gameId: $game->id,
                teamId: $game->team_id,
                competitionId: $game->competition_id,
                season: $game->season,
                gameMode: $game->game_mode ?? Game::MODE_CAREER,
            );
        }

        // Recovery: clear flag if career actions are stuck for > 2 minutes
        $game->clearStuckCareerActions();

        return response()->json([
            'ready' => $game->isSetupComplete()
                && !$game->isTransitioningSeason()
                && !$game->isProcessingCareerActions(),
        ]);
    }
}
