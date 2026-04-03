<?php

namespace App\Modules\Season\Processors;

use App\Models\Game;
use App\Modules\Season\Contracts\SeasonProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Transfer\Services\TransferService;

/**
 * Completes agreed non-pre-contract transfers at end of season.
 * Transfers agreed outside the transfer window are deferred until the next
 * window opens; if the season ends first, this processor finalises them.
 * Priority: 35 (after PreContractTransferProcessor, before TransferMarketResetProcessor)
 */
class AgreedTransferCompletionProcessor implements SeasonProcessor
{
    public function __construct(
        private readonly TransferService $transferService,
    ) {}

    public function priority(): int
    {
        return 35;
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        $outgoing = $this->transferService->completeAgreedTransfers($game);
        $incoming = $this->transferService->completeIncomingTransfers($game);

        $outgoingData = $outgoing->map(fn ($offer) => [
            'playerId' => $offer->game_player_id,
            'playerName' => $offer->gamePlayer->name,
            'fromTeamId' => $game->team_id,
            'toTeamId' => $offer->offering_team_id,
            'toTeamName' => $offer->offeringTeam->name,
            'transferFee' => $offer->transfer_fee,
        ])->toArray();

        $incomingData = $incoming->map(fn ($offer) => [
            'playerId' => $offer->game_player_id,
            'playerName' => $offer->gamePlayer->name,
            'fromTeamId' => $offer->selling_team_id,
            'fromTeamName' => $offer->sellingTeam->name ?? 'Unknown',
            'toTeamId' => $game->team_id,
            'transferFee' => $offer->transfer_fee,
        ])->toArray();

        return $data->setMetadata('agreedTransfers', array_merge($outgoingData, $incomingData));
    }
}
