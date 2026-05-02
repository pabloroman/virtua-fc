<?php

namespace App\Modules\Match\Services;

use App\Models\Game;
use App\Models\GameNotification;
use App\Models\GamePlayer;
use App\Models\RenewalNegotiation;
use App\Models\TransferOffer;
use App\Modules\Academy\Services\YouthAcademyService;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Transfer\Enums\TransferWindowType;
use App\Modules\Transfer\Services\AITransferMarketService;
use App\Modules\Transfer\Services\LoanService;
use App\Modules\Transfer\Services\ScoutingService;
use App\Modules\Transfer\Services\TransferMarketService;
use App\Modules\Transfer\Services\TransferService;
use App\Support\QueryProfiler;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CareerActionProcessor
{
    private const LISTED_OFFER_CHANCE_IN_WINDOW = 40;

    private const LISTED_OFFER_CHANCE_OUTSIDE_WINDOW = 15;

    public function __construct(
        private readonly TransferService $transferService,
        private readonly ScoutingService $scoutingService,
        private readonly LoanService $loanService,
        private readonly YouthAcademyService $youthAcademyService,
        private readonly NotificationService $notificationService,
        private readonly AITransferMarketService $aiTransferMarketService,
        private readonly TransferMarketService $transferMarketService,
    ) {}

    public function process(Game $game): void
    {
        $profile = QueryProfiler::enabled();
        $sections = [];
        $sectionStart = microtime(true);
        $sectionQueryCount = $profile ? count(DB::getQueryLog()) : 0;

        $mark = function (string $name) use (&$sections, &$sectionStart, &$sectionQueryCount, $profile): void {
            $now = microtime(true);
            $queries = $profile ? count(DB::getQueryLog()) - $sectionQueryCount : 0;
            $sections[$name] = [
                'ms' => (int) round(($now - $sectionStart) * 1000),
                'q' => $queries,
            ];
            $sectionStart = $now;
            $sectionQueryCount = $profile ? count(DB::getQueryLog()) : 0;
        };

        // Pre-load buyer pool once for all offer generation (avoids repeated team/squad queries)
        $buyerPool = $this->transferService->loadBuyerPool($game);
        $mark('buyer_pool');

        // Process transfers when window is open
        if ($game->isTransferWindowOpen()) {
            $completedOutgoing = $this->transferService->completeAgreedTransfers($game);
            $completedIncoming = $this->transferService->completeIncomingTransfers($game);

            foreach ($completedOutgoing->merge($completedIncoming) as $offer) {
                $this->notificationService->notifyTransferComplete($game, $offer);
            }
        }
        $mark('complete_transfers');

        // Generate offers for listed players (always, with reduced chance outside window)
        $listedOfferChance = $game->isTransferWindowOpen()
            ? self::LISTED_OFFER_CHANCE_IN_WINDOW
            : self::LISTED_OFFER_CHANCE_OUTSIDE_WINDOW;
        $listedOffers = $this->transferService->generateOffersForListedPlayers($game, buyerPool: $buyerPool, offerChance: $listedOfferChance);
        foreach ($listedOffers as $offer) {
            $this->notificationService->notifyTransferOffer($game, $offer);
        }
        $mark('listed_offers');

        // Unsolicited offers only during open windows
        if ($game->isTransferWindowOpen()) {
            $unsolicitedOffers = $this->transferService->generateUnsolicitedOffers($game, buyerPool: $buyerPool);
            foreach ($unsolicitedOffers as $offer) {
                $this->notificationService->notifyTransferOffer($game, $offer);
            }
        }
        $mark('unsolicited_offers');

        // Pre-contract offers (January onwards for expiring contracts)
        $preContractOffers = $this->transferService->generatePreContractOffers($game, buyerPool: $buyerPool);
        foreach ($preContractOffers as $offer) {
            $this->notificationService->notifyTransferOffer($game, $offer);
        }
        $mark('pre_contract_offers');

        // Resolve pending incoming pre-contract offers (after response delay)
        $resolvedPreContracts = $this->transferService->resolveIncomingPreContractOffers($game, $this->scoutingService);
        foreach ($resolvedPreContracts as $result) {
            $this->notificationService->notifyPreContractResult($game, $result['offer']);
        }
        $mark('resolve_pre_contracts');

        // Resolve pending incoming loan requests (deferred from user submission)
        $resolvedLoans = $this->loanService->resolveIncomingLoanRequests($game, $this->scoutingService);
        foreach ($resolvedLoans as $result) {
            $this->notificationService->notifyLoanRequestResult($game, $result['offer'], $result['result']);
        }
        $mark('resolve_loans');

        // Tick scout search progress
        $scoutReport = $this->scoutingService->tickSearch($game);
        if ($scoutReport?->isCompleted()) {
            $this->notificationService->notifyScoutComplete($game, $scoutReport);
        }
        $mark('scout_search');

        // Tick player tracking progress
        $leveledUpEntries = $this->scoutingService->tickTracking($game);
        foreach ($leveledUpEntries as $entry) {
            $this->notificationService->notifyTrackingIntelReady($game, $entry);
        }
        $mark('player_tracking');

        // Process loan searches
        $loanResults = $this->loanService->processLoanSearches($game);
        foreach ($loanResults['found'] as $result) {
            $this->notificationService->notifyLoanOfferReceived(
                $game,
                $result['player'],
                $result['destination'],
            );
        }
        foreach ($loanResults['expired'] as $result) {
            $this->notificationService->notifyLoanSearchFailed($game, $result['player']);
        }
        $mark('loan_searches');

        // Check for expiring transfer offers (2 days or less)
        $this->checkExpiringOffers($game);
        $mark('expiring_offers');

        // Warn about expiring contracts (6 months and 3 months before expiry)
        $this->checkExpiringContracts($game);
        $mark('expiring_contracts');

        // Develop academy players each matchday
        $this->youthAcademyService->developPlayers($game);
        $mark('develop_academy');

        // AI transfer market: process batch during open window
        $this->processAITransferBatch($game);
        $mark('ai_transfer_batch');

        if ($profile) {
            Log::info("[CareerActionProcessor {$game->id}] section breakdown", [
                'window_open' => $game->isTransferWindowOpen(),
                'sections' => $sections,
            ]);
        }
    }

    private function checkExpiringOffers(Game $game): void
    {
        $currentDate = $game->current_date;
        $expiringOffers = TransferOffer::with(['gamePlayer.player', 'offeringTeam'])
            ->where('game_id', $game->id)
            ->where('status', TransferOffer::STATUS_PENDING)
            ->whereHas('gamePlayer', fn ($q) => $q->where('team_id', $game->team_id))
            ->where('expires_at', '>', $currentDate)
            ->where('expires_at', '<=', $currentDate->copy()->addDays(7))
            ->get();

        if ($expiringOffers->isEmpty()) {
            return;
        }

        // Batch-load recent expiring-offer notifications to avoid per-offer queries
        $offerIds = $expiringOffers->pluck('id')->toArray();
        $cutoff = $currentDate->copy()->subDay();
        $recentlyNotifiedOfferIds = GameNotification::where('game_id', $game->id)
            ->where('type', GameNotification::TYPE_TRANSFER_OFFER_EXPIRING)
            ->where('game_date', '>', $cutoff)
            ->get(['metadata'])
            ->pluck('metadata.offer_id')
            ->filter()
            ->toArray();

        foreach ($expiringOffers as $offer) {
            if (! in_array($offer->id, $recentlyNotifiedOfferIds)) {
                $this->notificationService->notifyExpiringOffer($game, $offer);
            }
        }
    }

    private function checkExpiringContracts(Game $game): void
    {
        $currentDate = $game->current_date;
        $sixMonthsOut = $currentDate->copy()->addMonths(6);

        $expiringPlayers = GamePlayer::with('player')
            ->where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->whereNull('pending_annual_wage') // not already renewed
            ->whereNull('retiring_at_season') // retiring players can't be renewed — don't nag
            ->where('contract_until', '<=', $sixMonthsOut)
            ->where('contract_until', '>', $currentDate)
            ->whereDoesntHave('transferOffers', function ($q) {
                $q->where('status', TransferOffer::STATUS_AGREED)
                    ->where('offer_type', TransferOffer::TYPE_PRE_CONTRACT);
            })
            ->whereDoesntHave('latestRenewalNegotiation', function ($q) {
                $q->where('status', RenewalNegotiation::STATUS_CLUB_DECLINED);
            })
            ->whereDoesntHave('activeLoan')
            ->get();

        if ($expiringPlayers->isEmpty()) {
            return;
        }

        // Batch-load recent contract expiry notifications to avoid per-player queries
        $cutoff = $currentDate->copy()->subDays(30);
        $recentlyNotifiedPlayerIds = GameNotification::where('game_id', $game->id)
            ->where('type', GameNotification::TYPE_CONTRACT_EXPIRING)
            ->where('game_date', '>', $cutoff)
            ->get(['metadata'])
            ->pluck('metadata.player_id')
            ->filter()
            ->toArray();

        foreach ($expiringPlayers as $player) {
            if (in_array($player->id, $recentlyNotifiedPlayerIds)) {
                continue;
            }

            $monthsLeft = (int) $currentDate->diffInMonths($player->contract_until);
            $this->notificationService->notifyExpiringContract($game, $player, $monthsLeft);
        }
    }

    private function processAITransferBatch(Game $game): void
    {
        // The user-facing market is refreshed year-round so players can be
        // bought at any time. Bids accepted out-of-window are stored as
        // STATUS_AGREED and flushed when the next window opens.
        $this->transferMarketService->refreshListings($game);

        // AI-to-AI transfer activity only runs while a window is open.
        $windowType = TransferWindowType::fromDate($game->current_date);
        if (! $windowType) {
            return;
        }

        $this->aiTransferMarketService->processTransferBatch($game, $windowType->value);
    }

}
