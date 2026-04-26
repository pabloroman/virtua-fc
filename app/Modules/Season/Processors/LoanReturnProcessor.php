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
 * Priority: 3 (runs before pre-contract transfers at 5 and contract expiration)
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
            'parentTeamName' => $loan->parentTeam->name,
            'loanTeamId' => $loan->loan_team_id,
            'loanTeamName' => $loan->loanTeam->name,
        ])->toArray();

        // Create notifications for players returning to user's team
        foreach ($returnedLoans as $loan) {
            if ($loan->parent_team_id === $game->team_id) {
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
