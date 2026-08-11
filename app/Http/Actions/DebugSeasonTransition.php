<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Modules\Season\Jobs\ProcessSeasonTransition;

class DebugSeasonTransition
{
    public function __invoke(string $gameId)
    {
        abort_unless(app()->isLocal(), 404);

        $game = Game::findOrFail($gameId);

        $game->update(['season_transitioning_at' => now()]);

        ProcessSeasonTransition::dispatch($gameId);

        return redirect()->route('show-game', $gameId);
    }
}
