<?php

namespace App\Http\Actions;

use App\Modules\Match\Services\MatchdayOrchestrator;
use App\Modules\Match\Services\MatchFinalizationService;
use App\Models\Game;
use App\Models\GameMatch;
use Illuminate\Http\Request;

class FinalizeMatch
{
    public function __construct(
        private readonly MatchFinalizationService $finalizationService,
        private readonly MatchdayOrchestrator $orchestrator,
    ) {}

    public function __invoke(Request $request, string $gameId)
    {
        $game = Game::findOrFail($gameId);

        $matchId = $game->pending_finalization_match_id;

        if (! $matchId) {
            return redirect()->route('show-game', $gameId);
        }

        $match = GameMatch::find($matchId);

        if (! $match || ! $match->played) {
            $game->update(['pending_finalization_match_id' => null]);

            return redirect()->route('show-game', $gameId);
        }

        $this->finalizationService->finalize($match, $game);

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

            if (in_array($result->type, ['blocked', 'done', 'season_complete'])) {
                break;
            }

            $game->refresh()->setRelations([]);
        }

        return redirect()->route('game.tournament-end', $game->id);
    }
}
