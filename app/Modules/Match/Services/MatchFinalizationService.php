<?php

namespace App\Modules\Match\Services;

use App\Events\SeasonCompleted;
use App\Modules\Competition\Services\CompetitionHandlerResolver;
use App\Modules\Match\DTOs\MatchFinalizationResult;
use App\Modules\Match\Events\CupTieResolved;
use App\Modules\Match\Events\GameDateAdvanced;
use App\Modules\Match\Events\MatchFinalized;
use App\Modules\Player\Services\PlayerConditionService;
use App\Modules\Season\Listeners\DetectTournamentEnded;
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
        private readonly DetectTournamentEnded $tournamentEndDetector,
    ) {}

    /**
     * Finalize the game's pending match if one is outstanding.
     *
     * Safety net for HTTP entry points (ShowGame, ShowSeasonEnd, StartNewSeason)
     * that can be reached without traversing the FinalizeMatch action — most
     * commonly when the user abandons the live-match screen right before
     * end-of-season. The MatchdayOrchestrator's own safety net only catches
     * this on the next advance(), which never happens once the season is over.
     *
     * Mirrors FinalizeMatch: locks the game row, finalizes if pending, and
     * fires SeasonCompleted when this was the last outstanding match. Safe to
     * call on every request — no-ops when pending_finalization_match_id is null.
     */
    public function finalizePendingIfAny(string $gameId): bool
    {
        $finalizationResult = DB::transaction(function () use ($gameId) {
            $game = Game::where('id', $gameId)->lockForUpdate()->first();

            if (! $game || ! $game->pending_finalization_match_id) {
                return null;
            }

            $match = GameMatch::find($game->pending_finalization_match_id);

            if (! $match || ! $match->played) {
                $game->update(['pending_finalization_match_id' => null]);

                return null;
            }

            return $this->finalize($match, $game);
        });

        if (! $finalizationResult) {
            return false;
        }

        // Side effects (event dispatches, beforeMatches, date advance, tournament
        // end detection) run AFTER the lock-protected transaction commits so a
        // sibling finalize on the same game can't pile up behind them.
        $this->dispatchPostFinalizeEffects($finalizationResult);

        // Mirror FinalizeMatch: when no unplayed matches remain after the
        // finalize, fire SeasonCompleted so downstream listeners (other-league
        // simulation, activation records) run. Tournament mode has its own
        // TournamentEnded chain dispatched from dispatchPostFinalizeEffects().
        $hasRemainingMatches = GameMatch::where('game_id', $finalizationResult->game->id)
            ->where('played', false)
            ->exists();

        if (! $hasRemainingMatches && ! $finalizationResult->game->isTournamentMode()) {
            event(new SeasonCompleted($finalizationResult->game));
        }

        return true;
    }

    /**
     * Apply the lock-protected mutations for a finalized match and return the
     * data needed by {@see dispatchPostFinalizeEffects()} to fire side effects
     * after the caller's transaction commits.
     *
     * The split is deliberate: side-effect listeners (notably
     * UpdateManagerStats, which writes to the control-plane manager_stats
     * table via firstOrCreate) used to run synchronously inside the
     * `lockForUpdate()` on the games row, so two concurrent finalize
     * requests for the same game would queue up behind each other on the
     * games lock and the manager_stats unique-constraint speculative
     * insert. PHP's 30-second limit then killed the queued requests.
     *
     * Callers MUST invoke {@see dispatchPostFinalizeEffects()} on the
     * returned result *after* their transaction commits.
     */
    public function finalize(GameMatch $match, Game $game): MatchFinalizationResult
    {
        $previousDate = $game->current_date->copy();
        $competition = Competition::find($match->competition_id);

        // 1. Apply fitness/morale changes for the user's match (deferred from batch processing)
        $this->updateConditionsForDeferredMatch($match);

        // 2. Serve deferred suspensions for both teams in this match
        $this->serveDeferredSuspensions($match);

        // 3. Resolve cup tie mutations (the CupTieResolved event dispatch is
        // deferred to dispatchPostFinalizeEffects so listeners don't run
        // under the games lock).
        $resolvedCupTie = null;
        $cupTieWinnerId = null;
        if ($match->cup_tie_id !== null) {
            [$resolvedCupTie, $cupTieWinnerId] = $this->resolveCupTie($match);
        }

        // 4. Clear the pending flag last so any sibling request blocked on
        // lockForUpdate() finds it null when this commit releases the lock
        // and short-circuits without re-applying steps 1-3.
        $game->update(['pending_finalization_match_id' => null]);
        session()->forget("live_match_animated:{$match->id}");

        return new MatchFinalizationResult(
            match: $match,
            game: $game,
            competition: $competition,
            previousDate: $previousDate,
            resolvedCupTie: $resolvedCupTie,
            cupTieWinnerId: $cupTieWinnerId,
        );
    }

    /**
     * Dispatch all post-finalize side effects. Must be called *after* the
     * caller's lock-protected transaction has committed so listeners and
     * the beforeMatches handler don't extend the games row lock.
     *
     * The side effects are individually idempotent (standings_applied flag,
     * cupTie->completed guard, ManagerStats::firstOrCreate, etc.) so
     * re-running is safe if the caller retries.
     */
    public function dispatchPostFinalizeEffects(MatchFinalizationResult $result): void
    {
        // 1. CupTieResolved before MatchFinalized so cup-prize / next-round
        // draws settle before standings notifications go out.
        if ($result->resolvedCupTie !== null && $result->cupTieWinnerId !== null) {
            CupTieResolved::dispatch(
                $result->resolvedCupTie,
                $result->cupTieWinnerId,
                $result->match,
                $result->game,
                $result->competition,
            );
        }

        // 2. MatchFinalized for standings, GK stats, and notifications.
        MatchFinalized::dispatch($result->match, $result->game, $result->competition);

        // 3. Advance current_date to the next upcoming match (forward-looking calendar).
        // This ensures transfer windows and other date-based logic reflect where
        // the season calendar actually is, not when the last match was played.
        $this->advanceCurrentDate($result->game, $result->previousDate);

        // 4. Generate any pending knockout/playoff fixtures now that standings are final.
        // This covers both league matches (where standings determine playoff seedings)
        // and cup ties (where completing a round may trigger the next round draw,
        // especially for group_stage_cup competitions like the World Cup).
        if ($result->competition) {
            $handler = $this->handlerResolver->resolve($result->competition);
            $handler->beforeMatches($result->game, $result->game->current_date->toDateString());
        }

        // 5. Re-advance current_date if step 4 generated new matches (e.g. 3rd-place +
        // final after both semifinals completed). Step 3 may have found no matches
        // because they didn't exist yet.
        $this->advanceCurrentDate($result->game, $result->previousDate);

        // 6. Detect tournament end AFTER beforeMatches so group_stage_cup competitions
        // (like the World Cup) have had a chance to generate the next knockout round.
        // Otherwise the "no unplayed matches" check is briefly true between rounds and
        // ends the tournament prematurely after the group phase.
        $this->tournamentEndDetector->detect($result->game->refresh());
    }

    /**
     * Advance the game's current_date to the next unplayed match if one exists.
     */
    private function advanceCurrentDate(Game $game, Carbon $previousDate): void
    {
        if ($game->advanceDateToNextMatch() && $game->current_date->gt($previousDate)) {
            GameDateAdvanced::dispatch($game, $previousDate, $game->current_date);
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

        // game_id is required to hit the partial index
        // player_suspensions_active_idx (game_id, competition_id) WHERE matches_remaining > 0.
        PlayerSuspension::where('game_id', $match->game_id)
            ->where('competition_id', $match->competition_id)
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
        $players = GamePlayer::with(['matchState'])
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

    /**
     * Apply cup-tie resolution mutations and return [cupTie, winnerId] so
     * the caller can dispatch CupTieResolved after the transaction commits.
     * Returns [null, null] if there's nothing to resolve (already completed
     * or insufficient data).
     *
     * @return array{0: ?CupTie, 1: ?string}
     */
    private function resolveCupTie(GameMatch $match): array
    {
        $cupTie = CupTie::with([
            'firstLegMatch.homeTeam', 'firstLegMatch.awayTeam',
            'secondLegMatch.homeTeam', 'secondLegMatch.awayTeam',
        ])->find($match->cup_tie_id);

        if (! $cupTie || $cupTie->completed) {
            return [null, null];
        }

        // Build players collection for extra time / penalty simulation
        $allLineupIds = array_merge($match->home_lineup ?? [], $match->away_lineup ?? []);
        $players = GamePlayer::with(['matchState'])->whereIn('id', $allLineupIds)->get();
        $allPlayers = collect([
            $match->home_team_id => $players->filter(fn ($p) => $p->team_id === $match->home_team_id),
            $match->away_team_id => $players->filter(fn ($p) => $p->team_id === $match->away_team_id),
        ]);

        $winnerId = $this->cupTieResolver->resolve($cupTie, $allPlayers);

        if (! $winnerId) {
            return [null, null];
        }

        return [$cupTie, $winnerId];
    }
}
