<?php

namespace App\Modules\Season\Processors;

use App\Models\Game;
use App\Models\TransferOffer;
use App\Modules\Season\Contracts\SeasonProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Transfer\Services\TransferService;

/**
 * Completes agreed non-pre-contract transfers at end of season.
 * Transfers agreed outside the transfer window are deferred until the next
 * window opens; if the season ends first, this processor finalises them.
 * Runs after PreContractTransferProcessor and before TransferMarketResetProcessor.
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
            // The player has already moved; the offer names the club he left
            // (first team or reserve). Older offers without it were first-team.
            'fromTeamId' => $offer->selling_team_id ?? $game->team_id,
            'toTeamId' => $offer->offering_team_id,
            'toTeamName' => $offer->offeringTeam->name,
            'transferFee' => $offer->transfer_fee,
        ])->toArray();

        // As in PreContractTransferProcessor: completion re-asserts ownership
        // and rejects the deal when the seller no longer holds the player, so
        // split on the status left behind instead of reporting a rejected deal
        // to the season summary as a completed signing.
        [$incomingCompleted, $incomingFailed] = $incoming->partition(
            fn (TransferOffer $offer) => $offer->status === TransferOffer::STATUS_COMPLETED,
        );

        $incomingData = $incomingCompleted->map(fn ($offer) => [
            'playerId' => $offer->game_player_id,
            'playerName' => $offer->gamePlayer->name,
            'fromTeamId' => $offer->selling_team_id,
            'fromTeamName' => $offer->sellingTeam->name ?? 'Unknown',
            'toTeamId' => $game->team_id,
            'transferFee' => $offer->transfer_fee,
        ])->values()->toArray();

        return $data
            ->setMetadata('agreedTransfers', array_merge($outgoingData, $incomingData))
            ->setMetadata('agreedTransfersFailed', $incomingFailed->map(fn ($offer) => [
                'playerId' => $offer->game_player_id,
                'playerName' => $offer->gamePlayer->name,
                'expectedSellerId' => $offer->selling_team_id,
                'playerTeamId' => $offer->gamePlayer->team_id,
                'offerType' => $offer->offer_type,
                'status' => $offer->status,
            ])->values()->toArray());
    }
}
