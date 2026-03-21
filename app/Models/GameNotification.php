<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $game_id
 * @property string $type
 * @property string $title
 * @property string|null $message
 * @property string|null $icon
 * @property string $priority
 * @property array<array-key, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon|null $read_at
 * @property \Illuminate\Support\Carbon|null $game_date
 * @property-read \App\Models\Game $game
 * @method static Builder<static>|GameNotification byPriority(string $priority)
 * @method static Builder<static>|GameNotification newModelQuery()
 * @method static Builder<static>|GameNotification newQuery()
 * @method static Builder<static>|GameNotification ofType(string $type)
 * @method static Builder<static>|GameNotification query()
 * @method static Builder<static>|GameNotification read()
 * @method static Builder<static>|GameNotification unread()
 * @method static Builder<static>|GameNotification whereGameDate($value)
 * @method static Builder<static>|GameNotification whereGameId($value)
 * @method static Builder<static>|GameNotification whereIcon($value)
 * @method static Builder<static>|GameNotification whereId($value)
 * @method static Builder<static>|GameNotification whereMessage($value)
 * @method static Builder<static>|GameNotification whereMetadata($value)
 * @method static Builder<static>|GameNotification wherePriority($value)
 * @method static Builder<static>|GameNotification whereReadAt($value)
 * @method static Builder<static>|GameNotification whereTitle($value)
 * @method static Builder<static>|GameNotification whereType($value)
 * @mixin \Eloquent
 */
class GameNotification extends Model
{
    use HasUuids;

    public $timestamps = false;

    // Notification types
    public const TYPE_PLAYER_INJURED = 'player_injured';
    public const TYPE_PLAYER_SUSPENDED = 'player_suspended';
    public const TYPE_PLAYER_RECOVERED = 'player_recovered';
    public const TYPE_LOW_FITNESS = 'low_fitness';
    public const TYPE_TRANSFER_OFFER_RECEIVED = 'transfer_offer_received';
    public const TYPE_TRANSFER_OFFER_EXPIRING = 'transfer_offer_expiring';
    public const TYPE_SCOUT_REPORT_COMPLETE = 'scout_report_complete';
    public const TYPE_CONTRACT_EXPIRING = 'contract_expiring';
    public const TYPE_LOAN_RETURN = 'loan_return';
    public const TYPE_LOAN_DESTINATION_FOUND = 'loan_destination_found';
    public const TYPE_LOAN_SEARCH_FAILED = 'loan_search_failed';
    public const TYPE_COMPETITION_ADVANCEMENT = 'competition_advancement';
    public const TYPE_COMPETITION_ELIMINATION = 'competition_elimination';
    public const TYPE_ACADEMY_PROSPECT = 'academy_prospect';
    public const TYPE_ACADEMY_EVALUATION = 'academy_evaluation';
    public const TYPE_ACADEMY_BATCH = 'academy_batch';
    public const TYPE_TRANSFER_COMPLETE = 'transfer_complete';
    public const TYPE_TRANSFER_BID_RESULT = 'transfer_bid_result';
    public const TYPE_LOAN_REQUEST_RESULT = 'loan_request_result';
    public const TYPE_TOURNAMENT_WELCOME = 'tournament_welcome';
    public const TYPE_AI_TRANSFER_ACTIVITY = 'ai_transfer_activity';
    public const TYPE_TRANSFER_WINDOW_OPEN = 'transfer_window_open';
    public const TYPE_PLAYER_RELEASED = 'player_released';
    public const TYPE_TRACKING_INTEL_READY = 'tracking_intel_ready';
    public const TYPE_EMERGENCY_SIGNING = 'emergency_signing';
    public const TYPE_MATCH_FORFEIT = 'match_forfeit';

    // Priorities
    public const PRIORITY_MILESTONE = 'milestone';
    public const PRIORITY_CRITICAL = 'critical';
    public const PRIORITY_WARNING = 'warning';
    public const PRIORITY_INFO = 'info';

    // Navigation targets
    private const NAVIGATION_MAP = [
        self::TYPE_PLAYER_INJURED => 'lineup',
        self::TYPE_PLAYER_SUSPENDED => 'lineup',
        self::TYPE_PLAYER_RECOVERED => 'lineup',
        self::TYPE_LOW_FITNESS => 'lineup',
        self::TYPE_TRANSFER_OFFER_RECEIVED => 'transfers',
        self::TYPE_TRANSFER_OFFER_EXPIRING => 'transfers',
        self::TYPE_SCOUT_REPORT_COMPLETE => 'scouting',
        self::TYPE_CONTRACT_EXPIRING => 'transfers',
        self::TYPE_LOAN_RETURN => 'squad',
        self::TYPE_LOAN_DESTINATION_FOUND => 'transfers',
        self::TYPE_LOAN_SEARCH_FAILED => 'transfers',
        self::TYPE_COMPETITION_ADVANCEMENT => 'competition',
        self::TYPE_COMPETITION_ELIMINATION => 'competition',
        self::TYPE_ACADEMY_PROSPECT => 'academy',
        self::TYPE_ACADEMY_EVALUATION => 'academy',
        self::TYPE_ACADEMY_BATCH => 'academy',
        self::TYPE_TRANSFER_COMPLETE => 'squad',
        self::TYPE_TRANSFER_BID_RESULT => 'scouting',
        self::TYPE_LOAN_REQUEST_RESULT => 'scouting',
        self::TYPE_TOURNAMENT_WELCOME => 'competition',
        self::TYPE_AI_TRANSFER_ACTIVITY => 'transfer-activity',
        self::TYPE_TRANSFER_WINDOW_OPEN => 'scouting',
        self::TYPE_PLAYER_RELEASED => 'squad',
        self::TYPE_TRACKING_INTEL_READY => 'scouting',
        self::TYPE_EMERGENCY_SIGNING => 'squad',
        self::TYPE_MATCH_FORFEIT => 'squad',
    ];

