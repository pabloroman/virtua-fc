<?php

namespace App\Http\Actions;

use App\Modules\Lineup\Enums\DefensiveLineHeight;
use App\Modules\Lineup\Enums\Formation;
use App\Modules\Lineup\Enums\Mentality;
use App\Modules\Lineup\Enums\PlayingStyle;
use App\Modules\Lineup\Enums\PressingIntensity;
use App\Modules\Lineup\Services\SubstitutionService;
use App\Modules\Lineup\Services\TacticalChangeService;
use App\Models\Game;
use App\Models\GameMatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProcessTacticalActions
{
    public function __construct(
        private readonly TacticalChangeService $tacticalChangeService,
        private readonly SubstitutionService $substitutionService,
    ) {}

    public function __invoke(Request $request, string $gameId, string $matchId): JsonResponse
    {
        $game = Game::findOrFail($gameId);
        $match = GameMatch::with(['homeTeam', 'awayTeam', 'competition'])
            ->where('game_id', $gameId)
            ->findOrFail($matchId);

        if ($game->pending_finalization_match_id !== $match->id) {
            return response()->json(['error' => __('game.match_not_in_progress')], 403);
        }

        if (! $match->involvesTeam($game->team_id)) {
            return response()->json(['error' => __('game.sub_error_not_your_match')], 422);
        }

        $validated = $request->validate([
            'minute' => 'required|integer|min:1|max:120',
            'substitutions' => 'array|max:'.SubstitutionService::MAX_ET_SUBSTITUTIONS,
            'substitutions.*.playerOutId' => 'required|string',
            'substitutions.*.playerInId' => 'required|string',
            'formation' => ['nullable', 'string', Rule::enum(Formation::class)],
            'mentality' => ['nullable', 'string', Rule::enum(Mentality::class)],
            'playing_style' => ['nullable', 'string', Rule::enum(PlayingStyle::class)],
            'pressing' => ['nullable', 'string', Rule::enum(PressingIntensity::class)],
            'defensive_line' => ['nullable', 'string', Rule::enum(DefensiveLineHeight::class)],
            'pitch_positions' => 'nullable|array',
            'pitch_positions.*' => 'array|size:2',
            'previousSubstitutions' => 'array',
            'previousSubstitutions.*.playerOutId' => 'required|string',
            'previousSubstitutions.*.playerInId' => 'required|string',
            'previousSubstitutions.*.minute' => 'required|integer',
        ]);

        $hasSubs = ! empty($validated['substitutions']);
        $hasTactics = ! empty($validated['formation'])
            || ! empty($validated['mentality'])
            || ! empty($validated['playing_style'])
            || ! empty($validated['pressing'])
            || ! empty($validated['defensive_line'])
            || ! empty($validated['pitch_positions']);

        if (! $hasSubs && ! $hasTactics) {
            return response()->json(['error' => __('game.tactical_no_changes')], 422);
        }

        $isExtraTime = $validated['minute'] > 90;

        // Validate substitutions if present
        if ($hasSubs) {
            try {
                $this->substitutionService->validateBatchSubstitution(
                    $match,
                    $game,
                    $validated['substitutions'],
                    $validated['minute'],
                    $validated['previousSubstitutions'] ?? [],
                    isExtraTime: $isExtraTime,
                );
            } catch (\InvalidArgumentException $e) {
                return response()->json(['error' => __($e->getMessage())], 422);
            }
        }

        $result = $this->tacticalChangeService->processLiveMatchChanges(
            $match,
            $game,
            $validated['minute'],
            $validated['previousSubstitutions'] ?? [],
            $validated['substitutions'] ?? [],
            $validated['formation'] ?? null,
            $validated['mentality'] ?? null,
            $validated['playing_style'] ?? null,
            $validated['pressing'] ?? null,
            $validated['defensive_line'] ?? null,
            isExtraTime: $isExtraTime,
            pitchPositions: $validated['pitch_positions'] ?? null,
        );

        return response()->json($result);
    }
}
