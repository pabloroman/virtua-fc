<?php

namespace App\Http\Actions;

use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameMatch;
use App\Modules\Match\Services\ExtraTimeAndPenaltyService;
use App\Modules\Match\Services\MatchResimulationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProcessExtraTime
{
    public function __construct(
        private readonly ExtraTimeAndPenaltyService $service,
    ) {}

    public function __invoke(Request $request, string $gameId, string $matchId): JsonResponse
    {
        $game = Game::findOrFail($gameId);
        $match = GameMatch::with(['homeTeam', 'awayTeam'])
            ->where('game_id', $gameId)
            ->findOrFail($matchId);

        if ($game->pending_finalization_match_id !== $match->id) {
            return response()->json(['error' => 'Match not in progress'], 403);
        }

        if (! $match->cup_tie_id) {
            return response()->json(['needed' => false]);
        }

        $cupTie = CupTie::with(['firstLegMatch', 'secondLegMatch'])->find($match->cup_tie_id);

        if (! $cupTie || ! $cupTie->needsExtraTime($match)) {
            return response()->json(['needed' => false]);
        }

        $result = $this->service->processExtraTime($match, $game);

        return response()->json([
            'needed' => true,
            'extraTimeEvents' => MatchResimulationService::formatMatchEvents($result->storedEvents),
            'homeScoreET' => $result->homeScoreET,
            'awayScoreET' => $result->awayScoreET,
            'needsPenalties' => $result->needsPenalties,
            'homePossession' => $result->homePossession,
            'awayPossession' => $result->awayPossession,
        ]);
    }
}
