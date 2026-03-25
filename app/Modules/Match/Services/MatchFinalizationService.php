<?php

namespace App\Modules\Match\Services;

use App\Modules\Competition\Services\CompetitionHandlerResolver;
use App\Modules\Match\Events\CupTieResolved;
use App\Modules\Match\Events\MatchFinalized;
use App\Models\Competition;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\MatchEvent;
use App\Models\PlayerSuspension;

class MatchFinalizationService
{
    public function __construct(
        private readonly CupTieResolver $cupTieResolver,
        private readonly CompetitionHandlerResolver $handlerResolver,
    ) {}

    /**
     * Apply all deferred score-dependent side effects for a match.
     *
     * Core logic (cup tie resolution) runs here. All other side effects
     * (standings, GK stats, notifications, prize money, draws) are handled
     * by listeners on MatchFinalized and CupTieResolved events.
     */
    public function finalize(GameMatch $match, Game $game): void
    {
        $competition = Competition::find($match->competition_id);

        // 1. Serve deferred suspensions for both teams in this match
        $this->serveDeferredSuspensions($match);

        // 2. Resolve cup tie and dispatch CupTieResolved if applicable
        if ($match->cup_tie_id !== null) {
            $this->resolveCupTie($match, $game, $competition);
        }

        // 3. Dispatch MatchFinalized for standings, GK stats, and notifications
        MatchFinalized::dispatch($match, $game, $competition);

        // 4. Clear the pending flag
        $game->update(['pending_finalization_match_id' => null]);

        // 5. Generate any pending knockout/playoff fixtures now that standings are final.
        // This covers both league matches (where standings determine playoff seedings)
        // and cup ties (where completing a round may trigger the next round draw,
        // especially for group_stage_cup competitions like the World Cup).
        if ($competition) {
            $handler = $this->handlerResolver->resolve($competition);
            $handler->beforeMatches($game, $game->current_date->toDateString());
        }
    }

    /**
     * Serve suspensions that were deferred during batch processing.
     * These belong to players on the two teams in the deferred match.
     *
     * Excludes players who received cards in this match — any active suspension
     * they carry was created from this match's events (suspended players can't
     * be in lineups) and applies to future matches, not this one.
     */
    private function serveDeferredSuspensions(GameMatch $match): void
    {
        $teamPlayerSubquery = GamePlayer::where('game_id', $match->game_id)
            ->whereIn('team_id', [$match->home_team_id, $match->away_team_id])
            ->select('id');

        $cardPlayerSubquery = MatchEvent::where('game_match_id', $match->id)
            ->whereIn('event_type', [MatchEvent::TYPE_RED_CARD, MatchEvent::TYPE_YELLOW_CARD])
            ->select('game_player_id');

        PlayerSuspension::where('competition_id', $match->competition_id)
            ->where('matches_remaining', '>', 0)
            ->whereIn('game_player_id', $teamPlayerSubquery)
            ->whereNotIn('game_player_id', $cardPlayerSubquery)
            ->decrement('matches_remaining');
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
