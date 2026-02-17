<?php

namespace App\Http\Actions;

use App\Game\Services\CupDrawService;
use App\Game\Services\CupTieResolver;
use App\Game\Services\NotificationService;
use App\Game\Services\StandingsCalculator;
use App\Models\Competition;
use App\Models\CupTie;
use App\Models\FinancialTransaction;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use Illuminate\Http\Request;

class FinalizeMatch
{
    public function __construct(
        private readonly StandingsCalculator $standingsCalculator,
        private readonly CupTieResolver $cupTieResolver,
        private readonly CupDrawService $cupDrawService,
        private readonly NotificationService $notificationService,
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
     */
    public function finalizeMatch(GameMatch $match, Game $game): void
    {
        $competition = Competition::find($match->competition_id);
        $isCupTie = $match->cup_tie_id !== null;

        // 1. Update standings (league matches only)
        if ($competition?->isLeague() && ! $isCupTie) {
            $this->standingsCalculator->updateAfterMatch(
                gameId: $game->id,
                competitionId: $match->competition_id,
                homeTeamId: $match->home_team_id,
                awayTeamId: $match->away_team_id,
                homeScore: $match->home_score,
                awayScore: $match->away_score,
            );
            $this->standingsCalculator->recalculatePositions($game->id, $match->competition_id);
        }

        // 2. Update goalkeeper stats
        $this->updateGoalkeeperStats($match);

        // 3. Resolve cup tie (knockout matches only)
        if ($isCupTie) {
            $this->resolveCupTie($match, $game, $competition);
        }

        // Clear the pending flag
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

        // Award prize money if user team advances
        if ($winnerId === $game->team_id) {
            $this->awardCupPrizeMoney($game, $competition, $cupTie->round_number);
        }

        // Notify about advancement/elimination
        $this->notifyCupTieResult($game, $cupTie, $competition, $winnerId);

        // Conduct next round draw if ready
        $nextRound = $this->cupDrawService->getNextRoundNeedingDraw($game->id, $match->competition_id);
        if ($nextRound !== null) {
            $this->cupDrawService->conductDraw($game->id, $match->competition_id, $nextRound);
        }
    }

    private function notifyCupTieResult(Game $game, CupTie $cupTie, ?Competition $competition, string $winnerId): void
    {
        if (! $cupTie->involvesTeam($game->team_id)) {
            return;
        }

        $roundConfig = $cupTie->getRoundConfig();
        $roundName = $roundConfig->name ?? '';
        $competitionName = $competition->name ?? 'Cup';

        if ($winnerId === $game->team_id) {
            $this->notificationService->notifyCompetitionAdvancement(
                $game,
                $competition->id,
                $competitionName,
                __('cup.advanced_past_round', ['round' => $roundName]),
            );
        } else {
            $this->notificationService->notifyCompetitionElimination(
                $game,
                $competition->id,
                $competitionName,
                __('cup.eliminated_in_round', ['round' => $roundName]),
            );
        }
    }

    private function awardCupPrizeMoney(Game $game, ?Competition $competition, int $roundNumber): void
    {
        $prizeAmounts = [
            1 => 10_000_000,
            2 => 20_000_000,
            3 => 30_000_000,
            4 => 50_000_000,
            5 => 100_000_000,
            6 => 200_000_000,
        ];

        $amount = $prizeAmounts[$roundNumber] ?? $prizeAmounts[1];
        $competitionName = $competition->name ?? 'Cup';

        FinancialTransaction::recordIncome(
            gameId: $game->id,
            category: FinancialTransaction::CATEGORY_CUP_BONUS,
            amount: $amount,
            description: "{$competitionName} - Round {$roundNumber} advancement",
            transactionDate: $game->current_date->toDateString(),
        );
    }

    private function updateGoalkeeperStats(GameMatch $match): void
    {
        $homeLineupIds = $match->home_lineup ?? [];
        $awayLineupIds = $match->away_lineup ?? [];
        $allLineupIds = array_merge($homeLineupIds, $awayLineupIds);

        $goalkeepers = GamePlayer::whereIn('id', $allLineupIds)
            ->where('position', 'Goalkeeper')
            ->get();

        foreach ($goalkeepers as $gk) {
            if (in_array($gk->id, $homeLineupIds)) {
                $gk->goals_conceded += $match->away_score;
                if ($match->away_score === 0) {
                    $gk->clean_sheets++;
                }
            } elseif (in_array($gk->id, $awayLineupIds)) {
                $gk->goals_conceded += $match->home_score;
                if ($match->home_score === 0) {
                    $gk->clean_sheets++;
                }
            }
            $gk->save();
        }
    }
}
