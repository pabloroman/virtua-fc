<?php

namespace App\Http\Views;

use App\Modules\Transfer\Services\ContractService;
use App\Modules\Transfer\Services\LoanService;
use App\Modules\Transfer\Services\TransferService;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\RenewalNegotiation;
use App\Models\TransferOffer;
use App\Support\Money;

class ShowTransfers
{
    public function __construct(
        private readonly TransferService $transferService,
        private readonly ContractService $contractService,
        private readonly LoanService $loanService,
    ) {}

    public function __invoke(string $gameId)
    {
        $game = Game::with(['team', 'finances'])->findOrFail($gameId);
        abort_if($game->isTournamentMode(), 404);

        // Expire any old offers first
        $this->transferService->expireOffers($game);

        // Get all pending offers for user's players
        $pendingOffers = TransferOffer::with(['gamePlayer.player', 'gamePlayer.team', 'offeringTeam'])
            ->where('game_id', $gameId)
            ->where('status', TransferOffer::STATUS_PENDING)
            ->whereHas('gamePlayer', function ($query) use ($game) {
                $query->where('team_id', $game->team_id);
            })
            ->where('expires_at', '>=', $game->current_date)
            ->orderByDesc('transfer_fee')
            ->get();

        // Separate by offer type
        $unsolicitedOffers = $pendingOffers->where('offer_type', TransferOffer::TYPE_UNSOLICITED);
        $listedOffers = $pendingOffers->where('offer_type', TransferOffer::TYPE_LISTED);

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

        // Get agreed outgoing transfers (waiting for window) - exclude pre-contracts
        $agreedTransfers = TransferOffer::with(['gamePlayer.player', 'offeringTeam'])
            ->where('game_id', $gameId)
            ->where('status', TransferOffer::STATUS_AGREED)
            ->where('offer_type', '!=', TransferOffer::TYPE_PRE_CONTRACT)
            ->whereHas('gamePlayer', function ($query) use ($game) {
                $query->where('team_id', $game->team_id);
            })
            ->orderByDesc('transfer_fee')
            ->get();

        // Get listed players (even those without offers, excluding those with agreed deals)
        $agreedPlayerIds = $agreedTransfers->pluck('game_player_id')->toArray();
        $listedPlayers = GamePlayer::with(['player'])
            ->where('game_id', $gameId)
            ->where('team_id', $game->team_id)
            ->where('transfer_status', GamePlayer::TRANSFER_STATUS_LISTED)
            ->whereNotIn('id', $agreedPlayerIds)
            ->orderByDesc('market_value_cents')
            ->get();

        // Recent completed outgoing transfers (last 10)
        $recentTransfers = TransferOffer::with(['gamePlayer.player', 'offeringTeam'])
            ->where('game_id', $gameId)
            ->where('status', TransferOffer::STATUS_COMPLETED)
            ->where('direction', '!=', TransferOffer::DIRECTION_INCOMING)
            ->orderByDesc('resolved_at')
            ->limit(10)
            ->get();

        // Loan data
        $loans = $this->loanService->getActiveLoans($game);
        $loansOut = $loans['out'];

        $loanSearches = GamePlayer::with(['player'])
            ->where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->where('transfer_status', GamePlayer::TRANSFER_STATUS_LOAN_SEARCH)
            ->get();

        // Contract renewal data
        $renewalEligiblePlayers = $this->contractService->getPlayersEligibleForRenewal($game);
        $renewalDemands = [];
        $renewalMoods = [];
        foreach ($renewalEligiblePlayers as $player) {
            $demand = $this->contractService->calculateRenewalDemand($player);
            $renewalDemands[$player->id] = $demand;
            $disposition = $this->contractService->calculateDisposition($player);
            $renewalMoods[$player->id] = $this->contractService->getMoodIndicator($disposition);
        }
        $pendingRenewals = $this->contractService->getPlayersWithPendingRenewals($game);

        // Active negotiations (pending or countered)
        $activeNegotiations = $this->contractService->getActiveNegotiations($game);
        $negotiatingPlayers = $this->contractService->getPlayersInNegotiation($game);

        // Also compute moods for negotiating players
        foreach ($negotiatingPlayers as $player) {
            if (!isset($renewalMoods[$player->id])) {
                $negotiation = $activeNegotiations->get($player->id);
                $round = $negotiation ? $negotiation->round : 1;
                $disposition = $this->contractService->calculateDisposition($player, $round);
                $renewalMoods[$player->id] = $this->contractService->getMoodIndicator($disposition);
            }
        }

        // Pre-compute renewal offer midpoints (rounded up to nearest 10K)
        $renewalMidpoints = [];
        foreach ($negotiatingPlayers as $player) {
            $demand = $renewalDemands[$player->id] ?? null;
            $negotiation = $activeNegotiations->get($player->id);
            $renewalMidpoints[$player->id] = $demand
                ? (int) (ceil(($player->annual_wage + $demand['wage']) / 2 / 100 / 10000) * 10000)
                : (int) (ceil($negotiation->counter_offer / 100 / 10000) * 10000);
        }
        foreach ($renewalEligiblePlayers as $player) {
            $demand = $renewalDemands[$player->id] ?? null;
            $renewalMidpoints[$player->id] = $demand
                ? (int) (ceil(($player->annual_wage + $demand['wage']) / 2 / 100 / 10000) * 10000)
                : 0;
        }

        // Declined renewals
        $seasonEndDate = $game->getSeasonEndDate();
        $declinedRenewals = GamePlayer::with(['player', 'latestRenewalNegotiation'])
            ->where('game_id', $gameId)
            ->where('team_id', $game->team_id)
            ->whereHas('latestRenewalNegotiation', fn ($q) => $q->whereIn('status', [
                RenewalNegotiation::STATUS_PLAYER_REJECTED,
                RenewalNegotiation::STATUS_CLUB_DECLINED,
            ]))
            ->get()
            ->filter(fn (GamePlayer $p) => $p->isContractExpiring($seasonEndDate) && $p->hasDeclinedRenewal());

        // Transfer window info
        $isTransferWindow = $game->isTransferWindowOpen();
        $currentWindow = $game->getCurrentWindowName();
        $windowCountdown = $game->getWindowCountdown();

        // Wage bill
        $totalWageBill = GamePlayer::where('game_id', $gameId)
            ->where('team_id', $game->team_id)
            ->sum('annual_wage');

        // Badge count for Fichajes tab (counter-offers)
        $counterOfferCount = TransferOffer::where('game_id', $gameId)
            ->where('status', TransferOffer::STATUS_PENDING)
            ->where('direction', TransferOffer::DIRECTION_INCOMING)
            ->whereNotNull('asking_price')
            ->whereColumn('asking_price', '>', 'transfer_fee')
            ->count();

        return view('transfers', [
            'game' => $game,
            'unsolicitedOffers' => $unsolicitedOffers,
            'preContractOffers' => $preContractOffers,
            'listedOffers' => $listedOffers,
            'agreedTransfers' => $agreedTransfers,
            'agreedPreContracts' => $agreedPreContracts,
            'listedPlayers' => $listedPlayers,
            'recentTransfers' => $recentTransfers,
            'loansOut' => $loansOut,
            'loanSearches' => $loanSearches,
            'renewalEligiblePlayers' => $renewalEligiblePlayers,
            'renewalDemands' => $renewalDemands,
            'renewalMoods' => $renewalMoods,
            'pendingRenewals' => $pendingRenewals,
            'declinedRenewals' => $declinedRenewals,
            'activeNegotiations' => $activeNegotiations,
            'negotiatingPlayers' => $negotiatingPlayers,
            'renewalMidpoints' => $renewalMidpoints,
            'currentWindow' => $currentWindow,
            'isTransferWindow' => $isTransferWindow,
            'windowCountdown' => $windowCountdown,
            'totalWageBill' => $totalWageBill,
            'counterOfferCount' => $counterOfferCount,
        ]);
    }
}
