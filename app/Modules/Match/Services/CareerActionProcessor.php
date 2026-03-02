<?php

namespace App\Modules\Match\Services;

use App\Models\AcademyPlayer;
use App\Models\Game;
use App\Models\GameNotification;
use App\Models\TransferOffer;
use App\Modules\Academy\Services\YouthAcademyService;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Transfer\Services\AITransferMarketService;
use App\Modules\Transfer\Services\ContractService;
use App\Modules\Transfer\Services\LoanService;
use App\Modules\Transfer\Services\ScoutingService;
use App\Modules\Transfer\Services\TransferService;

class CareerActionProcessor
{
    public function __construct(
        private readonly TransferService $transferService,
        private readonly ContractService $contractService,
        private readonly ScoutingService $scoutingService,
        private readonly LoanService $loanService,
        private readonly YouthAcademyService $youthAcademyService,
        private readonly NotificationService $notificationService,
        private readonly AITransferMarketService $aiTransferMarketService,
    ) {}

    public function process(Game $game): void
    {
        // Process transfers when window is open
        if ($game->isTransferWindowOpen()) {
            $completedOutgoing = $this->transferService->completeAgreedTransfers($game);
            $completedIncoming = $this->transferService->completeIncomingTransfers($game);
            foreach ($completedOutgoing->merge($completedIncoming) as $offer) {
                $this->notificationService->notifyTransferComplete($game, $offer);
            }
        }

        // Generate transfer offers (can happen anytime, but more during windows)
        if ($game->isTransferWindowOpen()) {
            $listedOffers = $this->transferService->generateOffersForListedPlayers($game);
            $unsolicitedOffers = $this->transferService->generateUnsolicitedOffers($game);
            foreach ($listedOffers->merge($unsolicitedOffers) as $offer) {
                $this->notificationService->notifyTransferOffer($game, $offer);
            }
        }

        // Resolve pending renewal negotiations
        $renewalResults = $this->contractService->resolveRenewalNegotiations($game);
        foreach ($renewalResults as $result) {
            $this->notificationService->notifyRenewalResult($game, $result['negotiation'], $result['result']);
        }

        // Pre-contract offers (January onwards for expiring contracts)
        $preContractOffers = $this->transferService->generatePreContractOffers($game);
        foreach ($preContractOffers as $offer) {
            $this->notificationService->notifyTransferOffer($game, $offer);
        }

        // Resolve pending incoming pre-contract offers (after response delay)
        $resolvedPreContracts = $this->transferService->resolveIncomingPreContractOffers($game, $this->scoutingService);
        foreach ($resolvedPreContracts as $result) {
            $this->notificationService->notifyPreContractResult($game, $result['offer']);
        }

        // Resolve pending incoming bids (deferred from user submission)
        $resolvedBids = $this->transferService->resolveIncomingBids($game, $this->scoutingService);
        foreach ($resolvedBids as $result) {
            $this->notificationService->notifyBidResult($game, $result['offer'], $result['result']);
        }

        // Resolve pending incoming loan requests (deferred from user submission)
        $resolvedLoans = $this->transferService->resolveIncomingLoanRequests($game, $this->scoutingService);
        foreach ($resolvedLoans as $result) {
            $this->notificationService->notifyLoanRequestResult($game, $result['offer'], $result['result']);
        }

        // Tick scout search progress
        $scoutReport = $this->scoutingService->tickSearch($game);
        if ($scoutReport?->isCompleted()) {
            $this->notificationService->notifyScoutComplete($game, $scoutReport);
        }

        // Process loan searches
        $loanResults = $this->loanService->processLoanSearches($game);
        foreach ($loanResults['found'] as $result) {
            $this->notificationService->notifyLoanDestinationFound(
                $game,
                $result['player'],
                $result['destination'],
                $result['windowOpen'],
            );
        }
        foreach ($loanResults['expired'] as $result) {
            $this->notificationService->notifyLoanSearchFailed($game, $result['player']);
        }

        // Check for expiring transfer offers (2 days or less)
        $this->checkExpiringOffers($game);

        // Develop academy players each matchday
        $this->youthAcademyService->developPlayers($game);

        // Add pending action if any players still need evaluation (from season-end)
        $needsEval = AcademyPlayer::where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->where('is_on_loan', false)
            ->where('evaluation_needed', true)
            ->exists();

        if ($needsEval) {
            if (! $game->hasPendingAction('academy_evaluation')) {
                $game->addPendingAction('academy_evaluation', 'game.squad.academy.evaluate');
                $this->notificationService->notifyAcademyEvaluation($game);
            }
        }

        // Notify user when a transfer window opens
        $this->processTransferWindowOpen($game);

        // AI transfer market: process when a transfer window just closed
        $this->processTransferWindowClose($game);
    }

    private function checkExpiringOffers(Game $game): void
    {
        $currentDate = $game->current_date;
        $expiringOffers = TransferOffer::with(['gamePlayer.player', 'offeringTeam'])
            ->where('game_id', $game->id)
            ->where('status', TransferOffer::STATUS_PENDING)
            ->whereHas('gamePlayer', fn ($q) => $q->where('team_id', $game->team_id))
            ->where('expires_at', '>', $currentDate)
            ->where('expires_at', '<=', $currentDate->copy()->addDays(2))
            ->get();

        foreach ($expiringOffers as $offer) {
            if (! $this->notificationService->hasRecentNotification(
                $game->id,
                GameNotification::TYPE_TRANSFER_OFFER_EXPIRING,
                ['offer_id' => $offer->id],
                1,
                $game->current_date,
            )) {
                $this->notificationService->notifyExpiringOffer($game, $offer);
            }
        }
    }

    private function processTransferWindowOpen(Game $game): void
    {
        $month = (int) $game->current_date->format('n');

        // Summer window notification is handled at season start
        // (SetupNewGame + OnboardingResetProcessor). Only detect winter here.
        if ($month !== 1) {
            return;
        }

        $startOfWindow = $game->current_date->copy()->startOfMonth();

        $alreadyNotified = GameNotification::where('game_id', $game->id)
            ->where('type', GameNotification::TYPE_TRANSFER_WINDOW_OPEN)
            ->where('game_date', '>=', $startOfWindow)
            ->exists();

        if ($alreadyNotified) {
            return;
        }

        $this->notificationService->notifyTransferWindowOpen($game, 'winter');
    }

    private function processTransferWindowClose(Game $game): void
    {
        $month = (int) $game->current_date->format('n');

        $window = match ($month) {
            9 => 'summer',
            2 => 'winter',
            default => null,
        };

        if (! $window) {
            return;
        }

        // Already processed this window? Check if notification exists for this month
        $alreadyProcessed = GameNotification::where('game_id', $game->id)
            ->where('type', GameNotification::TYPE_AI_TRANSFER_ACTIVITY)
            ->where('game_date', '>=', $game->current_date->copy()->startOfMonth())
            ->where('game_date', '<=', $game->current_date->copy()->endOfMonth())
            ->exists();

        if ($alreadyProcessed) {
            return;
        }

        $this->aiTransferMarketService->processWindowClose($game, $window);
    }
}