    protected $fillable = [
        'id',
        'game_id',
        'type',
        'title',
        'message',
        'icon',
        'priority',
        'metadata',
        'game_date',
        'read_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'game_date' => 'date',
        'read_at' => 'datetime',
    ];

    // ==========================================
    // Relationships
    // ==========================================

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /**
     * Get the associated game player if referenced in metadata.
     */
    public function gamePlayer(): ?GamePlayer
    {
        $playerId = $this->metadata['player_id'] ?? null;

        if (!$playerId) {
            return null;
        }

        return GamePlayer::find($playerId);
    }

    // ==========================================
    // Scopes
    // ==========================================

    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    public function scopeRead(Builder $query): Builder
    {
        return $query->whereNotNull('read_at');
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeByPriority(Builder $query, string $priority): Builder
    {
        return $query->where('priority', $priority);
    }

    // ==========================================
    // Methods
    // ==========================================

    public function markAsRead(): void
    {
        if (!$this->read_at) {
            $this->update(['read_at' => now()]);
        }
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    public function isUnread(): bool
    {
        return $this->read_at === null;
    }

    public function isCritical(): bool
    {
        return $this->priority === self::PRIORITY_CRITICAL;
    }

    public function isWarning(): bool
    {
        return $this->priority === self::PRIORITY_WARNING;
    }

    /**
     * Get the route name for navigation based on notification type.
     */
    public function getNavigationRoute(): string
    {
        // Accepted/counter bid and loan results navigate to incoming transfers tab
        if (in_array($this->type, [self::TYPE_TRANSFER_BID_RESULT, self::TYPE_LOAN_REQUEST_RESULT])) {
            $result = $this->metadata['result'] ?? null;
            return ($result === 'rejected') ? 'game.scouting' : 'game.transfers';
        }

        $target = self::NAVIGATION_MAP[$this->type] ?? 'squad';

        return match ($target) {
            'lineup' => 'game.lineup',
            'squad' => 'game.squad',
            'transfers' => 'game.transfers.outgoing',
            'scouting' => 'game.scouting',
            'competition' => 'game.competition',
            'academy' => 'game.squad.academy',
            'transfer-activity' => 'game.transfer-activity',
            default => 'game.squad.academy',
        };
    }

    /**
     * Get the route parameters for navigation.
     */
    public function getNavigationParams(string $gameId): array
    {
        $params = ['gameId' => $gameId];

        if (($this->metadata['competition_id'] ?? null) && $this->getNavigationRoute() === 'game.competition') {
            $params['competitionId'] = $this->metadata['competition_id'];
        }

        return $params;
    }

    /**
     * Get the CSS classes for type-based styling.
     * Each notification type has its own unique color identity.
     */
    public function getTypeClasses(): array
    {
        return match ($this->type) {
            self::TYPE_PLAYER_INJURED => [
                'icon_bg' => 'bg-red-500/10',
                'icon_text' => 'text-red-500',
                'dot' => 'bg-red-500',
            ],
            self::TYPE_PLAYER_SUSPENDED => [
                'icon_bg' => 'bg-orange-500/10',
                'icon_text' => 'text-orange-500',
                'dot' => 'bg-orange-500',
            ],
            self::TYPE_PLAYER_RECOVERED => [
                'icon_bg' => 'bg-emerald-500/10',
                'icon_text' => 'text-emerald-500',
                'dot' => 'bg-emerald-500',
            ],
            self::TYPE_LOW_FITNESS => [
                'icon_bg' => 'bg-amber-500/10',
                'icon_text' => 'text-amber-500',
                'dot' => 'bg-amber-500',
            ],
            self::TYPE_TRANSFER_OFFER_RECEIVED => [
                'icon_bg' => 'bg-blue-500/10',
                'icon_text' => 'text-blue-500',
                'dot' => 'bg-blue-500',
            ],
            self::TYPE_TRANSFER_OFFER_EXPIRING => [
                'icon_bg' => 'bg-indigo-500/10',
                'icon_text' => 'text-indigo-500',
                'dot' => 'bg-indigo-500',
            ],
            self::TYPE_TRANSFER_COMPLETE => [
                'icon_bg' => 'bg-sky-500/10',
                'icon_text' => 'text-sky-500',
                'dot' => 'bg-sky-500',
            ],
            self::TYPE_SCOUT_REPORT_COMPLETE => [
                'icon_bg' => 'bg-teal-500/10',
                'icon_text' => 'text-teal-500',
                'dot' => 'bg-teal-500',
            ],
            self::TYPE_CONTRACT_EXPIRING => [
                'icon_bg' => 'bg-zinc-500/10',
                'icon_text' => 'text-zinc-400',
                'dot' => 'bg-zinc-400',
            ],
            self::TYPE_LOAN_RETURN => [
                'icon_bg' => 'bg-violet-500/10',
                'icon_text' => 'text-violet-500',
                'dot' => 'bg-violet-500',
            ],
            self::TYPE_LOAN_DESTINATION_FOUND => [
                'icon_bg' => 'bg-purple-500/10',
                'icon_text' => 'text-purple-500',
                'dot' => 'bg-purple-500',
            ],
            self::TYPE_LOAN_SEARCH_FAILED => [
                'icon_bg' => 'bg-slate-500/10',
                'icon_text' => 'text-slate-400',
                'dot' => 'bg-slate-400',
            ],
            self::TYPE_COMPETITION_ADVANCEMENT => [
                'icon_bg' => 'bg-emerald-500/10',
                'icon_text' => 'text-emerald-500',
                'dot' => 'bg-emerald-500',
            ],
            self::TYPE_COMPETITION_ELIMINATION => [
                'icon_bg' => 'bg-rose-500/10',
                'icon_text' => 'text-rose-500',
                'dot' => 'bg-rose-500',
            ],
            self::TYPE_ACADEMY_PROSPECT, self::TYPE_ACADEMY_BATCH => [
                'icon_bg' => 'bg-lime-500/10',
                'icon_text' => 'text-lime-500',
                'dot' => 'bg-lime-500',
            ],
            self::TYPE_ACADEMY_EVALUATION => [
                'icon_bg' => 'bg-amber-500/10',
                'icon_text' => 'text-amber-500',
                'dot' => 'bg-amber-500',
            ],
            self::TYPE_TRANSFER_BID_RESULT => [
                'icon_bg' => 'bg-blue-500/10',
                'icon_text' => 'text-blue-500',
                'dot' => 'bg-blue-500',
            ],
            self::TYPE_LOAN_REQUEST_RESULT => [
                'icon_bg' => 'bg-purple-500/10',
                'icon_text' => 'text-purple-500',
                'dot' => 'bg-purple-500',
            ],
            self::TYPE_TOURNAMENT_WELCOME => [
                'icon_bg' => 'bg-yellow-500/10',
                'icon_text' => 'text-yellow-500',
                'dot' => 'bg-yellow-500',
            ],
            self::TYPE_AI_TRANSFER_ACTIVITY => [
                'icon_bg' => 'bg-cyan-500/10',
                'icon_text' => 'text-cyan-500',
                'dot' => 'bg-cyan-500',
            ],
            self::TYPE_TRANSFER_WINDOW_OPEN => [
                'icon_bg' => 'bg-emerald-500/10',
                'icon_text' => 'text-emerald-500',
                'dot' => 'bg-emerald-500',
            ],
            self::TYPE_PLAYER_RELEASED => [
                'icon_bg' => 'bg-red-500/10',
                'icon_text' => 'text-red-500',
                'dot' => 'bg-red-500',
            ],
            self::TYPE_TRACKING_INTEL_READY => [
                'icon_bg' => 'bg-teal-500/10',
                'icon_text' => 'text-teal-500',
                'dot' => 'bg-teal-500',
            ],
            self::TYPE_EMERGENCY_SIGNING => [
                'icon_bg' => 'bg-orange-500/10',
                'icon_text' => 'text-orange-500',
                'dot' => 'bg-orange-500',
            ],
            self::TYPE_MATCH_FORFEIT => [
                'icon_bg' => 'bg-red-500/10',
                'icon_text' => 'text-red-500',
                'dot' => 'bg-red-500',
            ],
            default => [
                'icon_bg' => 'bg-slate-500/10',
                'icon_text' => 'text-slate-400',
                'dot' => 'bg-slate-400',
            ],
        };
    }

    /**
     * Get priority badge config for secondary urgency indicator.
     * Returns null for INFO and MILESTONE (no urgency badge needed).
     */
    public function getPriorityBadge(): ?array
    {
        return match ($this->priority) {
            self::PRIORITY_CRITICAL => [
                'bg' => 'bg-red-600',
                'text' => 'text-white',
                'label' => __('notifications.priority_urgent'),
            ],
            self::PRIORITY_WARNING => [
                'bg' => 'bg-amber-500',
                'text' => 'text-white',
                'label' => __('notifications.priority_attention'),
            ],
            default => null,
        };
    }

}
