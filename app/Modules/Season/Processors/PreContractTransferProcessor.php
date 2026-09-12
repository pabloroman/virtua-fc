<?php

namespace App\Modules\Season\Processors;

use App\Modules\Season\Contracts\SeasonProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Transfer\Services\TransferService;
use App\Models\Game;
use App\Models\TransferOffer;

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

        // Process incoming pre-contracts (user signed players on free transfers).
        // completeIncomingTransfer re-asserts the player is still at the club
        // that agreed to sell him and rejects the deal when he is not, so the
        // returned offers are a mix of completed and rejected — split on the
        // status it left behind rather than reporting every attempt to the
        // season summary as a signing the user actually got.
        $incomingTransfers = $this->transferService->completeIncomingPreContracts($game);

        [$incomingCompleted, $incomingFailed] = $incomingTransfers->partition(
            fn (TransferOffer $offer) => $offer->status === TransferOffer::STATUS_COMPLETED,
        );

        $incomingData = $incomingCompleted->map(fn ($offer) => [
            'playerId' => $offer->game_player_id,
            'playerName' => $offer->gamePlayer->name,
            'fromTeamId' => $offer->selling_team_id,
            'fromTeamName' => $offer->sellingTeam->name ?? 'Unknown',
            'toTeamId' => $game->team_id,
        ])->values()->toArray();

        $allTransfers = array_merge($outgoingData, $incomingData);

        // Failures are published separately and, unlike the success list, are
        // NOT stripped from the archived transition_log (see
        // ProcessSeasonTransition). A signing that never arrived leaves no
        // GameTransfer row and no offer once the market resets at priority 70,
        // so without this there is nothing left to diagnose it with.
        return $data
            ->setMetadata('preContractTransfers', $allTransfers)
            ->setMetadata('preContractTransfersFailed', $incomingFailed->map(fn ($offer) => [
                'playerId' => $offer->game_player_id,
                'playerName' => $offer->gamePlayer->name,
                'expectedSellerId' => $offer->selling_team_id,
                'playerTeamId' => $offer->gamePlayer->team_id,
                'status' => $offer->status,
            ])->values()->toArray());
    }
}
