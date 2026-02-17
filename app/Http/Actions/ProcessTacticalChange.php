<?php

namespace App\Http\Actions;

use App\Game\Enums\Formation;
use App\Game\Enums\Mentality;
use App\Game\Services\TacticalChangeService;
use App\Models\Game;
use App\Models\GameMatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProcessTacticalChange
{
    public function __construct(
        private readonly TacticalChangeService $tacticalChangeService,
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
            'minute' => 'required|integer|min:1|max:93',
            'formation' => ['nullable', 'string', Rule::enum(Formation::class)],
            'mentality' => ['nullable', 'string', Rule::enum(Mentality::class)],
            'previousSubstitutions' => 'array',
            'previousSubstitutions.*.playerOutId' => 'required|string',
            'previousSubstitutions.*.playerInId' => 'required|string',
            'previousSubstitutions.*.minute' => 'required|integer',
        ]);

        // At least one of formation/mentality must be provided
        if (empty($validated['formation']) && empty($validated['mentality'])) {
            return response()->json(['error' => __('game.tactical_no_changes')], 422);
        }

        $result = $this->tacticalChangeService->processTacticalChange(
            $match,
            $game,
            $validated['minute'],
            $validated['previousSubstitutions'] ?? [],
            $validated['formation'] ?? null,
            $validated['mentality'] ?? null,
        );

        return response()->json($result);
    }
}
