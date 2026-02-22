<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Models\GameMatch;
use App\Modules\Match\Services\ExtraTimeAndPenaltyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProcessPenalties
{
    public function __construct(
        private readonly ExtraTimeAndPenaltyService $service,
    ) {}

    public function __invoke(Request $request, string $gameId, string $matchId): JsonResponse
    {
        $game = Game::findOrFail($gameId);
        $match = GameMatch::where('game_id', $gameId)->findOrFail($matchId);

        if ($game->pending_finalization_match_id !== $match->id) {
            return response()->json(['error' => 'Match not in progress'], 403);
        }

        if (! $match->is_extra_time) {
            return response()->json(['error' => 'Extra time not played'], 400);
        }

        if ($match->home_score_penalties !== null) {
            return response()->json(['error' => 'Penalties already resolved'], 400);
        }

        $request->validate([
            'kickerOrder' => 'required|array|min:5',
            'kickerOrder.*' => 'string',
        ]);

        $result = $this->service->processPenalties($match, $game, $request->input('kickerOrder'));

        return response()->json([
            'homeScore' => $result->homeScore,
            'awayScore' => $result->awayScore,
            'kicks' => $result->kicks,
        ]);
    }
}
