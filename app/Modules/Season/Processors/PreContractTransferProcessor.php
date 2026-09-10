<?php

namespace App\Modules\Season\Processors;

use App\Modules\Season\Contracts\SeasonProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Transfer\Services\TransferService;
use App\Models\Game;

/**
 * Completes pre-contract transfers at end of season.
 * Players who agreed to leave on a free transfer move to their new team.
 * Runs after ContractExpirationProcessor (which must leave these players in
 * place — see TransferOffer::locksPlayer()) and before player development,
 * so the new club benefits from the off-season progression.
 */
class PreContractTransferProcessor implements SeasonProcessor
{
    public function __construct(
        private readonly TransferService $transferService,
    ) {}

    public function priority(): int
    {
        return 30;
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        // Process outgoing pre-contracts (AI clubs taking user's players)
        $outgoingTransfers = $this->transferService->completePreContractTransfers($game);

        $outgoingData = $outgoingTransfers->map(fn ($offer) => [
            'playerId' => $offer->game_player_id,
            'playerName' => $offer->gamePlayer->name,
            // The player has already moved, so read the club he left from the
            // offer. Offers agreed before selling_team_id was stamped on AI
            // pre-contracts fall back to the first team.
            'fromTeamId' => $offer->selling_team_id ?? $game->team_id,
            'toTeamId' => $offer->offering_team_id,
            'toTeamName' => $offer->offeringTeam->name,
        ])->toArray();

        // Process incoming pre-contracts (user signed players on free transfers)
        $incomingTransfers = $this->transferService->completeIncomingPreContracts($game);

        $incomingData = $incomingTransfers->map(fn ($offer) => [
            'playerId' => $offer->game_player_id,
            'playerName' => $offer->gamePlayer->name,
            'fromTeamId' => $offer->selling_team_id,
            'fromTeamName' => $offer->sellingTeam->name ?? 'Unknown',
            'toTeamId' => $game->team_id,
        ])->toArray();

        $allTransfers = array_merge($outgoingData, $incomingData);

        return $data->setMetadata('preContractTransfers', $allTransfers);
    }
}
