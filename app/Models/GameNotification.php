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
    public const TYPE_RENEWAL_ACCEPTED = 'renewal_accepted';
    public const TYPE_RENEWAL_COUNTERED = 'renewal_countered';
    public const TYPE_RENEWAL_REJECTED = 'renewal_rejected';
    public const TYPE_TRANSFER_BID_RESULT = 'transfer_bid_result';
    public const TYPE_LOAN_REQUEST_RESULT = 'loan_request_result';
    public const TYPE_TOURNAMENT_WELCOME = 'tournament_welcome';

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
        self::TYPE_RENEWAL_ACCEPTED => 'transfers',
        self::TYPE_RENEWAL_COUNTERED => 'transfers',
        self::TYPE_RENEWAL_REJECTED => 'transfers',
        self::TYPE_TRANSFER_BID_RESULT => 'scouting',
        self::TYPE_LOAN_REQUEST_RESULT => 'scouting',
        self::TYPE_TOURNAMENT_WELCOME => 'competition',
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
                'bg' => 'bg-red-50',
                'border' => 'border-red-200',
                'text' => 'text-red-800',
                'icon_bg' => 'bg-red-500',
                'icon_text' => 'text-white',
            ],
            self::TYPE_PLAYER_SUSPENDED => [
                'bg' => 'bg-orange-50',
                'border' => 'border-orange-200',
                'text' => 'text-orange-800',
                'icon_bg' => 'bg-orange-500',
                'icon_text' => 'text-white',
            ],
            self::TYPE_PLAYER_RECOVERED => [
                'bg' => 'bg-green-50',
                'border' => 'border-green-200',
                'text' => 'text-green-800',
                'icon_bg' => 'bg-green-500',
                'icon_text' => 'text-white',
            ],
            self::TYPE_LOW_FITNESS => [
                'bg' => 'bg-amber-50',
                'border' => 'border-amber-200',
                'text' => 'text-amber-800',
                'icon_bg' => 'bg-amber-500',
                'icon_text' => 'text-white',
            ],
            self::TYPE_TRANSFER_OFFER_RECEIVED => [
                'bg' => 'bg-blue-50',
                'border' => 'border-blue-200',
                'text' => 'text-blue-800',
                'icon_bg' => 'bg-blue-500',
                'icon_text' => 'text-white',
            ],
            self::TYPE_TRANSFER_OFFER_EXPIRING => [
                'bg' => 'bg-indigo-50',
                'border' => 'border-indigo-200',
                'text' => 'text-indigo-800',
                'icon_bg' => 'bg-indigo-500',
                'icon_text' => 'text-white',
            ],
            self::TYPE_TRANSFER_COMPLETE => [
                'bg' => 'bg-sky-50',
                'border' => 'border-sky-200',
                'text' => 'text-sky-800',
                'icon_bg' => 'bg-sky-500',
                'icon_text' => 'text-white',
            ],
            self::TYPE_SCOUT_REPORT_COMPLETE => [
                'bg' => 'bg-teal-50',
                'border' => 'border-teal-200',
                'text' => 'text-teal-800',
                'icon_bg' => 'bg-teal-500',
                'icon_text' => 'text-white',
            ],
            self::TYPE_CONTRACT_EXPIRING => [
                'bg' => 'bg-zinc-100',
                'border' => 'border-zinc-300',
                'text' => 'text-zinc-800',
                'icon_bg' => 'bg-zinc-500',
                'icon_text' => 'text-white',
            ],
            self::TYPE_LOAN_RETURN => [
                'bg' => 'bg-violet-50',
                'border' => 'border-violet-200',
                'text' => 'text-violet-800',
                'icon_bg' => 'bg-violet-500',
                'icon_text' => 'text-white',
            ],
            self::TYPE_LOAN_DESTINATION_FOUND => [
                'bg' => 'bg-purple-50',
                'border' => 'border-purple-200',
                'text' => 'text-purple-800',
                'icon_bg' => 'bg-purple-500',
                'icon_text' => 'text-white',
            ],
            self::TYPE_LOAN_SEARCH_FAILED => [
                'bg' => 'bg-slate-100',
                'border' => 'border-slate-300',
                'text' => 'text-slate-800',
                'icon_bg' => 'bg-slate-500',
                'icon_text' => 'text-white',
            ],
            self::TYPE_COMPETITION_ADVANCEMENT => [
                'bg' => 'bg-emerald-50',
                'border' => 'border-emerald-200',
                'text' => 'text-emerald-800',
                'icon_bg' => 'bg-emerald-500',
                'icon_text' => 'text-white',
            ],
            self::TYPE_COMPETITION_ELIMINATION => [
                'bg' => 'bg-rose-50',
                'border' => 'border-rose-200',
                'text' => 'text-rose-800',
                'icon_bg' => 'bg-rose-500',
                'icon_text' => 'text-white',
            ],
            self::TYPE_ACADEMY_PROSPECT, self::TYPE_ACADEMY_BATCH => [
                'bg' => 'bg-lime-50',
                'border' => 'border-lime-200',
                'text' => 'text-lime-800',
                'icon_bg' => 'bg-lime-600',
                'icon_text' => 'text-white',
            ],
            self::TYPE_ACADEMY_EVALUATION => [
                'bg' => 'bg-amber-50',
                'border' => 'border-amber-200',
                'text' => 'text-amber-800',
                'icon_bg' => 'bg-amber-500',
                'icon_text' => 'text-white',
            ],
            self::TYPE_TRANSFER_BID_RESULT => [
                'bg' => 'bg-blue-50',
                'border' => 'border-blue-200',
                'text' => 'text-blue-800',
                'icon_bg' => 'bg-blue-500',
                'icon_text' => 'text-white',
            ],
            self::TYPE_LOAN_REQUEST_RESULT => [
                'bg' => 'bg-purple-50',
                'border' => 'border-purple-200',
                'text' => 'text-purple-800',
                'icon_bg' => 'bg-purple-500',
                'icon_text' => 'text-white',
            ],
            self::TYPE_TOURNAMENT_WELCOME => [
                'bg' => 'bg-yellow-50',
                'border' => 'border-yellow-300',
                'text' => 'text-yellow-900',
                'icon_bg' => 'bg-yellow-500',
                'icon_text' => 'text-white',
            ],
            default => [
                'bg' => 'bg-slate-50',
                'border' => 'border-slate-200',
                'text' => 'text-slate-800',
                'icon_bg' => 'bg-slate-500',
                'icon_text' => 'text-white',
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
