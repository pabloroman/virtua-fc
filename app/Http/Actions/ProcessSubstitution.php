<?php

namespace App\Http\Actions;

use App\Game\Services\SubstitutionService;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProcessSubstitution
{
    private const MAX_SUBSTITUTIONS = 5;

    public function __construct(
        private readonly SubstitutionService $substitutionService,
    ) {}

    public function __invoke(Request $request, string $gameId, string $matchId): JsonResponse
    {
        $game = Game::findOrFail($gameId);
        $match = GameMatch::with(['homeTeam', 'awayTeam', 'competition'])
            ->where('game_id', $gameId)
            ->findOrFail($matchId);

        // Validate that the match involves the user's team
        if (! $match->involvesTeam($game->team_id)) {
            return response()->json(['error' => __('game.sub_error_not_your_match')], 422);
        }

        $validated = $request->validate([
            'playerOutId' => 'required|string',
            'playerInId' => 'required|string',
            'minute' => 'required|integer|min:1|max:93',
            'previousSubstitutions' => 'array',
            'previousSubstitutions.*.playerOutId' => 'required|string',
            'previousSubstitutions.*.playerInId' => 'required|string',
            'previousSubstitutions.*.minute' => 'required|integer',
        ]);

        $playerOutId = $validated['playerOutId'];
        $playerInId = $validated['playerInId'];
        $minute = $validated['minute'];
        $previousSubs = $validated['previousSubstitutions'] ?? [];

        // Check substitution limit
        $totalSubs = count($previousSubs) + 1;
        if ($totalSubs > self::MAX_SUBSTITUTIONS) {
            return response()->json(['error' => __('game.sub_error_limit_reached')], 422);
        }

        // Validate that playerOut is in the current active lineup
        $isHome = $match->isHomeTeam($game->team_id);
        $currentLineupIds = $isHome ? ($match->home_lineup ?? []) : ($match->away_lineup ?? []);

        // Apply previous subs to get current active lineup
        $activeLineupIds = $currentLineupIds;
        foreach ($previousSubs as $sub) {
            $activeLineupIds = array_values(array_filter(
                $activeLineupIds,
                fn ($id) => $id !== $sub['playerOutId']
            ));
            $activeLineupIds[] = $sub['playerInId'];
        }

        if (! in_array($playerOutId, $activeLineupIds)) {
            return response()->json(['error' => __('game.sub_error_player_not_on_pitch')], 422);
        }

        // Validate that playerIn belongs to the user's team and is not already on the pitch
        $playerIn = GamePlayer::where('id', $playerInId)
            ->where('game_id', $gameId)
            ->where('team_id', $game->team_id)
            ->first();

        if (! $playerIn) {
            return response()->json(['error' => __('game.sub_error_invalid_player')], 422);
        }

        if (in_array($playerInId, $activeLineupIds)) {
            return response()->json(['error' => __('game.sub_error_already_on_pitch')], 422);
        }

        // Process the substitution
        $result = $this->substitutionService->processSubstitution(
            $match, $game, $playerOutId, $playerInId, $minute, $previousSubs,
        );

        return response()->json($result);
    }
}
