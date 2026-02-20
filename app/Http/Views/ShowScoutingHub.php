<?php

namespace App\Http\Views;

use App\Modules\Transfer\Services\ScoutingService;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\TransferOffer;
use Illuminate\Http\Request;

class ShowScoutingHub
{
    public function __construct(
        private readonly ScoutingService $scoutingService,
    ) {}

    public function __invoke(Request $request, string $gameId)
    {
        $game = Game::with(['team', 'finances'])->findOrFail($gameId);
        abort_if($game->isTournamentMode(), 404);

        $searchingReport = $this->scoutingService->getActiveReport($game);
        $searchHistory = $this->scoutingService->getSearchHistory($game);
        $canSearchInternationally = $this->scoutingService->canSearchInternationally($game);

        // Transfer window info (for shared header)
        $isTransferWindow = $game->isTransferWindowOpen();
        $currentWindow = $game->getCurrentWindowName();
        $windowCountdown = $game->getWindowCountdown();

        // Wage bill (for shared header)
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

        // Badge count for Fichajes tab (counter-offers)
        $counterOfferCount = TransferOffer::where('game_id', $gameId)
            ->where('status', TransferOffer::STATUS_PENDING)
            ->where('direction', TransferOffer::DIRECTION_INCOMING)
            ->whereNotNull('asking_price')
            ->whereColumn('asking_price', '>', 'transfer_fee')
            ->count();

        return view('scouting-hub', [
            'game' => $game,
            'searchingReport' => $searchingReport,
            'searchHistory' => $searchHistory,
            'canSearchInternationally' => $canSearchInternationally,
            'isTransferWindow' => $isTransferWindow,
            'currentWindow' => $currentWindow,
            'windowCountdown' => $windowCountdown,
            'totalWageBill' => $totalWageBill,
            'salidaBadgeCount' => $salidaBadgeCount,
            'counterOfferCount' => $counterOfferCount,
        ]);
    }
}
