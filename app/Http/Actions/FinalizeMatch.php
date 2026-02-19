<?php

namespace App\Http\Actions;

use App\Modules\Match\Services\MatchFinalizationService;
use App\Models\Game;
use App\Models\GameMatch;
use Illuminate\Http\Request;

class FinalizeMatch
{
    public function __construct(
        private readonly MatchFinalizationService $finalizationService,
    ) {}

    public function __invoke(Request $request, string $gameId)
    {
        $game = Game::findOrFail($gameId);

        $matchId = $game->pending_finalization_match_id;

        if (! $matchId) {
            return redirect()->route('show-game', $gameId);
        }

        $match = GameMatch::find($matchId);

        if (! $match || ! $match->played) {
            $game->update(['pending_finalization_match_id' => null]);

            return redirect()->route('show-game', $gameId);
        }

        $this->finalizationService->finalize($match, $game);

        return redirect()->route('show-game', $gameId);
    }
}
