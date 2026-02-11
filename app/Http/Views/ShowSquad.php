<?php

namespace App\Http\Views;

use App\Game\Services\ContractService;
use App\Game\Services\LineupService;
use App\Models\Game;
use App\Models\TransferOffer;

class ShowSquad
{
    public function __construct(
        private readonly ContractService $contractService,
        private readonly LineupService $lineupService,
    ) {}

    public function __invoke(string $gameId)
    {
        $game = Game::with('team')->findOrFail($gameId);

        // Get all players for the user's team, grouped by position
        $players = $this->lineupService->getPlayersByPositionGroup($gameId, $game->team_id);

        // Contract renewal data
        $renewalEligiblePlayers = $this->contractService->getPlayersEligibleForRenewal($game);
        $renewalDemands = [];
        foreach ($renewalEligiblePlayers as $player) {
            $renewalDemands[$player->id] = $this->contractService->calculateRenewalDemand($player);
        }

        $pendingRenewals = $this->contractService->getPlayersWithPendingRenewals($game);

        // Pre-contract offers (players being poached)
        $preContractOffers = TransferOffer::with(['gamePlayer.player', 'offeringTeam'])
            ->where('game_id', $gameId)
            ->where('status', TransferOffer::STATUS_PENDING)
            ->where('offer_type', TransferOffer::TYPE_PRE_CONTRACT)
            ->whereHas('gamePlayer', function ($query) use ($game) {
                $query->where('team_id', $game->team_id);
            })
            ->where('expires_at', '>=', $game->current_date)
            ->orderByDesc('game_date')
            ->get();

        // Agreed pre-contracts (players leaving at end of season)
        $agreedPreContracts = TransferOffer::with(['gamePlayer.player', 'offeringTeam'])
            ->where('game_id', $gameId)
            ->where('status', TransferOffer::STATUS_AGREED)
            ->where('offer_type', TransferOffer::TYPE_PRE_CONTRACT)
            ->whereHas('gamePlayer', function ($query) use ($game) {
                $query->where('team_id', $game->team_id);
            })
            ->get();

        return view('squad', [
            'game' => $game,
            'goalkeepers' => $players['goalkeepers'],
            'defenders' => $players['defenders'],
            'midfielders' => $players['midfielders'],
            'forwards' => $players['forwards'],
            'renewalEligiblePlayers' => $renewalEligiblePlayers,
            'renewalDemands' => $renewalDemands,
            'pendingRenewals' => $pendingRenewals,
            'preContractOffers' => $preContractOffers,
            'agreedPreContracts' => $agreedPreContracts,
            'isTransferWindow' => $game->isTransferWindowOpen(),
        ]);
    }
}
