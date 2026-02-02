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
     * Conduct any pending cup draws before matches are simulated.
     */
    public function beforeMatches(Game $game, string $targetDate): void
    {
        $this->conductPendingCupDraws($game, $targetDate);
    }

    /**
     * Resolve cup ties after matches have been played.
     */
    public function afterMatches(Game $game, Collection $matches, Collection $allPlayers): void
    {
        $this->resolveCupTies($matches, $allPlayers, $game->id);
    }

    /**
     * Redirect to the cup bracket page, or league results if not participating.
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
            return route('game.cup', $game->id);
        }

        // Otherwise redirect to league results
        return route('game.results', [
            'gameId' => $game->id,
            'competition' => $game->competition_id,
            'matchday' => $game->current_matchday,
        ]);
    }

    /**
     * Conduct any cup draws that are needed before the given date.
     */
    private function conductPendingCupDraws(Game $game, string $targetDate): void
    {
        // Get all cup competitions this handler manages
        $cupCompetitions = \App\Models\Competition::where('handler_type', 'knockout_cup')->pluck('id');

        foreach ($cupCompetitions as $competitionId) {
            $cupRounds = CupRoundTemplate::where('competition_id', $competitionId)
                ->where('season', $game->season)
                ->whereDate('first_leg_date', '<=', $targetDate)
                ->orderBy('round_number')
                ->get();

            foreach ($cupRounds as $round) {
                if ($this->cupDrawService->needsDrawForRound($game->id, $competitionId, $round->round_number)) {
                    // Conduct the draw
                    $ties = $this->cupDrawService->conductDraw($game->id, $competitionId, $round->round_number);

                    // Record the event
                    $command = new ConductCupDraw(
                        competitionId: $competitionId,
                        roundNumber: $round->round_number,
                    );

                    $aggregate = GameAggregate::retrieve($game->id);
                    $aggregate->conductCupDraw($command, $ties->pluck('id')->toArray());
                }
            }
        }
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
