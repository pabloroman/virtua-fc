<?php

namespace App\Http\Views;

use App\Game\Services\ContractService;
use App\Game\Services\FinancialService;
use App\Game\Services\TransferService;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\TransferOffer;

class ShowContracts
{
    public function __construct(
        private readonly ContractService $contractService,
        private readonly FinancialService $financialService,
        private readonly TransferService $transferService,
    ) {}

    public function __invoke(string $gameId)
    {
        $game = Game::with('team')->findOrFail($gameId);

        // Contract renewal data
        $renewalEligiblePlayers = $this->contractService->getPlayersEligibleForRenewal($game);
        $renewalDemands = [];
        foreach ($renewalEligiblePlayers as $player) {
            $renewalDemands[$player->id] = $this->contractService->calculateRenewalDemand($player);
        }

        // Pending renewals (wage increases at end of season)
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
            ->orderByDesc('created_at')
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

        // Contracts by expiry year for overview
        $contractsByYear = $this->contractService->getContractsByExpiryYear($game);

        // Highest earners
        $highestEarners = $this->contractService->getHighestEarners($game, 5);

        // Most valuable players
        $mostValuable = GamePlayer::with('player')
            ->where('game_id', $gameId)
            ->where('team_id', $game->team_id)
            ->orderByDesc('market_value_cents')
            ->limit(5)
            ->get();

        // Squad totals
        $squadValue = $this->financialService->calculateSquadValue($game);
        $wageBill = $this->financialService->calculateAnnualWageBill($game);

        return view('contracts', [
            'game' => $game,
            'renewalEligiblePlayers' => $renewalEligiblePlayers,
            'renewalDemands' => $renewalDemands,
            'pendingRenewals' => $pendingRenewals,
            'preContractOffers' => $preContractOffers,
            'agreedPreContracts' => $agreedPreContracts,
            'contractsByYear' => $contractsByYear,
            'highestEarners' => $highestEarners,
            'mostValuable' => $mostValuable,
            'squadValue' => $squadValue,
            'wageBill' => $wageBill,
        ]);
    }
}
