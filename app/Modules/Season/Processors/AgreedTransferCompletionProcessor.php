<?php

namespace App\Modules\Season\Processors;

use App\Modules\Season\Contracts\SeasonProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Transfer\Services\TransferService;
use App\Models\Game;

/**
 * Completes regular transfers (non-pre-contract) that were agreed outside
 * the transfer window. Without this, they would be deleted by
 * TransferMarketResetProcessor without ever being executed.
 *
 * Priority: 35 (runs after PreContractTransferProcessor but before TransferMarketResetProcessor)
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
        // Complete outgoing agreed transfers (AI clubs buying user's players)
        $completedOutgoing = $this->transferService->completeAgreedTransfers($game);

        // Complete incoming agreed transfers (user buying/loaning players from AI)
        $completedIncoming = $this->transferService->completeIncomingTransfers($game);

        $transfers = $completedOutgoing->merge($completedIncoming)->map(fn ($offer) => [
            'playerId' => $offer->game_player_id,
            'playerName' => $offer->gamePlayer->name,
            'direction' => $offer->direction,
            'fee' => $offer->transfer_fee,
        ])->toArray();

        return $data->setMetadata('agreedTransfersCompleted', $transfers);
    }
}
