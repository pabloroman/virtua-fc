<?php

namespace App\Http\Actions;

use App\Game\Events\CupTieResolved;
use App\Game\Events\MatchFinalized;
use App\Game\Services\CupTieResolver;
use App\Models\Competition;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use Illuminate\Http\Request;

class FinalizeMatch
{
    public function __construct(
        private readonly CupTieResolver $cupTieResolver,
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

        $this->finalizeMatch($match, $game);

        return redirect()->route('show-game', $gameId);
    }

    /**
     * Apply all deferred score-dependent side effects for a match.
     *
     * Core logic (cup tie resolution) runs here. All other side effects
     * (standings, GK stats, notifications, prize money, draws) are handled
     * by listeners on MatchFinalized and CupTieResolved events.
     */
    public function finalizeMatch(GameMatch $match, Game $game): void
    {
        $competition = Competition::find($match->competition_id);

        // 1. Resolve cup tie and dispatch CupTieResolved if applicable
        if ($match->cup_tie_id !== null) {
            $this->resolveCupTie($match, $game, $competition);
        }

        // 2. Dispatch MatchFinalized for standings, GK stats, and notifications
        MatchFinalized::dispatch($match, $game, $competition);

        // 3. Clear the pending flag
        $game->update(['pending_finalization_match_id' => null]);
    }

    private function resolveCupTie(GameMatch $match, Game $game, ?Competition $competition): void
    {
        $cupTie = CupTie::with([
            'firstLegMatch.homeTeam', 'firstLegMatch.awayTeam',
            'secondLegMatch.homeTeam', 'secondLegMatch.awayTeam',
        ])->find($match->cup_tie_id);

        if (! $cupTie || $cupTie->completed) {
            return;
        }

        // Build players collection for extra time / penalty simulation
        $allLineupIds = array_merge($match->home_lineup ?? [], $match->away_lineup ?? []);
        $players = GamePlayer::with('player')->whereIn('id', $allLineupIds)->get();
        $allPlayers = collect([
            $match->home_team_id => $players->filter(fn ($p) => $p->team_id === $match->home_team_id),
            $match->away_team_id => $players->filter(fn ($p) => $p->team_id === $match->away_team_id),
        ]);

        $winnerId = $this->cupTieResolver->resolve($cupTie, $allPlayers);

        if (! $winnerId) {
            return;
        }

        CupTieResolved::dispatch($cupTie, $winnerId, $match, $game, $competition);
    }
}
