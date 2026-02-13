<?php

namespace App\Http\Views;

use App\Game\Services\LoanService;
use App\Game\Services\ScoutingService;
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

        // Determine what to show based on query params
        $showForm = false;
        $selectedReport = null;

        if ($request->has('new')) {
            $showForm = true;
        } elseif ($request->has('report')) {
            $selectedReport = ScoutReport::where('game_id', $game->id)
                ->where('id', $request->query('report'))
                ->where('status', ScoutReport::STATUS_COMPLETED)
                ->first();
            if (!$selectedReport) {
                $showForm = true;
            }
        } elseif ($searchingReport) {
            // Active search in progress — show progress
        } elseif ($searchHistory->isNotEmpty()) {
            $selectedReport = $searchHistory->first();
        } else {
            $showForm = true;
        }

        // Load player data for the selected report
        $scoutedPlayers = collect();
        $playerDetails = [];
        $existingOffers = [];

        if ($selectedReport && $selectedReport->isCompleted()) {
            $scoutedPlayers = $selectedReport->players;

            foreach ($scoutedPlayers as $player) {
                $playerDetails[$player->id] = $this->scoutingService->getPlayerScoutingDetail($player, $game);

                $existingOffers[$player->id] = TransferOffer::where('game_id', $gameId)
                    ->where('game_player_id', $player->id)
                    ->where('direction', TransferOffer::DIRECTION_INCOMING)
                    ->whereIn('status', [TransferOffer::STATUS_PENDING, TransferOffer::STATUS_AGREED])
                    ->orderByDesc('game_date')
                    ->first();
            }
        }

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
            'selectedReport' => $selectedReport,
            'showForm' => $showForm,
            'searchHistory' => $searchHistory,
            'scoutedPlayers' => $scoutedPlayers,
            'playerDetails' => $playerDetails,
            'existingOffers' => $existingOffers,
            'teamCountry' => $game->team->country,
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
