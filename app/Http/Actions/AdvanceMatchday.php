<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Modules\Match\Jobs\ProcessMatchdayAdvance;

class AdvanceMatchday
{
    public function __invoke(string $gameId)
    {
        $game = Game::findOrFail($gameId);

        // Already advancing — redirect to loading screen
        if ($game->isAdvancingMatchday()) {
            return redirect()->route('show-game', $gameId);
        }

        // Career actions still processing — redirect to loading screen
        if ($game->isProcessingCareerActions()) {
            return redirect()->route('show-game', $gameId);
        }

        // Set flag and clear any stale result
        $game->update([
            'matchday_advancing_at' => now(),
            'matchday_advance_result' => null,
        ]);

        ProcessMatchdayAdvance::dispatch($game->id);

        return redirect()->route('show-game', $gameId);
    }
}
