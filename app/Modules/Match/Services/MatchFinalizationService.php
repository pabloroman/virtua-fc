<?php

namespace App\Modules\Match\Services;

use App\Modules\Competition\Services\CompetitionHandlerResolver;
use App\Modules\Match\Events\CupTieResolved;
use App\Modules\Match\Events\MatchFinalized;
use App\Modules\Player\Services\PlayerConditionService;
use App\Models\Competition;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\MatchEvent;
use App\Models\PlayerSuspension;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class MatchFinalizationService
{
    public function __construct(
        private readonly CupTieResolver $cupTieResolver,
        private readonly CompetitionHandlerResolver $handlerResolver,
        private readonly PlayerConditionService $conditionService,
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

        // 1. Apply fitness/morale changes for the user's match (deferred from batch processing)
        $this->updateConditionsForDeferredMatch($match);

        // 2. Serve deferred suspensions for both teams in this match
        $this->serveDeferredSuspensions($match);

        // 3. Resolve cup tie and dispatch CupTieResolved if applicable
        if ($match->cup_tie_id !== null) {
            $this->resolveCupTie($match, $game, $competition);
        }

        // 4. Dispatch MatchFinalized for standings, GK stats, and notifications
        MatchFinalized::dispatch($match, $game, $competition);

        // 5. Clear the pending flag
        $game->update(['pending_finalization_match_id' => null]);

        // 6. Generate any pending knockout/playoff fixtures now that standings are final.
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

    /**
     * Apply fitness/morale changes for the deferred (live) match.
     *
     * During batch processing the user's match is included but hasn't been played
     * yet by the user, so their players get recovery without match fitness loss.
     * This method applies the correct update after the match is finalized.
     */
    private function updateConditionsForDeferredMatch(GameMatch $match): void
    {
        $teamIds = [$match->home_team_id, $match->away_team_id];
        $players = GamePlayer::with('player')
            ->where('game_id', $match->game_id)
            ->whereIn('team_id', $teamIds)
            ->get();

        $playersByTeam = collect([
            $match->home_team_id => $players->filter(fn ($p) => $p->team_id === $match->home_team_id),
            $match->away_team_id => $players->filter(fn ($p) => $p->team_id === $match->away_team_id),
        ]);

        // Compute per-team recovery days from their last match before this one
        $recoveryDaysByTeam = [];
        foreach ($teamIds as $tid) {
            $lastPlayed = DB::table('game_matches')
                ->where('game_id', $match->game_id)
                ->where('played', true)
                ->where('scheduled_date', '<', $match->scheduled_date->toDateString())
                ->where('id', '!=', $match->id)
                ->where(fn ($q) => $q->where('home_team_id', $tid)->orWhere('away_team_id', $tid))
                ->max('scheduled_date');

            $recoveryDaysByTeam[$tid] = $lastPlayed
                ? (int) Carbon::parse($lastPlayed)->diffInDays($match->scheduled_date)
                : 7;
        }

        // Build match result from stored events
        $events = MatchEvent::where('game_match_id', $match->id)
            ->get(['event_type', 'game_player_id', 'minute', 'team_id'])
            ->map(fn ($e) => $e->toArray())
            ->toArray();

        $matchResults = [[
            'matchId' => $match->id,
            'events' => $events,
        ]];

        $this->conditionService->batchUpdateAfterMatchday(
            collect([$match]),
            $matchResults,
            $playersByTeam,
            $recoveryDaysByTeam,
            $match->scheduled_date,
        );
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
