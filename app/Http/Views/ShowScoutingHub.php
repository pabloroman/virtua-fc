<?php

namespace App\Http\Views;

use App\Modules\Transfer\Services\ScoutingService;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\ShortlistedPlayer;
use App\Models\TransferOffer;
use App\Support\Money;
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

        // Tracking capacity
        $trackingCapacity = $this->scoutingService->getTrackingCapacity($game);

        // Shortlisted players with intel-gated data
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
            $shortlistedPlayers[] = [
                'entry' => $entry,
                'gamePlayer' => $gp,
            ];
            $shortlistedPlayerIds[] = $gp->id;
        }

        // Check existing offers for shortlisted players (map player_id => status details)
        $existingOfferStatuses = TransferOffer::getOfferStatusesForPlayers($gameId, $shortlistedPlayerIds);

        // Pre-load team rosters for all shortlisted players' teams (avoids N+1 in calculatePlayerImportance)
        $teamIds = collect($shortlistedPlayers)->pluck('gamePlayer.team_id')->filter()->unique();
        $teamRosters = GamePlayer::where('game_id', $gameId)
            ->whereIn('team_id', $teamIds)
            ->get()
            ->groupBy('team_id');

        // Build JSON-serializable shortlist data for Alpine.js
        $shortlistData = [];
        foreach ($shortlistedPlayers as $item) {
            $gp = $item['gamePlayer'];
            $entry = $item['entry'];
            $positionDisplay = PositionMapper::getPositionDisplay($gp->position);

            $playerData = [
                'id' => $gp->id,
                'name' => $gp->name,
                'position' => $gp->position,
                'positionAbbr' => $positionDisplay['abbreviation'],
                'positionBg' => $positionDisplay['bg'],
                'positionText' => $positionDisplay['text'],
                'age' => $gp->age,
                'teamName' => $gp->team?->name,
                'teamImage' => $gp->team?->image,
                'isExpiring' => $gp->contract_until && $gp->contract_until <= $game->getSeasonEndDate(),
                'contractYear' => $gp->contract_until?->format('Y'),
                'marketValue' => $gp->market_value_cents,
                'formattedMarketValue' => Money::format($gp->market_value_cents),
                'intelLevel' => $entry->intel_level,
                'isTracking' => $entry->is_tracking,
                'matchdaysTracked' => $entry->matchdays_tracked,
                'hasExistingOffer' => isset($existingOfferStatuses[$gp->id]),
                'offerStatus' => $existingOfferStatuses[$gp->id]['status'] ?? null,
                'offerIsCounter' => $existingOfferStatuses[$gp->id]['isCounter'] ?? false,
                'offerType' => $existingOfferStatuses[$gp->id]['offerType'] ?? null,
                // Locked by default — populated below if intel level warrants it
                'techRange' => null,
                'formattedAskingPrice' => null,
                'askingPrice' => null,
                'canAffordFee' => false,
                'wageDemand' => null,
                'formattedWageDemand' => null,
                'bidEuros' => 0,
                'wageEuros' => 0,
                'willingness' => null,
                'willingnessLabel' => null,
                'rivalInterest' => false,
            ];

            // Level 1+: unlock full scouting detail
            if ($entry->hasReportLevel()) {
                $detail = $this->scoutingService->getPlayerScoutingDetail($gp, $game);
                $playerData['techRange'] = $detail['tech_range'];
                $playerData['formattedAskingPrice'] = $detail['formatted_asking_price'];
                $playerData['askingPrice'] = $detail['asking_price'];
                $playerData['canAffordFee'] = $detail['can_afford_fee'];
                $playerData['wageDemand'] = $detail['wage_demand'];
                $playerData['formattedWageDemand'] = $detail['formatted_wage_demand'];
                $playerData['bidEuros'] = (int) ($detail['asking_price'] / 100);
                $playerData['wageEuros'] = (int) ($detail['wage_demand'] / 100);
            }

            // Level 2: unlock willingness and rival interest
            if ($entry->hasDeepIntel()) {
                $teammates = $teamRosters->get($gp->team_id, collect());
                $importance = $this->scoutingService->calculatePlayerImportance($gp, $teammates);
                $willingness = $this->scoutingService->calculateWillingness($gp, $game, $importance);
                $playerData['willingness'] = $willingness['label'];
                $playerData['willingnessLabel'] = __('transfers.willingness_' . $willingness['label']);
                $playerData['rivalInterest'] = $this->scoutingService->calculateRivalInterest($gp, $importance);
            }

            $shortlistData[] = $playerData;
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
            'trackingCapacity' => $trackingCapacity,
        ]);
    }
}
