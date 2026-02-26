<?php

namespace App\Modules\Match\Handlers;

use App\Modules\Competition\Contracts\CompetitionHandler;
use App\Modules\Match\Events\CupTieResolved;
use App\Modules\Match\Services\CupTieResolver;
use App\Modules\Squad\Services\EligibilityService;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameMatch;
use Illuminate\Support\Collection;

class KnockoutCupHandler implements CompetitionHandler
{
    public function __construct(
        private readonly CupTieResolver $cupTieResolver,
        private readonly EligibilityService $eligibilityService,
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
     * Resolve cup ties after matches have been played.
     */
    public function afterMatches(Game $game, Collection $matches, Collection $allPlayers): void
    {
        $this->resolveCupTies($game, $matches, $allPlayers);

        // Reset yellow cards if a completed round matches the reset threshold
        $this->maybeResetYellowCards($game, $matches);
    }

    /**
     * Redirect to the match results page to show cup results.
     */
    public function getRedirectRoute(Game $game, Collection $matches, int $matchday): string
    {
        $firstMatch = $matches->first();

        return route('game.results', array_filter([
            'gameId' => $game->id,
            'competition' => $firstMatch->competition_id ?? $game->competition_id,
            'matchday' => $firstMatch->round_number ?? $matchday,
            'round' => $firstMatch?->round_name,
        ]));
    }

    /**
     * Resolve cup ties after matches have been played.
     */
    private function resolveCupTies(Game $game, Collection $cupMatches, Collection $allPlayers): void
    {
        $tieIds = $cupMatches->pluck('cup_tie_id')->unique()->filter()->values();
        $ties = CupTie::with([
                'firstLegMatch.homeTeam', 'firstLegMatch.awayTeam',
                'secondLegMatch.homeTeam', 'secondLegMatch.awayTeam',
                'competition',
            ])
            ->whereIn('id', $tieIds)
            ->where('completed', false)
            ->get();

        if ($ties->isEmpty()) {
            return;
        }

        $firstTie = $ties->first();
        $roundConfig = $firstTie->getRoundConfig();

        foreach ($ties as $tie) {
            $winnerId = $this->cupTieResolver->resolve($tie, $allPlayers, $roundConfig);

            if ($winnerId) {
                $match = $tie->secondLegMatch ?? $tie->firstLegMatch;
                CupTieResolved::dispatch($tie, $winnerId, $match, $game, $tie->competition);
            }
        }
    }

    /**
     * Reset yellow cards if the just-completed round matches the reset threshold.
     */
    private function maybeResetYellowCards(Game $game, Collection $matches): void
    {
        $competitionId = $matches->first()?->competition_id;
        if (!$competitionId) {
            return;
        }

        $rules = $this->eligibilityService->rulesForHandlerType('knockout_cup');
        if ($rules->yellowCardResetAfterRound === null) {
            return;
        }

        // Check if the reset round just completed (all ties resolved)
        $resetRound = $rules->yellowCardResetAfterRound;
        $allComplete = CupTie::where('game_id', $game->id)
            ->where('competition_id', $competitionId)
            ->where('round_number', $resetRound)
            ->where('completed', false)
            ->doesntExist();

        $roundExists = CupTie::where('game_id', $game->id)
            ->where('competition_id', $competitionId)
            ->where('round_number', $resetRound)
            ->exists();

        if ($roundExists && $allComplete) {
            // Only reset once — check if a later round already has ties (reset already happened)
            $laterRoundExists = CupTie::where('game_id', $game->id)
                ->where('competition_id', $competitionId)
                ->where('round_number', '>', $resetRound)
                ->exists();

            if (!$laterRoundExists) {
                $this->eligibilityService->resetYellowCardsForCompetition($game->id, $competitionId);
            }
        }
    }

}
