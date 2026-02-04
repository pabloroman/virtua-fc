<?php

namespace App\Game\Handlers;

use App\Game\Commands\ConductCupDraw;
use App\Game\Contracts\CompetitionHandler;
use App\Game\Game as GameAggregate;
use App\Game\Services\CupDrawService;
use App\Game\Services\CupTieResolver;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameMatch;
use Illuminate\Support\Collection;

class KnockoutCupHandler implements CompetitionHandler
{
    public function __construct(
        private readonly CupDrawService $cupDrawService,
        private readonly CupTieResolver $cupTieResolver,
    ) {}

    public function getType(): string
    {
        return 'knockout_cup';
    }

    /**
     * Get all unplayed cup matches from the same date.
     * Cup matches are grouped by date, not round number.
     */
    public function getMatchBatch(string $gameId, GameMatch $nextMatch): Collection
    {
        return GameMatch::with(['homeTeam', 'awayTeam', 'cupTie'])
            ->where('game_id', $gameId)
            ->whereDate('scheduled_date', $nextMatch->scheduled_date->toDateString())
            ->whereNotNull('cup_tie_id')
            ->where('played', false)
            ->get();
    }

    /**
     * No pre-match actions needed - draws now happen after rounds complete.
     */
    public function beforeMatches(Game $game, string $targetDate): void
    {
        // Draws are now conducted after rounds complete, not before matches
    }

    /**
     * Resolve cup ties after matches have been played, then draw the next round if ready.
     */
    public function afterMatches(Game $game, Collection $matches, Collection $allPlayers): void
    {
        $this->resolveCupTies($matches, $allPlayers, $game->id);

        // After resolving ties, check if next round can be drawn
        $this->conductNextRoundDrawIfReady($game, $matches);
    }

    /**
     * Redirect to the cup bracket page, or game dashboard if not participating.
     */
    public function getRedirectRoute(Game $game, Collection $matches, int $matchday): string
    {
        $game->refresh();

        // Check if player's team participated in these matches
        $playerTeamPlayed = $matches->contains(function ($match) use ($game) {
            return $match->home_team_id === $game->team_id
                || $match->away_team_id === $game->team_id;
        });

        // Only show cup page if player's team was involved and not eliminated
        if ($playerTeamPlayed && !$game->cup_eliminated) {
            $competitionId = $matches->first()?->competition_id ?? 'ESPCUP';
            return route('game.competition', [$game->id, $competitionId]);
        }

        // If player didn't participate in these cup matches, go to dashboard
        // so they can see their actual next match
        return route('show-game', $game->id);
    }

    /**
     * Conduct the next round draw if the previous round is complete.
     */
    private function conductNextRoundDrawIfReady(Game $game, Collection $matches): void
    {
        // Get competition ID from the matches just played
        $competitionId = $matches->first()?->competition_id;

        if (!$competitionId) {
            return;
        }

        // Check if next round needs a draw
        $nextRound = $this->cupDrawService->getNextRoundNeedingDraw($game->id, $competitionId);

        if ($nextRound === null) {
            return;
        }

        // Conduct the draw
        $ties = $this->cupDrawService->conductDraw($game->id, $competitionId, $nextRound);

        // Record the event
        $command = new ConductCupDraw(
            competitionId: $competitionId,
            roundNumber: $nextRound,
        );

        $aggregate = GameAggregate::retrieve($game->id);
        $aggregate->conductCupDraw($command, $ties->pluck('id')->toArray());
    }

    /**
     * Resolve cup ties after matches have been played.
     */
    private function resolveCupTies(Collection $cupMatches, Collection $allPlayers, string $gameId): void
    {
        // Get unique tie IDs from the matches
        $tieIds = $cupMatches->pluck('cup_tie_id')->unique()->filter();

        foreach ($tieIds as $tieId) {
            $tie = CupTie::with(['firstLegMatch', 'secondLegMatch'])->find($tieId);

            if (!$tie || $tie->completed) {
                continue;
            }

            // Try to resolve the tie
            $winnerId = $this->cupTieResolver->resolve($tie, $allPlayers);

            if ($winnerId) {
                // Record the cup tie completion event
                $aggregate = GameAggregate::retrieve($gameId);
                $aggregate->completeCupTie(
                    tieId: $tie->id,
                    competitionId: $tie->competition_id,
                    roundNumber: $tie->round_number,
                    winnerId: $winnerId,
                    loserId: $tie->getLoserId(),
                    resolution: $tie->fresh()->resolution ?? [],
                );
            }
        }
    }
}
