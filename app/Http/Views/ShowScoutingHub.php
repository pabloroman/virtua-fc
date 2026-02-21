<?php

namespace App\Http\Views;

use App\Modules\Transfer\Services\ScoutingService;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\ShortlistedPlayer;
use App\Models\TransferOffer;
use App\Support\PositionMapper;
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

        // Shortlisted players with scouting details
        $shortlistedEntries = ShortlistedPlayer::where('game_id', $gameId)
            ->with(['gamePlayer.player', 'gamePlayer.team'])
            ->get();

        $shortlistedPlayers = [];
        $shortlistedPlayerIds = [];
        foreach ($shortlistedEntries as $entry) {
            $gp = $entry->gamePlayer;
            if (!$gp || $gp->team_id === $game->team_id) {
                continue;
            }
            $detail = $this->scoutingService->getPlayerScoutingDetail($gp, $game);
            $shortlistedPlayers[] = [
                'gamePlayer' => $gp,
                'detail' => $detail,
                'added_at' => $entry->added_at,
            ];
            $shortlistedPlayerIds[] = $gp->id;
        }

        // Check existing offers for shortlisted players (map player_id => status details)
        $existingOfferStatuses = TransferOffer::getOfferStatusesForPlayers($gameId, $shortlistedPlayerIds);

        // Build JSON-serializable shortlist data for Alpine.js
        $shortlistData = [];
        foreach ($shortlistedPlayers as $entry) {
            $gp = $entry['gamePlayer'];
            $detail = $entry['detail'];
            $positionDisplay = PositionMapper::getPositionDisplay($gp->position);
            $shortlistData[] = [
                'id' => $gp->id,
                'name' => $gp->name,
                'position' => $gp->position,
                'positionAbbr' => $positionDisplay['abbreviation'],
                'positionBg' => $positionDisplay['bg'],
                'positionText' => $positionDisplay['text'],
                'age' => $gp->age,
                'teamName' => $gp->team?->name,
                'teamImage' => $gp->team?->image,
                'techRange' => $detail['tech_range'],
                'formattedAskingPrice' => $detail['formatted_asking_price'],
                'askingPrice' => $detail['asking_price'],
                'canAffordFee' => $detail['can_afford_fee'],
                'isExpiring' => $gp->contract_until && $gp->contract_until <= $game->getSeasonEndDate(),
                'wageDemand' => $detail['wage_demand'],
                'formattedWageDemand' => $detail['formatted_wage_demand'],
                'hasExistingOffer' => isset($existingOfferStatuses[$gp->id]),
                'offerStatus' => $existingOfferStatuses[$gp->id]['status'] ?? null,
                'offerIsCounter' => $existingOfferStatuses[$gp->id]['isCounter'] ?? false,
                'offerType' => $existingOfferStatuses[$gp->id]['offerType'] ?? null,
                'bidEuros' => (int) ($detail['asking_price'] / 100),
                'wageEuros' => (int) ($detail['wage_demand'] / 100),
            ];
        }

        $isPreContractPeriod = $game->isPreContractPeriod();

        return view('scouting-hub', [
            'game' => $game,
            'searchingReport' => $searchingReport,
            'searchHistory' => $searchHistory,
            'canSearchInternationally' => $canSearchInternationally,
            'isTransferWindow' => $isTransferWindow,
            'isPreContractPeriod' => $isPreContractPeriod,
            'currentWindow' => $currentWindow,
            'windowCountdown' => $windowCountdown,
            'totalWageBill' => $totalWageBill,
            'salidaBadgeCount' => $salidaBadgeCount,
            'counterOfferCount' => $counterOfferCount,
            'shortlistData' => $shortlistData,
        ]);
    }
}
