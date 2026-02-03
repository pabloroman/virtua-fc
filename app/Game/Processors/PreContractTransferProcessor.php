<?php

namespace App\Game\Processors;

use App\Game\Contracts\SeasonEndProcessor;
use App\Game\DTO\SeasonTransitionData;
use App\Game\Services\TransferService;
use App\Models\Game;

/**
 * Completes pre-contract transfers at end of season.
 * Players who agreed to leave on a free transfer move to their new team.
 * Priority: 5 (runs before player development so new team benefits from development)
 */
class PreContractTransferProcessor implements SeasonEndProcessor
{
    public function __construct(
        private readonly TransferService $transferService,
    ) {}

    public function priority(): int
    {
        return 5;
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        $completedTransfers = $this->transferService->completePreContractTransfers($game);

        // Store completed transfers in metadata for display
        $transfersData = $completedTransfers->map(fn ($offer) => [
            'playerId' => $offer->game_player_id,
            'playerName' => $offer->gamePlayer->name,
            'fromTeamId' => $game->team_id,
            'toTeamId' => $offer->offering_team_id,
            'toTeamName' => $offer->offeringTeam->name,
        ])->toArray();

        return $data->setMetadata('preContractTransfers', $transfersData);
    }
}
