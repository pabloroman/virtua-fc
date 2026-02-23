<?php

namespace App\Http\Actions;

use App\Modules\Lineup\Services\SubstitutionService;
use App\Models\Game;
use App\Models\GameMatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProcessSubstitution
{
    public function __construct(
        private readonly SubstitutionService $substitutionService,
    ) {}

    public function __invoke(Request $request, string $gameId, string $matchId): JsonResponse
    {
        $game = Game::findOrFail($gameId);
        $match = GameMatch::with(['homeTeam', 'awayTeam', 'competition'])
            ->where('game_id', $gameId)
            ->findOrFail($matchId);

        // Validate that the match is currently pending finalization (i.e. being played right now)
        if ($game->pending_finalization_match_id !== $match->id) {
            return response()->json(['error' => __('game.match_not_in_progress')], 403);
        }

        // Validate that the match involves the user's team
        if (! $match->involvesTeam($game->team_id)) {
            return response()->json(['error' => __('game.sub_error_not_your_match')], 422);
        }

        $validated = $request->validate([
            'substitutions' => 'required|array|min:1|max:'.SubstitutionService::MAX_ET_SUBSTITUTIONS,
            'substitutions.*.playerOutId' => 'required|string',
            'substitutions.*.playerInId' => 'required|string',
            'minute' => 'required|integer|min:1|max:120',
            'previousSubstitutions' => 'array',
            'previousSubstitutions.*.playerOutId' => 'required|string',
            'previousSubstitutions.*.playerInId' => 'required|string',
            'previousSubstitutions.*.minute' => 'required|integer',
        ]);

        $isExtraTime = $validated['minute'] > 90;

        try {
            $result = $this->substitutionService->validateAndProcessBatchSubstitution(
                $match,
                $game,
                $validated['substitutions'],
                $validated['minute'],
                $validated['previousSubstitutions'] ?? [],
                isExtraTime: $isExtraTime,
            );

            return response()->json($result);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => __($e->getMessage())], 422);
        }
    }
}
