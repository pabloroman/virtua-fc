<?php

namespace App\Modules\Season\Processors;

use App\Modules\ReserveTeam\Services\ReserveTeamService;
use App\Modules\Season\Contracts\SeasonProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Transfer\Services\LoanService;
use App\Modules\Notification\Services\NotificationService;
use App\Models\Game;

/**
 * Returns all loaned players to their parent teams at end of season.
 * Runs before contract expiration and before the pre-contract / agreed
 * transfer completions, so every player is back at his owner when those
 * look him up by team.
 */
class LoanReturnProcessor implements SeasonProcessor
{
    public function __construct(
        private readonly LoanService $loanService,
        private readonly NotificationService $notificationService,
        private readonly ReserveTeamService $reserveTeamService,
    ) {}

    public function priority(): int
    {
        return 5;
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        // Reserve players still called up to the first team at season close
        // are kept up permanently — closes their call-up loans before the
        // generic return sweep runs, so they aren't sent back to the filial.
        $promotedFromReserve = $this->reserveTeamService->permanentlyPromoteCalledUpPlayers($game);

        if ($promotedFromReserve->isNotEmpty()) {
            $data->setMetadata('reserve_called_up_promoted', $promotedFromReserve->count());
        }

        $returnedLoans = $this->loanService->returnAllLoans($game);

        $loanReturns = $returnedLoans->map(fn ($loan) => [
            'playerId' => $loan->game_player_id,
            'playerName' => $loan->gamePlayer->name,
            'parentTeamId' => $loan->parent_team_id,
            // Null for loans whose owning club isn't in the game — the player
            // is freed rather than returned, so there is no parent team name.
            'parentTeamName' => $loan->parentTeam?->name,
            'loanTeamId' => $loan->loan_team_id,
            'loanTeamName' => $loan->loanTeam->name,
        ])->toArray();

        // Create notifications for players returning to the user's club —
        // either of his teams, since a filial's reserve lends players out too.
        foreach ($returnedLoans as $loan) {
            if (in_array($loan->parent_team_id, $game->userTeamIds(), true)) {
                $this->notificationService->notifyLoanReturn(
                    $game,
                    $loan->gamePlayer,
                    $loan->loanTeam
                );
            }
        }

        return $data->setMetadata('loanReturns', $loanReturns);
    }
}
