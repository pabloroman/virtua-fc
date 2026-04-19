<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Modules\Lineup\Enums\Formation;
use App\Modules\Lineup\Services\FormationRecommender;
use App\Modules\Lineup\Services\LineupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;

/**
 * Compute the authoritative {slotId => playerId} map for a given formation,
 * roster of 11 players, and optional manual pins.
 *
 * This is the single read-only endpoint the frontend calls when it needs a
 * fresh slot layout (formation change, click "Auto"). Drag-drop, click-to-
 * assign, and player add/remove stay fully local on the client — no round
 * trip — because they only mutate already-owned slot map state.
 *
 * When `include_suggested_formation` is set, the response also carries the
 * name of the best-fit alternative formation for the same player set.
 * Used by the live-match sub flow to offer a "switch formation" option
 * when the current shape leaves a player out of position after the
 * reshuffle.
 */
class ComputeSlotAssignments
{
    public function __construct(
        private readonly LineupService $lineupService,
        private readonly FormationRecommender $formationRecommender,
    ) {}

    public function __invoke(Request $request, string $gameId): JsonResponse
    {
        $game = Game::findOrFail($gameId);

        $validated = $request->validate([
            'formation' => ['required', 'string', new Enum(Formation::class)],
            'player_ids' => ['required', 'array', 'min:1'],
            'player_ids.*' => ['required', 'string', 'uuid'],
            'manual_assignments' => ['nullable', 'array'],
            'manual_assignments.*' => ['nullable', 'string', 'uuid'],
            'include_suggested_formation' => ['nullable', 'boolean'],
        ]);

        $formation = Formation::from($validated['formation']);
        $playerIds = array_values(array_filter($validated['player_ids']));
        $manualAssignments = $validated['manual_assignments'] ?? [];
        $includeSuggested = $validated['include_suggested_formation'] ?? false;

        // Load only the requested players, scoped to the user's team in this
        // game. Anything not found is silently dropped by the algorithm.
        $players = GamePlayer::with('player')
            ->where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->whereIn('id', $playerIds)
            ->get();

        $slotAssignments = $this->lineupService->computeSlotAssignments(
            $formation,
            $players,
            $manualAssignments,
        );

        $response = [
            'slot_assignments' => $slotAssignments,
            'formation' => $formation->value,
        ];

        if ($includeSuggested) {
            $response['suggested_formation'] = $this->formationRecommender
                ->getBestFormation($players)
                ->value;
        }

        return response()->json($response);
    }
}
