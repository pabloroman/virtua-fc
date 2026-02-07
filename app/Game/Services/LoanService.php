<?php

namespace App\Game\Services;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Loan;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class LoanService
{
    /**
     * Process a loan-in: player joins user's team on loan.
     */
    public function processLoanIn(Game $game, GamePlayer $player): Loan
    {
        $parentTeamId = $player->team_id;
        $returnDate = $game->getSeasonEndDate();

        $loan = Loan::create([
            'game_id' => $game->id,
            'game_player_id' => $player->id,
            'parent_team_id' => $parentTeamId,
            'loan_team_id' => $game->team_id,
            'started_at' => $game->current_date,
            'return_at' => $returnDate,
            'status' => Loan::STATUS_ACTIVE,
        ]);

        // Move player to user's team
        $player->update([
            'team_id' => $game->team_id,
            'joined_on' => $game->current_date,
        ]);

        return $loan;
    }

    /**
     * Process a loan-out: user's player goes to AI team.
     */
    public function processLoanOut(Game $game, GamePlayer $player, Team $destinationTeam): Loan
    {
        $returnDate = $game->getSeasonEndDate();

        $loan = Loan::create([
            'game_id' => $game->id,
            'game_player_id' => $player->id,
            'parent_team_id' => $game->team_id,
            'loan_team_id' => $destinationTeam->id,
            'started_at' => $game->current_date,
            'return_at' => $returnDate,
            'status' => Loan::STATUS_ACTIVE,
        ]);

        // Move player to AI team
        $player->update([
            'team_id' => $destinationTeam->id,
            'transfer_status' => null,
            'transfer_listed_at' => null,
        ]);

        return $loan;
    }

    /**
     * Complete all active loans (return players to parent teams).
     * Called at season end.
     */
    public function returnAllLoans(Game $game): Collection
    {
        $activeLoans = Loan::with(['gamePlayer.player', 'parentTeam', 'loanTeam'])
            ->where('game_id', $game->id)
            ->where('status', Loan::STATUS_ACTIVE)
            ->get();

        foreach ($activeLoans as $loan) {
            $this->returnLoan($loan);
        }

        return $activeLoans;
    }

    /**
     * Return a single loan - player goes back to parent team.
     */
    private function returnLoan(Loan $loan): void
    {
        $loan->gamePlayer->update([
            'team_id' => $loan->parent_team_id,
        ]);

        $loan->update([
            'status' => Loan::STATUS_COMPLETED,
        ]);
    }

    /**
     * Get active loans for a game (both in and out).
     */
    public function getActiveLoans(Game $game): array
    {
        $allLoans = Loan::with(['gamePlayer.player', 'parentTeam', 'loanTeam'])
            ->where('game_id', $game->id)
            ->where('status', Loan::STATUS_ACTIVE)
            ->get();

        $loansIn = $allLoans->filter(fn ($loan) => $loan->loan_team_id === $game->team_id);
        $loansOut = $allLoans->filter(fn ($loan) => $loan->parent_team_id === $game->team_id);

        return [
            'in' => $loansIn,
            'out' => $loansOut,
        ];
    }

    /**
     * Find an eligible AI team to loan a player out to.
     */
    public function findLoanDestination(Game $game, GamePlayer $player): ?Team
    {
        // Find teams in the same league(s) that need players at this position
        $teamIds = Team::whereHas('competitions', function ($q) {
            $q->where('type', 'league');
        })
            ->where('id', '!=', $game->team_id)
            ->pluck('id');

        // Pick a random eligible team
        if ($teamIds->isEmpty()) {
            return null;
        }

        return Team::find($teamIds->random());
    }
}
