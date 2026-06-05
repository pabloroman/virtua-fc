<?php

namespace App\Modules\Notification\Services;

use App\Models\Game;
use App\Models\GameNotification;
use App\Models\GamePlayer;

use App\Models\ScoutReport;
use App\Models\ShortlistedPlayer;
use App\Models\Team;
use App\Models\TransferOffer;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class NotificationService
{
    private const CLEANUP_DAYS = 14;

    // ==========================================
    // Core Methods
    // ==========================================

    /**
     * Create a notification for a game.
     */
    public function create(
        Game $game,
        string $type,
        string $title,
        ?string $message = null,
        string $priority = GameNotification::PRIORITY_INFO,
        array $metadata = [],
        ?string $icon = null
    ): GameNotification {
        return GameNotification::create([
            'id' => Str::uuid()->toString(),
            'game_id' => $game->id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'icon' => $icon ?? $this->getDefaultIcon($type),
            'priority' => $priority,
            'metadata' => $metadata,
            'game_date' => $game->current_date,
        ]);
    }

    /**
     * Mark a notification as read.
     */
    public function markAsRead(string $notificationId): ?GameNotification
    {
        $notification = GameNotification::find($notificationId);

        if ($notification) {
            $notification->markAsRead();
        }

        return $notification;
    }

    /**
     * Mark all notifications for a game as read.
     */
    public function markAllAsRead(string $gameId): int
    {
        return GameNotification::where('game_id', $gameId)
            ->unread()
            ->update(['read_at' => now()]);
    }

    /**
     * Acknowledge (mark read) unread CRITICAL notifications for a game.
     * Backs the critical-alert popup's dismiss/action buttons: once acknowledged
     * the alert no longer pops on subsequent page loads. The popup groups all
     * pending criticals of one type, so it passes that type to clear the whole
     * group at once; with no type (safe fallback) every critical is cleared.
     * Always game- and critical-scoped, so a foreign type posted in the form is a
     * no-op rather than a way to clear unrelated notifications.
     */
    public function markCriticalAsRead(string $gameId, ?string $type = null): int
    {
        $query = GameNotification::where('game_id', $gameId)
            ->unread()
            ->where('priority', GameNotification::PRIORITY_CRITICAL);

        if ($type !== null) {
            $query->where('type', $type);
        }

        return $query->update(['read_at' => now()]);
    }

    /**
     * The pending critical-alert group to surface in the blocking popup: all
     * unread CRITICAL notifications sharing the type of the most-recent one.
     * Same-type criticals (e.g. several purchase offers) are shown together with
     * a single dismiss/action since they all route to the same page; criticals of
     * other types surface as their own group on a later load. Returns an empty
     * collection when nothing is pending.
     */
    public function pendingCriticalAlertGroup(string $gameId): Collection
    {
        $criticals = GameNotification::where('game_id', $gameId)
            ->unread()
            ->where('priority', GameNotification::PRIORITY_CRITICAL)
            ->orderByDesc('game_date')
            ->get();

        if ($criticals->isEmpty()) {
            return $criticals;
        }

        return $criticals->where('type', $criticals->first()->type)->values();
    }

    /**
     * Get unread count for a game.
     */
    public function getUnreadCount(string $gameId): int
    {
        return GameNotification::where('game_id', $gameId)
            ->unread()
            ->count();
    }

    /**
     * Get notifications for a game.
     */
    public function getNotifications(string $gameId, bool $unreadOnly = false, int $limit = 10): Collection
    {
        $query = GameNotification::where('game_id', $gameId)
            ->orderByDesc('game_date')
            ->orderByRaw("CASE priority
                WHEN 'milestone' THEN 0
                WHEN 'critical' THEN 1
                WHEN 'warning' THEN 2
                ELSE 3
            END")
            ->limit($limit);

        if ($unreadOnly) {
            $query->unread();
        }

        return $query->get();
    }

    /**
     * Clean up old read notifications based on in-game time.
     */
    public function cleanupOldNotifications(Game $game): int
    {
        $cutoffDate = $game->current_date->subDays(self::CLEANUP_DAYS);

        return GameNotification::where('game_id', $game->id)
            ->read()
            ->where('game_date', '<', $cutoffDate)
            ->delete();
    }

    // ==========================================
    // Player Notifications
    // ==========================================

    /**
     * Create an injury notification.
     */
    public function notifyInjury(Game $game, GamePlayer $player, string $injuryType, int $weeksOut, bool $duringMatch = true): GameNotification
    {
        $translatedInjury = $this->translateInjuryType($injuryType);
        $translatedLocation = __('notifications.injury_location_'.($duringMatch ? 'match' : 'training'));

        $messageKey = $player->injury_until
            ? 'notifications.player_injured_message_with_date'
            : 'notifications.player_injured_message';

        return $this->create(
            game: $game,
            type: GameNotification::TYPE_PLAYER_INJURED,
            title: __('notifications.player_injured_title', ['player' => $player->name]),
            message: __($messageKey, [
                'player' => $player->name,
                'injury' => $translatedInjury,
                'location' => $translatedLocation,
                'date' => $player->injury_until?->translatedFormat('j M Y'),
            ]),
            priority: GameNotification::PRIORITY_WARNING,
            metadata: [
                'player_id' => $player->id,
                'injury_type' => $injuryType,
                'weeks_out' => $weeksOut,
            ],
        );
    }

    /**
     * Create a suspension notification.
     */
    public function notifySuspension(Game $game, GamePlayer $player, int $matches, string $reason, string $competition): GameNotification
    {
        return $this->create(
            game: $game,
            type: GameNotification::TYPE_PLAYER_SUSPENDED,
            title: __('notifications.player_suspended_title', ['player' => $player->name]),
            message: trans_choice('notifications.player_suspended_message', $matches, [
                'player' => $player->name,
                'matches' => $matches,
                'reason' => $reason,
                'competition' => __($competition),
            ]),
            priority: GameNotification::PRIORITY_WARNING,
            metadata: [
                'player_id' => $player->id,
                'matches' => $matches,
                'reason' => $reason,
            ],
        );
    }

    /**
     * Create a player recovered notification.
     */
    public function notifyRecovery(Game $game, GamePlayer $player): GameNotification
    {
        return $this->create(
            game: $game,
            type: GameNotification::TYPE_PLAYER_RECOVERED,
            title: __('notifications.player_recovered_title', ['player' => $player->name]),
            message: __('notifications.player_recovered_message', ['player' => $player->name]),
            priority: GameNotification::PRIORITY_INFO,
            metadata: [
                'player_id' => $player->id,
            ],
        );
    }

    /**
     * Create a low fitness notification.
     */
    public function notifyLowFitness(Game $game, GamePlayer $player): GameNotification
    {
        return $this->create(
            game: $game,
            type: GameNotification::TYPE_LOW_FITNESS,
            title: __('notifications.low_fitness_title', ['player' => $player->name]),
            message: __('notifications.low_fitness_message', [
                'player' => $player->name,
                'fitness' => $player->fitness,
            ]),
            priority: GameNotification::PRIORITY_WARNING,
            metadata: [
                'player_id' => $player->id,
                'fitness' => $player->fitness,
            ],
        );
    }

    // ==========================================
    // Transfer Notifications
    // ==========================================

    /**
     * Create a transfer offer received notification.
     */
    public function notifyTransferOffer(Game $game, TransferOffer $offer): GameNotification
    {
        $player = $offer->gamePlayer;
        $fee = $offer->isPreContract()
            ? __('notifications.free_transfer')
            : $offer->formatted_transfer_fee;

        return $this->create(
            game: $game,
            type: GameNotification::TYPE_TRANSFER_OFFER_RECEIVED,
            title: __('notifications.transfer_offer_title', ['player' => $player->name]),
            message: __('notifications.transfer_offer_message', [
                'team' => $offer->offeringTeam->name,
                'team_el' => Str::ucfirst($offer->offeringTeam->nameWithEl()),
                'fee' => $fee,
            ]),
            priority: GameNotification::PRIORITY_CRITICAL,
            metadata: [
                'offer_id' => $offer->id,
                'player_id' => $player->id,
                'team_id' => $offer->offering_team_id,
                'fee' => $offer->transfer_fee,
            ],
        );
    }

    /**
     * Create an expiring offer notification.
     */
    public function notifyExpiringOffer(Game $game, TransferOffer $offer): GameNotification
    {
        $player = $offer->gamePlayer;

        return $this->create(
            game: $game,
            type: GameNotification::TYPE_TRANSFER_OFFER_EXPIRING,
            title: __('notifications.offer_expiring_title', ['player' => $player->name]),
            message: trans_choice('notifications.offer_expiring_message', $offer->days_until_expiry, [
                'team_de' => $offer->offeringTeam->nameWithDe(),
                'player' => $player->name,
            ]),
            priority: GameNotification::PRIORITY_WARNING,
            metadata: [
                'offer_id' => $offer->id,
                'player_id' => $player->id,
                'days_left' => $offer->days_until_expiry,
            ],
        );
    }

    /**
     * Create a notification when a transfer completes and a player joins or leaves the squad.
     */
    public function notifyTransferComplete(Game $game, TransferOffer $offer): GameNotification
    {
        $player = $offer->gamePlayer;
        $isIncoming = $offer->direction === TransferOffer::DIRECTION_INCOMING;

        if ($isIncoming) {
            $fromTeam = $offer->sellingTeam ?? $player->team;
            $fee = $offer->transfer_fee > 0
                ? $offer->formatted_transfer_fee
                : __('notifications.free_transfer');

            $title = __('notifications.transfer_complete_incoming_title', ['player' => $player->name]);
            $message = __('notifications.transfer_complete_incoming_message', [
                'player' => $player->name,
                'team_de' => $fromTeam?->nameWithDe() ?? '',
                'fee' => $fee,
            ]);
        } elseif ($offer->offer_type === TransferOffer::TYPE_LOAN_OUT) {
            $title = __('notifications.loan_out_complete_title', ['player' => $player->name]);
            $message = __('notifications.loan_out_complete_message', [
                'player' => $player->name,
                'team_a' => $offer->offeringTeam->nameWithA(),
            ]);
        } else {
            $title = __('notifications.transfer_complete_outgoing_title', ['player' => $player->name]);
            $message = __('notifications.transfer_complete_outgoing_message', [
                'player' => $player->name,
                'team_a' => $offer->offeringTeam->nameWithA(),
                'fee' => $offer->formatted_transfer_fee,
            ]);
        }

        return $this->create(
            game: $game,
            type: GameNotification::TYPE_TRANSFER_COMPLETE,
            title: $title,
            message: $message,
            priority: GameNotification::PRIORITY_INFO,
            metadata: [
                'offer_id' => $offer->id,
                'player_id' => $player->id,
                'direction' => $offer->direction,
            ],
        );
    }

    /**
     * Notify the user that an AI club triggered the release clause on one of
     * their players (Phase 3). Fired at trigger time — before the agreed sale
     * completes — so the loss is announced up front. Uses PRIORITY_CRITICAL so
     * it surfaces as a blocking, must-dismiss popup the user can't miss. Deduped
     * on player_id so a player can't generate two clause alerts in a day.
     */
    public function notifyPlayerLeftViaReleaseClause(Game $game, TransferOffer $offer): ?GameNotification
    {
        $player = $offer->gamePlayer;

        if ($this->hasRecentNotification(
            $game->id,
            GameNotification::TYPE_PLAYER_LEFT_VIA_RELEASE_CLAUSE,
            ['player_id' => $player->id],
            currentDate: $game->current_date,
        )) {
            return null;
        }

        return $this->create(
            game: $game,
            type: GameNotification::TYPE_PLAYER_LEFT_VIA_RELEASE_CLAUSE,
            title: __('notifications.player_left_via_release_clause_title', ['player' => $player->name]),
            message: __('notifications.player_left_via_release_clause_message', [
                'player' => $player->name,
                'team_a' => $offer->offeringTeam->nameWithA(),
                'fee' => $offer->formatted_transfer_fee,
            ]),
            priority: GameNotification::PRIORITY_CRITICAL,
            metadata: [
                'player_id' => $player->id,
                'buying_team_id' => $offer->offering_team_id,
                'clause_amount' => $offer->transfer_fee,
            ],
        );
    }

    /**
     * Create a high-priority notification when an agreed incoming transfer
     * can't be completed (e.g. the player is no longer at the expected seller,
     * or a budget guard fired). The reserved budget is released by rejecting the
     * offer; this tells the user why the signing did not go through.
     */
    public function notifyTransferFellThrough(Game $game, GamePlayer $player, ?Team $seller = null): GameNotification
    {
        return $this->create(
            game: $game,
            type: GameNotification::TYPE_TRANSFER_FAILED,
            title: __('notifications.transfer_failed_title', ['player' => $player->name]),
            message: __('notifications.transfer_failed_message', [
                'player' => $player->name,
                'team' => $seller?->name ?? '',
            ]),
            priority: GameNotification::PRIORITY_WARNING,
            metadata: [
                'player_id' => $player->id,
                'team_id' => $seller?->id,
            ],
        );
    }

    /**
     * Create a notification for a pre-contract offer result.
     */
    public function notifyPreContractResult(Game $game, TransferOffer $offer): GameNotification
    {
        $player = $offer->gamePlayer;
        $accepted = $offer->isAgreed();

        return $this->create(
            game: $game,
            type: GameNotification::TYPE_TRANSFER_OFFER_RECEIVED,
            title: $accepted
                ? __('messages.pre_contract_result_accepted', ['player' => $player->name])
                : __('messages.pre_contract_result_rejected', ['player' => $player->name]),
            message: $accepted
                ? __('messages.pre_contract_result_accepted', ['player' => $player->name])
                : __('messages.pre_contract_result_rejected', ['player' => $player->name]),
            priority: $accepted ? GameNotification::PRIORITY_MILESTONE : GameNotification::PRIORITY_WARNING,
            metadata: [
                'offer_id' => $offer->id,
                'player_id' => $player->id,
                'accepted' => $accepted,
            ],
        );
    }

    /**
     * Create a notification for a loan request result.
     */
    public function notifyLoanRequestResult(Game $game, TransferOffer $offer, string $result): GameNotification
    {
        $player = $offer->gamePlayer;
        $sellingTeam = $offer->sellingTeam ?? $player->team;
        $teamName = $sellingTeam?->name ?? '';

        return match ($result) {
            'accepted' => $this->create(
                game: $game,
                type: GameNotification::TYPE_LOAN_REQUEST_RESULT,
                title: __('notifications.loan_accepted_title', ['player' => $player->name]),
                message: __('notifications.loan_accepted', [
                    'team' => $teamName,
                    'player' => $player->name,
                ]),
                priority: GameNotification::PRIORITY_MILESTONE,
                metadata: ['offer_id' => $offer->id, 'player_id' => $player->id, 'result' => 'accepted'],
            ),
            'rejected' => $this->create(
                game: $game,
                type: GameNotification::TYPE_LOAN_REQUEST_RESULT,
                title: __('notifications.loan_rejected_title', ['player' => $player->name]),
                message: __('notifications.loan_rejected', [
                    'team' => $teamName,
                    'player' => $player->name,
                ]),
                priority: GameNotification::PRIORITY_WARNING,
                metadata: ['offer_id' => $offer->id, 'player_id' => $player->id, 'result' => 'rejected'],
            ),
            default => throw new \InvalidArgumentException("Unexpected loan request result: {$result}"),
        };
    }

    // ==========================================
    // Scout Notifications
    // ==========================================

    /**
     * Create a scout report complete notification.
     */
    public function notifyScoutComplete(Game $game, ScoutReport $report): GameNotification
    {
        $playerCount = $report->players->count();

        return $this->create(
            game: $game,
            type: GameNotification::TYPE_SCOUT_REPORT_COMPLETE,
            title: __('notifications.scout_complete_title'),
            message: __('notifications.scout_complete_message', ['count' => $playerCount]),
            priority: GameNotification::PRIORITY_INFO,
            metadata: [
                'report_id' => $report->id,
                'player_count' => $playerCount,
            ],
        );
    }

    /**
     * Create a tracking intel ready notification.
     */
    public function notifyTrackingIntelReady(Game $game, ShortlistedPlayer $entry): GameNotification
    {
        $player = $entry->gamePlayer;
        $levelKey = $entry->intel_level === ShortlistedPlayer::INTEL_DEEP
            ? 'notifications.tracking_deep_intel_ready'
            : 'notifications.tracking_report_ready';

        return $this->create(
            game: $game,
            type: GameNotification::TYPE_TRACKING_INTEL_READY,
            title: __('notifications.tracking_intel_title', ['player' => $player->name]),
            message: __($levelKey, ['player' => $player->name]),
            priority: GameNotification::PRIORITY_INFO,
            metadata: [
                'player_id' => $player->id,
                'intel_level' => $entry->intel_level,
            ],
        );
    }

    // ==========================================
    // Contract Notifications
    // ==========================================

    /**
     * Create an expiring contract notification.
     */
    public function notifyExpiringContract(Game $game, GamePlayer $player, int $monthsLeft): GameNotification
    {
        return $this->create(
            game: $game,
            type: GameNotification::TYPE_CONTRACT_EXPIRING,
            title: __('notifications.contract_expiring_title', ['player' => $player->name]),
            message: __('notifications.contract_expiring_message', [
                'player' => $player->name,
                'months' => $monthsLeft,
            ]),
            priority: GameNotification::PRIORITY_WARNING,
            metadata: [
                'player_id' => $player->id,
                'months_left' => $monthsLeft,
            ],
        );
    }

    // ==========================================
    // Loan Notifications
    // ==========================================

    /**
     * Create a loan return notification.
     */
    public function notifyLoanReturn(Game $game, GamePlayer $player, Team $fromTeam): GameNotification
    {
        return $this->create(
            game: $game,
            type: GameNotification::TYPE_LOAN_RETURN,
            title: __('notifications.loan_return_title', ['player' => $player->name]),
            message: __('notifications.loan_return_message', [
                'player' => $player->name,
                'team_en' => $fromTeam->nameWithEn(),
            ]),
            priority: GameNotification::PRIORITY_INFO,
            metadata: [
                'player_id' => $player->id,
                'from_team' => $fromTeam->name,
            ],
        );
    }

    /**
     * Notify the user that an AI club has tabled a loan offer for a player
     * they listed on the loan market. The user accepts it on the outgoing
     * transfers screen; ignoring it lets it expire and lets further offers
     * roll in on later matchdays.
     */
    public function notifyLoanOfferReceived(Game $game, GamePlayer $player, Team $destination): GameNotification
    {
        return $this->create(
            game: $game,
            type: GameNotification::TYPE_LOAN_DESTINATION_FOUND,
            title: __('notifications.loan_offer_received_title', [
                'player' => $player->name,
            ]),
            message: __('notifications.loan_offer_received_message', [
                'team' => $destination->name,
                'team_el' => Str::ucfirst($destination->nameWithEl()),
            ]),
            priority: GameNotification::PRIORITY_INFO,
            metadata: [
                'player_id' => $player->id,
                'team_id' => $destination->id,
                'team_name' => $destination->name,
            ],
        );
    }

    /**
     * Create a loan search failed notification.
     */
    public function notifyLoanSearchFailed(Game $game, GamePlayer $player): GameNotification
    {
        return $this->create(
            game: $game,
            type: GameNotification::TYPE_LOAN_SEARCH_FAILED,
            title: __('notifications.loan_search_failed_title', ['player' => $player->name]),
            message: __('notifications.loan_search_failed_message', ['player' => $player->name]),
            priority: GameNotification::PRIORITY_WARNING,
            metadata: [
                'player_id' => $player->id,
            ],
        );
    }

    // ==========================================
    // Competition Notifications
    // ==========================================

    /**
     * Create a competition advancement notification.
     */
    public function notifyCompetitionAdvancement(
        Game $game, string $competitionId, string $competitionName, string $nextStage
    ): GameNotification {
        return $this->create(
            game: $game,
            type: GameNotification::TYPE_COMPETITION_ADVANCEMENT,
            title: __('notifications.competition_advancement_title', ['competition' => __($competitionName)]),
            message: __('notifications.competition_advancement_message', ['stage' => __($nextStage)]),
            priority: GameNotification::PRIORITY_MILESTONE,
            metadata: [
                'competition_id' => $competitionId,
            ],
        );
    }

    /**
     * Create a trophy won notification (cup final won).
     */
    public function notifyTrophyWon(
        Game $game, string $competitionId, string $competitionName
    ): GameNotification {
        return $this->create(
            game: $game,
            type: GameNotification::TYPE_COMPETITION_ADVANCEMENT,
            title: __('notifications.trophy_won_title', ['competition' => __($competitionName)]),
            message: __('cup.champion_message', ['competition' => __($competitionName)]),
            priority: GameNotification::PRIORITY_CRITICAL,
            metadata: [
                'competition_id' => $competitionId,
            ],
        );
    }

    /**
     * Create a competition elimination notification.
     */
    public function notifyCompetitionElimination(
        Game $game, string $competitionId, string $competitionName, string $stage
    ): GameNotification {
        return $this->create(
            game: $game,
            type: GameNotification::TYPE_COMPETITION_ELIMINATION,
            title: __('notifications.competition_elimination_title', ['competition' => __($competitionName)]),
            message: __('notifications.competition_elimination_message', ['stage' => __($stage)]),
            priority: GameNotification::PRIORITY_MILESTONE,
            metadata: [
                'competition_id' => $competitionId,
            ],
        );
    }

    // ==========================================
    // Academy Notifications
    // ==========================================

    /**
     * Create a notification for a new academy prospect.
     */
    public function notifyAcademyBatch(Game $game, int $count): GameNotification
    {
        return $this->create(
            game: $game,
            type: GameNotification::TYPE_ACADEMY_BATCH,
            title: __('notifications.academy_batch_title'),
            message: __('notifications.academy_batch_message', ['count' => $count]),
            priority: GameNotification::PRIORITY_MILESTONE,
        );
    }

    // ==========================================
    // AI Transfer Market Notifications
    // ==========================================

    /**
     * Create a single summary notification for AI transfer window activity.
     */
    public function notifyAITransferSummary(Game $game, int $totalMoves, string $window): GameNotification
    {
        return $this->create(
            game: $game,
            type: GameNotification::TYPE_AI_TRANSFER_ACTIVITY,
            title: __('notifications.ai_transfer_title', ['window' => __("notifications.ai_transfer_window_{$window}")]),
            message: __('notifications.ai_transfer_message', ['count' => $totalMoves]),
            priority: GameNotification::PRIORITY_INFO,
            metadata: [
                'window' => $window,
                'season' => $game->season,
            ],
        );
    }

    // ==========================================
    // Transfer Window Notifications
    // ==========================================

    /**
     * Notify a pro-manager user that one or more clubs have come calling at
     * season end. A single notification covers the whole batch — the
     * season-end screen is where the user reviews and picks an offer.
     */
    public function notifyJobOfferReceived(Game $game, int $offerCount, bool $fired): GameNotification
    {
        $titleKey = $fired
            ? 'notifications.job_offer_post_firing_title'
            : 'notifications.job_offer_received_title';

        return $this->create(
            game: $game,
            type: GameNotification::TYPE_JOB_OFFER_RECEIVED,
            title: __($titleKey, ['count' => $offerCount]),
            message: __('notifications.job_offer_received_message', ['count' => $offerCount]),
            priority: $fired ? GameNotification::PRIORITY_WARNING : GameNotification::PRIORITY_MILESTONE,
            metadata: [
                'offer_count' => $offerCount,
                'fired' => $fired,
                'season' => $game->season,
            ],
        );
    }

    /**
     * Notify the user that a transfer window has opened.
     */
    public function notifyTransferWindowOpen(Game $game, string $window): GameNotification
    {
        $windowLabel = __("notifications.ai_transfer_window_{$window}");

        return $this->create(
            game: $game,
            type: GameNotification::TYPE_TRANSFER_WINDOW_OPEN,
            title: __('notifications.transfer_window_open_title', ['window' => $windowLabel]),
            message: __('notifications.transfer_window_open_message'),
            priority: GameNotification::PRIORITY_INFO,
            metadata: [
                'window' => $window,
            ],
        );
    }

    /**
     * Notify the user that the transfer window is about to close.
     */
    public function notifyTransferWindowClosing(Game $game, string $window): GameNotification
    {
        $windowLabel = __("notifications.ai_transfer_window_{$window}");

        return $this->create(
            game: $game,
            type: GameNotification::TYPE_TRANSFER_WINDOW_CLOSING,
            title: __('notifications.transfer_window_closing_title', ['window' => $windowLabel]),
            message: __('notifications.transfer_window_closing_message'),
            priority: GameNotification::PRIORITY_WARNING,
            metadata: [
                'window' => $window,
            ],
        );
    }

    /**
     * Notify the user that the transfer window has closed.
     */
    public function notifyTransferWindowClosed(Game $game, string $window): GameNotification
    {
        $windowLabel = __("notifications.ai_transfer_window_{$window}");

        return $this->create(
            game: $game,
            type: GameNotification::TYPE_TRANSFER_WINDOW_CLOSED,
            title: __('notifications.transfer_window_closed_title', ['window' => $windowLabel]),
            message: __('notifications.transfer_window_closed_message'),
            priority: GameNotification::PRIORITY_INFO,
            metadata: [
                'window' => $window,
            ],
        );
    }

    // ==========================================
    // Tournament Notifications
    // ==========================================

    /**
     * Create a welcome notification for a new tournament game.
     */
    public function notifyTournamentWelcome(Game $game, string $competitionId, string $teamName): GameNotification
    {
        return $this->create(
            game: $game,
            type: GameNotification::TYPE_TOURNAMENT_WELCOME,
            title: __('notifications.tournament_welcome_title'),
            message: __('notifications.tournament_welcome_message'),
            priority: GameNotification::PRIORITY_MILESTONE,
            metadata: [
                'competition_id' => $competitionId,
            ],
        );
    }

    // ==========================================
    // Player Release Notifications
    // ==========================================

    /**
     * Create a notification when a player is released from the squad.
     */
    public function notifyPlayerReleased(Game $game, string $playerName, int $severance): GameNotification
    {
        $formattedSeverance = \App\Support\Money::format($severance);

        return $this->create(
            game: $game,
            type: GameNotification::TYPE_PLAYER_RELEASED,
            title: __('notifications.player_released_title', ['player' => $playerName]),
            message: $severance > 0
                ? __('notifications.player_released_message', ['player' => $playerName, 'severance' => $formattedSeverance])
                : __('notifications.player_released_message_free', ['player' => $playerName]),
            priority: GameNotification::PRIORITY_INFO,
        );
    }

    // ==========================================
    // Emergency & Forfeit Notifications
    // ==========================================

    /**
     * Create a notification when emergency free agents are signed for the user's team.
     */
    public function notifyEmergencySignings(Game $game, array $playerNames): GameNotification
    {
        return $this->create(
            game: $game,
            type: GameNotification::TYPE_EMERGENCY_SIGNING,
            title: __('notifications.emergency_signing_title'),
            message: __('notifications.emergency_signing_message', [
                'count' => count($playerNames),
                'players' => implode(', ', $playerNames),
            ]),
            priority: GameNotification::PRIORITY_WARNING,
        );
    }

    /**
     * Create a notification when a match is forfeited due to insufficient players.
     */
    public function notifyMatchForfeit(Game $game): GameNotification
    {
        return $this->create(
            game: $game,
            type: GameNotification::TYPE_MATCH_FORFEIT,
            title: __('notifications.match_forfeit_title'),
            message: __('notifications.match_forfeit_message'),
            priority: GameNotification::PRIORITY_WARNING,
        );
    }

    public function notifyBudgetLoanTaken(Game $game, string $formattedAmount, string $formattedRepayment): GameNotification
    {
        return $this->create(
            game: $game,
            type: GameNotification::TYPE_BUDGET_LOAN,
            title: __('notifications.budget_loan_taken_title'),
            message: __('notifications.budget_loan_taken_message', [
                'amount' => $formattedAmount,
                'repayment' => $formattedRepayment,
            ]),
            priority: GameNotification::PRIORITY_WARNING,
        );
    }

    public function notifyBudgetLoanRepaid(Game $game, string $formattedRepayment, bool $createdDebt): GameNotification
    {
        $message = $createdDebt
            ? __('notifications.budget_loan_repaid_with_debt', ['repayment' => $formattedRepayment])
            : __('notifications.budget_loan_repaid_message', ['repayment' => $formattedRepayment]);

        return $this->create(
            game: $game,
            type: GameNotification::TYPE_BUDGET_LOAN,
            title: __('notifications.budget_loan_repaid_title'),
            message: $message,
            priority: $createdDebt ? GameNotification::PRIORITY_WARNING : GameNotification::PRIORITY_INFO,
        );
    }

    public function notifyStadiumProjectCommitted(
        Game $game,
        \App\Modules\Stadium\Enums\StadiumProjectType $type,
        int $targetCapacity,
        string $completionLabel,
    ): GameNotification {
        return $this->create(
            game: $game,
            type: GameNotification::TYPE_STADIUM,
            title: __("notifications.stadium_{$type->value}_committed_title"),
            message: __("notifications.stadium_{$type->value}_committed_message", [
                'capacity' => number_format($targetCapacity, 0, ',', '.'),
                'completion' => $completionLabel,
            ]),
            priority: GameNotification::PRIORITY_INFO,
        );
    }

    public function notifyStadiumProjectCompleted(
        Game $game,
        \App\Modules\Stadium\Enums\StadiumProjectType $type,
        int $newCapacity,
    ): GameNotification {
        return $this->create(
            game: $game,
            type: GameNotification::TYPE_STADIUM,
            title: __("notifications.stadium_{$type->value}_completed_title"),
            message: __("notifications.stadium_{$type->value}_completed_message", [
                'capacity' => number_format($newCapacity, 0, ',', '.'),
            ]),
            priority: GameNotification::PRIORITY_MILESTONE,
        );
    }

    public function notifyStadiumLoanDrawn(
        Game $game,
        string $formattedPrincipal,
        int $termYears,
    ): GameNotification {
        return $this->create(
            game: $game,
            type: GameNotification::TYPE_STADIUM,
            title: __('notifications.stadium_loan_drawn_title'),
            message: __('notifications.stadium_loan_drawn_message', [
                'amount' => $formattedPrincipal,
                'years' => $termYears,
            ]),
            priority: GameNotification::PRIORITY_WARNING,
        );
    }

    public function notifyStadiumLoanRepaid(
        Game $game,
        string $formattedPrincipal,
    ): GameNotification {
        return $this->create(
            game: $game,
            type: GameNotification::TYPE_STADIUM,
            title: __('notifications.stadium_loan_repaid_title'),
            message: __('notifications.stadium_loan_repaid_message', [
                'amount' => $formattedPrincipal,
            ]),
            priority: GameNotification::PRIORITY_INFO,
        );
    }

    public function notifySquadRegistrationRequired(Game $game, int $unenrolledCount): GameNotification
    {
        return $this->create(
            game: $game,
            type: GameNotification::TYPE_SQUAD_REGISTRATION_REQUIRED,
            title: __('notifications.squad_registration_required_title'),
            message: __('notifications.squad_registration_required_message', ['count' => $unenrolledCount]),
            priority: GameNotification::PRIORITY_WARNING,
        );
    }

    public function notifyUnenrolledBeforeWindowClose(Game $game, int $unenrolledCount, string $window): GameNotification
    {
        $windowLabel = __("notifications.ai_transfer_window_{$window}");

        return $this->create(
            game: $game,
            type: GameNotification::TYPE_SQUAD_REGISTRATION_REQUIRED,
            title: __('notifications.unenrolled_before_window_close_title', ['window' => $windowLabel]),
            message: __('notifications.unenrolled_before_window_close_message', ['count' => $unenrolledCount]),
            priority: GameNotification::PRIORITY_WARNING,
            metadata: [
                'window' => $window,
            ],
        );
    }

    // ==========================================
    // Helpers
    // ==========================================

    /**
     * Get the default icon for a notification type.
     */
    private function getDefaultIcon(string $type): string
    {
        return match ($type) {
            GameNotification::TYPE_PLAYER_INJURED => 'injury',
            GameNotification::TYPE_PLAYER_SUSPENDED => 'suspended',
            GameNotification::TYPE_PLAYER_RECOVERED => 'recovered',
            GameNotification::TYPE_LOW_FITNESS => 'fitness',
            GameNotification::TYPE_TRANSFER_OFFER_RECEIVED => 'transfer',
            GameNotification::TYPE_TRANSFER_OFFER_EXPIRING => 'clock',
            GameNotification::TYPE_SCOUT_REPORT_COMPLETE => 'scout',
            GameNotification::TYPE_CONTRACT_EXPIRING => 'contract',
            GameNotification::TYPE_LOAN_RETURN => 'loan',
            GameNotification::TYPE_LOAN_DESTINATION_FOUND => 'loan_destination',
            GameNotification::TYPE_LOAN_SEARCH_FAILED => 'loan_failed',
            GameNotification::TYPE_COMPETITION_ADVANCEMENT => 'trophy',
            GameNotification::TYPE_COMPETITION_ELIMINATION => 'eliminated',
            GameNotification::TYPE_ACADEMY_PROSPECT => 'academy',
            GameNotification::TYPE_TRANSFER_COMPLETE => 'transfer_complete',
            GameNotification::TYPE_TRANSFER_FAILED => 'transfer',
            GameNotification::TYPE_LOAN_REQUEST_RESULT => 'loan',
            GameNotification::TYPE_TOURNAMENT_WELCOME => 'trophy',
            GameNotification::TYPE_AI_TRANSFER_ACTIVITY => 'transfer',
            GameNotification::TYPE_TRANSFER_WINDOW_OPEN => 'transfer',
            GameNotification::TYPE_PLAYER_RELEASED => 'transfer_complete',
            GameNotification::TYPE_TRACKING_INTEL_READY => 'scout',
            GameNotification::TYPE_EMERGENCY_SIGNING => 'transfer_complete',
            GameNotification::TYPE_MATCH_FORFEIT => 'eliminated',
            GameNotification::TYPE_BUDGET_LOAN => 'transfer',
            GameNotification::TYPE_TRANSFER_WINDOW_CLOSING => 'clock',
            GameNotification::TYPE_SQUAD_REGISTRATION_REQUIRED => 'squad',
            GameNotification::TYPE_JOB_OFFER_RECEIVED => 'trophy',
            GameNotification::TYPE_PLAYER_LEFT_VIA_RELEASE_CLAUSE => 'transfer',
            default => 'bell',
        };
    }

    /**
     * Translate an injury type to the current locale.
     */
    private function translateInjuryType(string $injuryType): string
    {
        $key = 'notifications.injury_' . str_replace([' ', '-'], '_', strtolower($injuryType));

        $translated = __($key);

        // Return original if no translation found
        if ($translated === $key) {
            return $injuryType;
        }

        return $translated;
    }

    /**
     * Check if a similar notification already exists (to avoid duplicates).
     *
     * @param  Carbon|null  $currentDate  Game's current date (avoids Game::find query when available)
     */
    public function hasRecentNotification(string $gameId, string $type, array $metadata, int $days = 1, ?Carbon $currentDate = null): bool
    {
        if (! $currentDate) {
            $currentDate = Carbon::parse(Game::where('id', $gameId)->value('current_date'));
        }
        $cutoff = $currentDate->copy()->subDays($days);

        $query = GameNotification::where('game_id', $gameId)
            ->where('type', $type)
            ->where('game_date', '>', $cutoff);

        // Check for matching metadata key (e.g., player_id for injury notifications)
        if (isset($metadata['player_id'])) {
            $query->whereJsonContains('metadata->player_id', $metadata['player_id']);
        }
        if (isset($metadata['offer_id'])) {
            $query->whereJsonContains('metadata->offer_id', $metadata['offer_id']);
        }

        return $query->exists();
    }
}
