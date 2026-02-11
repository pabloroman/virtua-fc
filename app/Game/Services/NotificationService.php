<?php

namespace App\Game\Services;

use App\Models\AcademyPlayer;
use App\Models\Game;
use App\Models\GameNotification;
use App\Models\GamePlayer;
use App\Models\ScoutReport;
use App\Models\Team;
use App\Models\TransferOffer;
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
    public function notifyInjury(Game $game, GamePlayer $player, string $injuryType, int $weeksOut): GameNotification
    {
        $translatedInjury = $this->translateInjuryType($injuryType);

        return $this->create(
            game: $game,
            type: GameNotification::TYPE_PLAYER_INJURED,
            title: __('notifications.player_injured_title', ['player' => $player->name]),
            message: __('notifications.player_injured_message', [
                'player' => $player->name,
                'injury' => $translatedInjury,
                'weeks' => $weeksOut,
            ]),
            priority: GameNotification::PRIORITY_CRITICAL,
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
    public function notifySuspension(Game $game, GamePlayer $player, int $matches, string $reason): GameNotification
    {
        return $this->create(
            game: $game,
            type: GameNotification::TYPE_PLAYER_SUSPENDED,
            title: __('notifications.player_suspended_title', ['player' => $player->name]),
            message: __('notifications.player_suspended_message', [
                'player' => $player->name,
                'matches' => $matches,
                'reason' => $reason,
            ]),
            priority: GameNotification::PRIORITY_CRITICAL,
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
            title: __('notifications.transfer_offer_title', ['team' => $offer->offeringTeam->name]),
            message: __('notifications.transfer_offer_message', [
                'team' => $offer->offeringTeam->name,
                'player' => $player->name,
                'fee' => $fee,
            ]),
            priority: GameNotification::PRIORITY_INFO,
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
            message: __('notifications.offer_expiring_message', [
                'team' => $offer->offeringTeam->name,
                'player' => $player->name,
                'days' => $offer->days_until_expiry,
            ]),
            priority: GameNotification::PRIORITY_WARNING,
            metadata: [
                'offer_id' => $offer->id,
                'player_id' => $player->id,
                'days_left' => $offer->days_until_expiry,
            ],
        );
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
    public function notifyLoanReturn(Game $game, GamePlayer $player, string $fromTeam): GameNotification
    {
        return $this->create(
            game: $game,
            type: GameNotification::TYPE_LOAN_RETURN,
            title: __('notifications.loan_return_title', ['player' => $player->name]),
            message: __('notifications.loan_return_message', [
                'player' => $player->name,
                'team' => $fromTeam,
            ]),
            priority: GameNotification::PRIORITY_INFO,
            metadata: [
                'player_id' => $player->id,
                'from_team' => $fromTeam,
            ],
        );
    }

    /**
     * Create a loan destination found notification.
     */
    public function notifyLoanDestinationFound(Game $game, GamePlayer $player, Team $destination, bool $windowOpen): GameNotification
    {
        $message = $windowOpen
            ? __('notifications.loan_destination_found_message', ['player' => $player->name, 'team' => $destination->name])
            : __('notifications.loan_destination_found_waiting', ['player' => $player->name, 'team' => $destination->name]);

        return $this->create(
            game: $game,
            type: GameNotification::TYPE_LOAN_DESTINATION_FOUND,
            title: __('notifications.loan_destination_found_title', ['player' => $player->name]),
            message: $message,
            priority: GameNotification::PRIORITY_INFO,
            metadata: [
                'player_id' => $player->id,
                'team_id' => $destination->id,
                'team_name' => $destination->name,
                'window_open' => $windowOpen,
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
            title: __('notifications.competition_advancement_title', ['competition' => $competitionName]),
            message: __('notifications.competition_advancement_message', ['stage' => $nextStage]),
            priority: GameNotification::PRIORITY_MILESTONE,
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
            title: __('notifications.competition_elimination_title', ['competition' => $competitionName]),
            message: __('notifications.competition_elimination_message', ['stage' => $stage]),
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
    public function notifyAcademyProspect(Game $game, AcademyPlayer $prospect): GameNotification
    {
        return $this->create(
            game: $game,
            type: GameNotification::TYPE_ACADEMY_PROSPECT,
            title: __('notifications.academy_prospect_title', ['player' => $prospect->name]),
            message: __('notifications.academy_prospect_message', [
                'player' => $prospect->name,
                'position' => $prospect->position,
                'age' => $prospect->age,
            ]),
            priority: GameNotification::PRIORITY_INFO,
            metadata: [
                'academy_player_id' => $prospect->id,
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
            GameNotification::TYPE_LOAN_DESTINATION_FOUND => 'loan',
            GameNotification::TYPE_LOAN_SEARCH_FAILED => 'loan',
            GameNotification::TYPE_COMPETITION_ADVANCEMENT => 'trophy',
            GameNotification::TYPE_COMPETITION_ELIMINATION => 'eliminated',
            GameNotification::TYPE_ACADEMY_PROSPECT => 'academy',
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
     */
    public function hasRecentNotification(string $gameId, string $type, array $metadata, int $days = 1): bool
    {
        $game = Game::find($gameId);
        $cutoff = $game->current_date->subDays($days);

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
