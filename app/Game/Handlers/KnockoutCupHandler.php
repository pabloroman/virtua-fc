<?php

namespace App\Game\Handlers;

use App\Game\Commands\ConductCupDraw;
use App\Game\Contracts\CompetitionHandler;
use App\Game\Game as GameAggregate;
use App\Game\Services\CupDrawService;
use App\Game\Services\CupTieResolver;
use App\Models\CupRoundTemplate;
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
        // Retrieve the aggregate once for all cup operations
        $aggregate = GameAggregate::retrieve($game->id);

        $this->resolveCupTies($matches, $allPlayers, $aggregate);

        // After resolving ties, check if next round can be drawn
        $this->conductNextRoundDrawIfReady($game, $matches, $aggregate);
    }

    /**
     * Redirect to the match results page to show cup results.
     */
    public function getRedirectRoute(Game $game, Collection $matches, int $matchday): string
    {
        return route('game.results', [
            'gameId' => $game->id,
            'competition' => $matches->first()?->competition_id ?? $game->competition_id,
            'matchday' => $matches->first()?->round_number ?? $matchday,
        ]);
    }

    /**
     * Conduct the next round draw if the previous round is complete.
     */
    private function conductNextRoundDrawIfReady(Game $game, Collection $matches, GameAggregate $aggregate): void
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

        $aggregate->conductCupDraw($command, $ties->pluck('id')->toArray());
    }

    /**
     * Resolve cup ties after matches have been played.
     */
    private function resolveCupTies(Collection $cupMatches, Collection $allPlayers, GameAggregate $aggregate): void
    {
        // Batch-load all ties with their matches and teams in one query
        $tieIds = $cupMatches->pluck('cup_tie_id')->unique()->filter()->values();
        $ties = CupTie::with([
                'firstLegMatch.homeTeam', 'firstLegMatch.awayTeam',
                'secondLegMatch.homeTeam', 'secondLegMatch.awayTeam',
            ])
            ->whereIn('id', $tieIds)
            ->where('completed', false)
            ->get();

        if ($ties->isEmpty()) {
            return;
        }

        // Pre-load the round template once (all ties in a batch share the same round)
        $firstTie = $ties->first();
        $roundTemplate = CupRoundTemplate::where('competition_id', $firstTie->competition_id)
            ->where('round_number', $firstTie->round_number)
            ->first();

        foreach ($ties as $tie) {
            $winnerId = $this->cupTieResolver->resolve($tie, $allPlayers, $roundTemplate);

            if ($winnerId) {
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
