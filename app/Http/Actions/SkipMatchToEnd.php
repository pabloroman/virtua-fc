<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\PlayerSuspension;
use App\Modules\Lineup\Services\SubstitutionService;
use App\Modules\Lineup\Services\TacticalChangeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Re-simulate a live match from the current minute to the end with AI
 * substitutions enabled for the user's team. Triggered by the "Skip to end"
 * button in the live match view so players who fast-forward don't finish with
 * the tired starting 11.
 *
 * Normal live-match play (minute by minute) stays fully manual — auto-subs
 * only kick in when this endpoint is called.
 */
class SkipMatchToEnd
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

        if ($game->pending_finalization_match_id !== $match->id) {
            return response()->json(['error' => __('game.match_not_in_progress')], 403);
        }

        if (! $match->involvesTeam($game->team_id)) {
            return response()->json(['error' => __('game.sub_error_not_your_match')], 422);
        }

        $validated = $request->validate([
            'minute' => 'required|integer|min:1|max:93',
            'previousSubstitutions' => 'array',
            'previousSubstitutions.*.playerOutId' => 'required|string',
            'previousSubstitutions.*.playerInId' => 'required|string',
            'previousSubstitutions.*.minute' => 'required|integer',
        ]);

        // Extra time is out of scope for this iteration — the existing
        // client-only skipToEnd handles ET and penalties.
        if ($match->is_extra_time) {
            return $this->noop();
        }

        $previousSubstitutions = $validated['previousSubstitutions'] ?? [];
        $minute = $validated['minute'];

        // Nothing meaningful left to resimulate — AI sub windows are bounded
        // by config('match_simulation.ai_substitutions.max_minute') anyway.
        if ($minute >= 90) {
            return $this->noop();
        }

        // If the user already burned all 5 subs or all 3 windows, there's no
        // sub budget to top up. Avoid an unnecessary resimulation.
        if (count($previousSubstitutions) >= SubstitutionService::MAX_SUBSTITUTIONS) {
            return $this->noop();
        }

        $usedWindows = collect($previousSubstitutions)
            ->pluck('minute')
            ->unique()
            ->count();
        if ($usedWindows >= SubstitutionService::MAX_WINDOWS) {
            return $this->noop();
        }

        // Empty user bench → nothing to bring on.
        if ($this->availableBenchCount($match, $game, $previousSubstitutions) === 0) {
            return $this->noop();
        }

        try {
            $result = $this->tacticalChangeService->processLiveMatchChanges(
                $match,
                $game,
                $minute,
                $previousSubstitutions,
                newSubstitutions: [],
                isExtraTime: false,
                autoSubUserTeam: true,
            );
        } catch (\Throwable $e) {
            Log::error('SkipMatchToEnd failed', [
                'match_id' => $match->id,
                'game_id' => $game->id,
                'minute' => $minute,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => __('game.tactical_error_generic'),
            ], 422);
        }

        $result['autoSubsApplied'] = true;

        return response()->json($result);
    }

    /**
     * Return an empty "no-op" response that matches the shape of a successful
     * resimulation so the frontend can fall through to its client-only
     * fast-forward without special-casing the payload.
     */
    private function noop(): JsonResponse
    {
        return response()->json([
            'autoSubsApplied' => false,
            'newEvents' => [],
        ]);
    }

    /**
     * Count how many bench players are actually available (not already
     * subbed in, not injured, not suspended). Cheap pre-check to avoid
     * running the full resimulation when the bench can't contribute subs.
     */
    private function availableBenchCount(GameMatch $match, Game $game, array $previousSubstitutions): int
    {
        $isUserHome = $match->isHomeTeam($game->team_id);
        $lineupIds = $isUserHome ? ($match->home_lineup ?? []) : ($match->away_lineup ?? []);

        $subbedInIds = array_column($previousSubstitutions, 'playerInId');
        $subbedOutIds = array_column($previousSubstitutions, 'playerOutId');

        // Active on-pitch IDs = original lineup minus subbed-out plus subbed-in
        $activeIds = array_values(array_filter(
            $lineupIds,
            fn ($id) => ! in_array($id, $subbedOutIds, true),
        ));
        $activeIds = array_merge($activeIds, $subbedInIds);

        $suspendedIds = PlayerSuspension::suspendedPlayerIdsForCompetition($match->game_id, $match->competition_id);

        return GamePlayer::query()
            ->where('game_players.game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->whereNotIn('id', $activeIds)
            ->whereNotIn('id', $suspendedIds)
            ->when($game->requiresSquadEnrollment(), fn ($q) => $q->whereNotNull('number'))
            ->notInjuredOn($match->scheduled_date)
            ->count();
    }
}
