<?php

namespace App\Game\Processors;

use App\Game\Contracts\SeasonEndProcessor;
use App\Game\DTO\SeasonTransitionData;
use App\Game\Services\LoanService;
use App\Models\Game;

/**
 * Returns all loaned players to their parent teams at end of season.
 * Priority: 3 (runs before pre-contract transfers at 5 and contract expiration)
 */
class LoanReturnProcessor implements SeasonEndProcessor
{
    public function __construct(
        private readonly LoanService $loanService,
    ) {}

    public function priority(): int
    {
        return 3;
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        $returnedLoans = $this->loanService->returnAllLoans($game);

        $loanReturns = $returnedLoans->map(fn ($loan) => [
            'playerId' => $loan->game_player_id,
            'playerName' => $loan->gamePlayer->name,
            'parentTeamId' => $loan->parent_team_id,
            'parentTeamName' => $loan->parentTeam->name,
            'loanTeamId' => $loan->loan_team_id,
            'loanTeamName' => $loan->loanTeam->name,
        ])->toArray();

        return $data->setMetadata('loanReturns', $loanReturns);
    }
}
