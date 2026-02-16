<?php

namespace App\Http\Actions;

use App\Game\Services\SubstitutionService;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\MatchEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProcessSubstitution
{
    private const MAX_SUBSTITUTIONS = 5;

    private const MAX_WINDOWS = 3;

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
            'substitutions' => 'required|array|min:1|max:'.self::MAX_SUBSTITUTIONS,
            'substitutions.*.playerOutId' => 'required|string',
            'substitutions.*.playerInId' => 'required|string',
            'minute' => 'required|integer|min:1|max:93',
            'previousSubstitutions' => 'array',
            'previousSubstitutions.*.playerOutId' => 'required|string',
            'previousSubstitutions.*.playerInId' => 'required|string',
            'previousSubstitutions.*.minute' => 'required|integer',
        ]);

        $newSubs = $validated['substitutions'];
        $minute = $validated['minute'];
        $previousSubs = $validated['previousSubstitutions'] ?? [];

        // Check total substitution limit
        $totalSubs = count($previousSubs) + count($newSubs);
        if ($totalSubs > self::MAX_SUBSTITUTIONS) {
            return response()->json(['error' => __('game.sub_error_limit_reached')], 422);
        }

        // Check substitution window limit (group previous subs by minute to count windows used)
        $previousWindows = count(array_unique(array_column($previousSubs, 'minute')));
        if ($previousWindows >= self::MAX_WINDOWS) {
            return response()->json(['error' => __('game.sub_error_windows_reached')], 422);
        }

        // Validate each sub in the batch
        $isHome = $match->isHomeTeam($game->team_id);
        $currentLineupIds = $isHome ? ($match->home_lineup ?? []) : ($match->away_lineup ?? []);

        // Build active lineup from previous subs
        $activeLineupIds = $currentLineupIds;
        foreach ($previousSubs as $sub) {
            $activeLineupIds = array_values(array_filter(
                $activeLineupIds,
                fn ($id) => $id !== $sub['playerOutId']
            ));
            $activeLineupIds[] = $sub['playerInId'];
        }

        // Track IDs used in this batch to prevent conflicts within the same window
        $batchOutIds = [];
        $batchInIds = [];

        foreach ($newSubs as $sub) {
            $playerOutId = $sub['playerOutId'];
            $playerInId = $sub['playerInId'];

            // Check player is on pitch (considering earlier subs in this batch)
            $effectiveLineup = $activeLineupIds;
            foreach ($batchOutIds as $i => $outId) {
                $effectiveLineup = array_values(array_filter($effectiveLineup, fn ($id) => $id !== $outId));
                $effectiveLineup[] = $batchInIds[$i];
            }

            if (! in_array($playerOutId, $effectiveLineup)) {
                return response()->json(['error' => __('game.sub_error_player_not_on_pitch')], 422);
            }

            // Prevent substituting a red-carded player
            $wasRedCarded = MatchEvent::where('game_match_id', $match->id)
                ->where('game_player_id', $playerOutId)
                ->where('event_type', 'red_card')
                ->where('minute', '<=', $minute)
                ->exists();

            if ($wasRedCarded) {
                return response()->json(['error' => __('game.sub_error_player_sent_off')], 422);
            }

            // Validate player-in belongs to team and is not on pitch
            $playerIn = GamePlayer::where('id', $playerInId)
                ->where('game_id', $gameId)
                ->where('team_id', $game->team_id)
                ->first();

            if (! $playerIn) {
                return response()->json(['error' => __('game.sub_error_invalid_player')], 422);
            }

            if (in_array($playerInId, $effectiveLineup)) {
                return response()->json(['error' => __('game.sub_error_already_on_pitch')], 422);
            }

            // Check not already used in this batch
            if (in_array($playerInId, $batchInIds)) {
                return response()->json(['error' => __('game.sub_error_already_on_pitch')], 422);
            }

            $batchOutIds[] = $playerOutId;
            $batchInIds[] = $playerInId;
        }

        // Process the batch
        $result = $this->substitutionService->processBatchSubstitution(
            $match, $game, $newSubs, $minute, $previousSubs,
        );

        return response()->json($result);
    }
}
