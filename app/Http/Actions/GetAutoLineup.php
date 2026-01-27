<?php

namespace App\Http\Actions;

use App\Game\Enums\Formation;
use App\Game\Services\LineupService;
use App\Models\Game;
use App\Models\GameMatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetAutoLineup
{
    public function __construct(
        private readonly LineupService $lineupService,
    ) {}

    public function __invoke(Request $request, string $gameId, string $matchId): JsonResponse
    {
        $game = Game::findOrFail($gameId);
        $match = GameMatch::where('game_id', $gameId)->findOrFail($matchId);

        // Get formation from request, default to 4-4-2
        $formationValue = $request->input('formation', '4-4-2');
        $formation = Formation::tryFrom($formationValue) ?? Formation::F_4_4_2;

        // Determine matchday
        $matchday = $match->round_number ?? $game->current_matchday + 1;
        $matchDate = $match->scheduled_date;

        // Get auto-selected lineup for the formation
        $autoLineup = $this->lineupService->autoSelectLineup(
            $gameId,
            $game->team_id,
            $matchDate,
            $matchday,
            $formation
        );

        return response()->json([
            'autoLineup' => $autoLineup,
            'formation' => $formation->value,
        ]);
    }
}
