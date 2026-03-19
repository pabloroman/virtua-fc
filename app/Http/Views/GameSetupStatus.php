<?php

namespace App\Http\Views;

use App\Models\Game;
use App\Modules\Match\Jobs\ProcessMatchdayAdvance;
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

        // Recovery: re-dispatch if matchday advance is stuck for > 2 minutes
        if ($game->isAdvancingMatchday() && $game->matchday_advancing_at->lt(now()->subMinutes(2))) {
            ProcessMatchdayAdvance::dispatch($game->id);
            $game->update(['matchday_advancing_at' => now()]);
        }

        // Calculate season transition progress from checkpoint step
        $progress = null;
        if ($game->isTransitioningSeason() && $game->season_transition_step !== null) {
            $totalSteps = 27; // 19 closing + 8 setup processors
            $progress = min(100, (int) round(($game->season_transition_step + 1) / $totalSteps * 100));
        }

        return response()->json([
            'ready' => $game->isSetupComplete()
                && !$game->isTransitioningSeason()
                && !$game->isProcessingCareerActions()
                && !$game->isAdvancingMatchday(),
            'progress' => $progress,
        ]);
    }
}
