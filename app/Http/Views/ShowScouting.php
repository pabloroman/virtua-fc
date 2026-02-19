<?php

namespace App\Http\Views;

use App\Modules\Transfer\Services\LoanService;
use App\Modules\Transfer\Services\ScoutingService;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\ScoutReport;
use App\Models\TransferOffer;
use App\Support\Money;
use Illuminate\Http\Request;

class ShowScouting
{
    public function __construct(
        private readonly ScoutingService $scoutingService,
        private readonly LoanService $loanService,
    ) {}

    public function __invoke(Request $request, string $gameId)
    {
        $game = Game::with(['team', 'finances'])->findOrFail($gameId);
        abort_if($game->isTournamentMode(), 404);

        $searchingReport = $this->scoutingService->getActiveReport($game);
        $searchHistory = $this->scoutingService->getSearchHistory($game);

        // Load player data for the selected report
        $scoutedPlayers = collect();
        $playerDetails = [];
        $existingOffers = [];

        // Incoming transfer data (moved from ShowTransfers)
        $pendingBids = TransferOffer::with(['gamePlayer.player', 'gamePlayer.team', 'sellingTeam'])
            ->where('game_id', $gameId)
            ->where('status', TransferOffer::STATUS_PENDING)
            ->where('direction', TransferOffer::DIRECTION_INCOMING)
            ->orderByDesc('game_date')
            ->get();

        // Separate counter-offers from regular pending bids
        $counterOffers = $pendingBids->filter(function ($bid) {
            return $bid->asking_price && $bid->asking_price > $bid->transfer_fee;
        });
        $regularPendingBids = $pendingBids->reject(function ($bid) {
            return $bid->asking_price && $bid->asking_price > $bid->transfer_fee;
        });

        $rejectedBids = TransferOffer::with(['gamePlayer.player', 'gamePlayer.team', 'sellingTeam'])
            ->where('game_id', $gameId)
            ->where('status', TransferOffer::STATUS_REJECTED)
            ->where('direction', TransferOffer::DIRECTION_INCOMING)
            ->where('resolved_at', '>=', $game->current_date->subDays(7))
            ->orderByDesc('resolved_at')
            ->get();

        $incomingAgreedTransfers = TransferOffer::with(['gamePlayer.player', 'gamePlayer.team', 'sellingTeam'])
            ->where('game_id', $gameId)
            ->where('status', TransferOffer::STATUS_AGREED)
            ->where('direction', TransferOffer::DIRECTION_INCOMING)
            ->orderByDesc('game_date')
            ->get();

        // Loans in
        $loans = $this->loanService->getActiveLoans($game);
        $loansIn = $loans['in'];

        // Transfer window info
        $isTransferWindow = $game->isTransferWindowOpen();
        $currentWindow = $game->getCurrentWindowName();
        $isPreContractPeriod = $game->isPreContractPeriod();
        $seasonEndDate = $game->getSeasonEndDate();
        $canSearchInternationally = $this->scoutingService->canSearchInternationally($game);
        $windowCountdown = $game->getWindowCountdown();

        // Wage bill
        $totalWageBill = GamePlayer::where('game_id', $gameId)
            ->where('team_id', $game->team_id)
            ->sum('annual_wage');

        // Badge count for Salidas tab
        $salidaBadgeCount = TransferOffer::where('game_id', $gameId)
            ->where('status', TransferOffer::STATUS_PENDING)
            ->whereHas('gamePlayer', function ($query) use ($game) {
                $query->where('team_id', $game->team_id);
            })
            ->where('expires_at', '>=', $game->current_date)
            ->whereIn('offer_type', [
                TransferOffer::TYPE_UNSOLICITED,
                TransferOffer::TYPE_LISTED,
                TransferOffer::TYPE_PRE_CONTRACT,
            ])
            ->count();

        return view('scouting', [
            'game' => $game,
            'searchingReport' => $searchingReport,
            'searchHistory' => $searchHistory,
            'scoutedPlayers' => $scoutedPlayers,
            'playerDetails' => $playerDetails,
            'existingOffers' => $existingOffers,
            'teamCountry' => $game->country,
            'isTransferWindow' => $isTransferWindow,
            'currentWindow' => $currentWindow,
            'isPreContractPeriod' => $isPreContractPeriod,
            'seasonEndDate' => $seasonEndDate,
            'canSearchInternationally' => $canSearchInternationally,
            'counterOffers' => $counterOffers,
            'pendingBids' => $regularPendingBids,
            'rejectedBids' => $rejectedBids,
            'incomingAgreedTransfers' => $incomingAgreedTransfers,
            'loansIn' => $loansIn,
            'windowCountdown' => $windowCountdown,
            'totalWageBill' => $totalWageBill,
            'salidaBadgeCount' => $salidaBadgeCount,
        ]);
    }
}
