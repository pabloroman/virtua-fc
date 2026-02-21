<?php

namespace App\Modules\Match\Handlers;

use App\Modules\Competition\Contracts\CompetitionHandler;
use App\Modules\Competition\Services\CupDrawService;
use App\Modules\Match\Services\CupTieResolver;
use App\Models\Competition;
use App\Models\CupTie;
use App\Models\FinancialTransaction;
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
        $this->resolveCupTies($game, $matches, $allPlayers);

        // After resolving ties, check if next round can be drawn
        $this->conductNextRoundDrawIfReady($game, $matches);
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
     * Conduct the next round draw if the previous round is complete.
     */
    private function conductNextRoundDrawIfReady(Game $game, Collection $matches): void
    {
        $competitionId = $matches->first()?->competition_id;

        if (!$competitionId) {
            return;
        }

        $nextRound = $this->cupDrawService->getNextRoundNeedingDraw($game->id, $competitionId);

        if ($nextRound === null) {
            return;
        }

        $this->cupDrawService->conductDraw($game->id, $competitionId, $nextRound);
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
                $this->awardCupPrizeMoney($game, $tie->competition_id, $tie->round_number, $winnerId);
            }
        }
    }

    /**
     * Award prize money for advancing in a cup competition.
     */
    private function awardCupPrizeMoney(Game $game, string $competitionId, int $roundNumber, string $winnerId): void
    {
        if ($winnerId !== $game->team_id) {
            return;
        }

        $prizeAmounts = [
            1 => 10_000_000,      // €100K - Round of 64/32
            2 => 20_000_000,      // €200K - Round of 32/16
            3 => 30_000_000,      // €300K - Round of 16
            4 => 50_000_000,      // €500K - Quarter-finals
            5 => 100_000_000,     // €1M - Semi-finals
            6 => 200_000_000,     // €2M - Final
        ];

        $amount = $prizeAmounts[$roundNumber] ?? $prizeAmounts[1];

        $competition = Competition::find($competitionId);
        $competitionName = $competition->name ?? 'Cup';

        FinancialTransaction::recordIncome(
            gameId: $game->id,
            category: FinancialTransaction::CATEGORY_CUP_BONUS,
            amount: $amount,
            description: __('finances.tx_cup_advancement', ['competition' => $competitionName, 'round' => $roundNumber]),
            transactionDate: $game->current_date->toDateString(),
        );
    }
}
