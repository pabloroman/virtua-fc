<?php

namespace App\Http\Actions;

use App\Events\SeasonCompleted;
use App\Models\ActivationEvent;
use App\Modules\Match\Services\MatchdayOrchestrator;
use App\Modules\Match\Services\MatchFinalizationService;
use App\Modules\Report\Services\TournamentSnapshotService;
use App\Modules\Season\Services\ActivationTracker;
use App\Modules\Season\Services\GameDeletionService;
use App\Models\Game;
use App\Models\GameMatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FinalizeMatch
{
    public function __construct(
        private readonly MatchFinalizationService $finalizationService,
        private readonly MatchdayOrchestrator $orchestrator,
        private readonly ActivationTracker $activationTracker,
        private readonly TournamentSnapshotService $snapshotService,
        private readonly GameDeletionService $deletionService,
    ) {}

    public function __invoke(Request $request, string $gameId)
    {
        // Atomic finalization: lock the game row to prevent double-submit
        // (user clicking Continue twice) and races with the finalizePendingMatch
        // safety net in MatchdayOrchestrator::advance().
        $game = DB::transaction(function () use ($gameId) {
            $game = Game::where('id', $gameId)->lockForUpdate()->first();

            if (! $game) {
                return null;
            }

            $matchId = $game->pending_finalization_match_id;

            if (! $matchId) {
                return $game;
            }

            $match = GameMatch::find($matchId);

            if (! $match || ! $match->played) {
                $game->update(['pending_finalization_match_id' => null]);

                return $game;
            }

            $this->finalizationService->finalize($match, $game);

            return $game;
        });

        if (! $game) {
            return redirect()->route('show-game', $gameId);
        }

        // Fire SeasonCompleted if no unplayed matches remain after finalization.
        // This covers the case where the player's match is the last match of the
        // season (e.g. promotion playoff final) — ShowGame would redirect straight
        // to season-end, bypassing AdvanceMatchday which normally fires this event.
        $hasRemainingMatches = GameMatch::where('game_id', $game->id)
            ->where('played', false)
            ->exists();

        if (! $hasRemainingMatches) {
            if ($game->isTournamentMode()) {
                return $this->completeTournament($game);
            }

            event(new SeasonCompleted($game));
        }

        // Tournament auto-simulation: advance all remaining matches and go to summary
        if ($request->has('tournament_end') && $game->isTournamentMode()) {
            return $this->simulateRemainingAndRedirect($game);
        }

        return redirect()->route('show-game', $gameId);
    }

    private function simulateRemainingAndRedirect(Game $game)
    {
        $game->refresh()->setRelations([]);

        for ($i = 0; $i < 500; $i++) {
            $result = $this->orchestrator->advance($game);

            if ($result->type === 'live_match') {
                return redirect()->route('game.live-match', [
                    'gameId' => $game->id,
                    'matchId' => $result->matchId,
                ]);
            }

            if ($result->type === 'blocked') {
                // Transient block — redirect back to game view instead of ending tournament
                return redirect()->route('show-game', $game->id)
                    ->with('warning', __('messages.action_required'));
            }

            if (in_array($result->type, ['done', 'season_complete'])) {
                break;
            }

            $game->refresh()->setRelations([]);
        }

        return $this->completeTournament($game);
    }

    private function completeTournament(Game $game)
    {
        $this->activationTracker->record($game->user_id, ActivationEvent::EVENT_TOURNAMENT_COMPLETED, $game->id, Game::MODE_TOURNAMENT);

        $summary = $this->snapshotService->createSnapshot($game);
        $this->deletionService->delete($game);

        return redirect()->route('tournament-summary.show', $summary->id);
    }
}
