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
        // Process outgoing pre-contracts (AI clubs taking user's players)
        $outgoingTransfers = $this->transferService->completePreContractTransfers($game);

        $outgoingData = $outgoingTransfers->map(fn ($offer) => [
            'playerId' => $offer->game_player_id,
            'playerName' => $offer->gamePlayer->name,
            'fromTeamId' => $game->team_id,
            'toTeamId' => $offer->offering_team_id,
            'toTeamName' => $offer->offeringTeam->name,
        ])->toArray();

        // Process incoming pre-contracts (user signed players on free transfers)
        $incomingTransfers = $this->transferService->completeIncomingPreContracts($game);

        $incomingData = $incomingTransfers->map(fn ($offer) => [
            'playerId' => $offer->game_player_id,
            'playerName' => $offer->gamePlayer->name,
            'fromTeamId' => $offer->selling_team_id,
            'fromTeamName' => $offer->sellingTeam?->name ?? 'Unknown',
            'toTeamId' => $game->team_id,
        ])->toArray();

        $allTransfers = array_merge($outgoingData, $incomingData);

        return $data->setMetadata('preContractTransfers', $allTransfers);
    }
}
