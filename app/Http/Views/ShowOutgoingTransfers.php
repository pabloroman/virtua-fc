<?php

namespace App\Http\Views;

use App\Modules\Transfer\Services\ContractService;
use App\Modules\Transfer\Services\DispositionService;
use App\Modules\Transfer\Services\LoanService;
use App\Modules\Transfer\Services\TransferHeaderService;
use App\Modules\Transfer\Services\TransferService;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\RenewalNegotiation;
use App\Models\TransferListing;
use App\Models\TransferOffer;

class ShowOutgoingTransfers
{
    public function __construct(
        private readonly TransferService $transferService,
        private readonly ContractService $contractService,
        private readonly LoanService $loanService,
        private readonly TransferHeaderService $headerService,
        private readonly DispositionService $dispositionService,
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
            ->whereHas('transferListing', fn ($q) => $q->where('status', TransferListing::STATUS_LISTED))
            ->whereNotIn('id', $agreedPlayerIds)
            ->orderByDesc('market_value_cents')
            ->get();

        // Recent completed outgoing transfers (last 10)
        $recentTransfers = TransferOffer::with(['gamePlayer.player', 'offeringTeam'])
            ->where('game_id', $gameId)
            ->where('status', TransferOffer::STATUS_COMPLETED)
            ->where('direction', '!=', TransferOffer::DIRECTION_INCOMING)
            ->orderByDesc('resolved_at')
            ->get();

        // Loan data
        $loans = $this->loanService->getActiveLoans($game);
        $loansOut = $loans['out'];

        $loanSearches = GamePlayer::with(['player'])
            ->where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->whereHas('transferListing', fn ($q) => $q->where('status', TransferListing::STATUS_LOAN_SEARCH))
            ->get();

        // Pending loan-out offers for the "Loan Offers Received" section.
        // Each offer is a separate card, same UX as sale offers.
        $loanOffers = TransferOffer::with(['offeringTeam', 'gamePlayer.player'])
            ->where('game_id', $gameId)
            ->where('direction', TransferOffer::DIRECTION_OUTGOING)
            ->where('offer_type', TransferOffer::TYPE_LOAN_OUT)
            ->where('status', TransferOffer::STATUS_PENDING)
            ->whereHas('gamePlayer', fn ($q) => $q->where('team_id', $game->team_id))
            ->where('expires_at', '>=', $game->current_date)
            ->orderByDesc('game_date')
            ->get()
            // days_until_expiry reads ->game->current_date; hydrate once for all rows.
            ->each(fn (TransferOffer $offer) => $offer->setRelation('game', $game));

        // Contract renewal data
        $renewalEligiblePlayers = $this->contractService->getPlayersEligibleForRenewal($game);
        $pendingRenewals = $this->contractService->getPlayersWithPendingRenewals($game);

        // Declined renewals (club-declined only — player rejections use cooldown)
        $seasonEndDate = $game->getSeasonEndDate();

        // Per-player flags (stature/wage gap) used to drive indicators and filtering.
        $playerFlags = $this->dispositionService->buildSquadFlags($game)->toArray();

        // Narrow the renewal list: final-contract-year, wage-gap (wants a raise),
        // or stature-gap (will not re-sign at any terms). Users can still renew a
        // long-contract player from the player detail page; the list itself stays
        // focused on actionable cases.
        $renewalEligiblePlayers = $renewalEligiblePlayers->filter(
            fn (GamePlayer $p) => $p->isContractExpiring($seasonEndDate)
                || ($playerFlags[$p->id]['stature_gap'] ?? false)
                || ($playerFlags[$p->id]['wage_gap'] ?? false)
        )->values();
        $declinedRenewals = GamePlayer::with(['player', 'latestRenewalNegotiation'])
            ->where('game_id', $gameId)
            ->ownedByTeam($game->team_id)
            ->whereHas('latestRenewalNegotiation', fn ($q) => $q->where('status', RenewalNegotiation::STATUS_CLUB_DECLINED))
            ->get()
            ->filter(fn (GamePlayer $p) => $p->isContractExpiring($seasonEndDate) && $p->hasDeclinedRenewal());

        // Players on renewal cooldown (player rejected, waiting for next matchday)
        $cooldownPlayerIds = RenewalNegotiation::where('game_id', $gameId)
            ->where('status', RenewalNegotiation::STATUS_PLAYER_REJECTED)
            ->where('rejected_at', '>=', $game->current_date)
            ->pluck('game_player_id')
            ->toArray();

        $cooldownRenewals = collect();
        if (!empty($cooldownPlayerIds)) {
            $cooldownRenewals = GamePlayer::with(['player'])
                ->where('game_id', $gameId)
                ->ownedByTeam($game->team_id)
                ->whereIn('id', $cooldownPlayerIds)
                ->get();
        }

        return view('outgoing-transfers', [
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
            'loanOffers' => $loanOffers,
            'renewalEligiblePlayers' => $renewalEligiblePlayers,
            'pendingRenewals' => $pendingRenewals,
            'declinedRenewals' => $declinedRenewals,
            'cooldownRenewals' => $cooldownRenewals,
            'playerFlags' => $playerFlags,
            ...$this->headerService->getHeaderData($game),
        ]);
    }
}
