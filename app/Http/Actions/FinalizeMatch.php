<?php

namespace App\Http\Actions;

use App\Events\SeasonCompleted;
use App\Models\TournamentSummary;
use App\Modules\Match\Services\MatchdayOrchestrator;
use App\Modules\Match\Services\MatchFinalizationService;
use App\Models\Game;
use App\Models\GameMatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FinalizeMatch
{
    public function __construct(
        private readonly MatchFinalizationService $finalizationService,
        private readonly MatchdayOrchestrator $orchestrator,
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
        // Tournament mode is handled by the TournamentEnded event chain dispatched
        // from the DetectTournamentEnded listener on MatchFinalized.
        $hasRemainingMatches = GameMatch::where('game_id', $game->id)
            ->where('played', false)
            ->exists();

        if (! $hasRemainingMatches) {
            if ($game->isTournamentMode()) {
                return $this->redirectToTournamentSummary($game);
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

        return $this->redirectToTournamentSummary($game);
    }

    private function redirectToTournamentSummary(Game $game)
    {
        $summary = TournamentSummary::where('original_game_id', $game->id)->first();

        if (! $summary) {
            // Should not happen: the TournamentEnded listener chain persists the
            // summary synchronously before this method runs. Fall back to the game
            // view rather than hard-failing.
            return redirect()->route('show-game', $game->id);
        }

        return redirect()->route('tournament-summary.show', $summary->id);
    }
}
