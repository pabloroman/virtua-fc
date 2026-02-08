<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameNotification extends Model
{
    use HasUuids;

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

    // Priorities
    public const PRIORITY_CRITICAL = 'critical';
    public const PRIORITY_WARNING = 'warning';
    public const PRIORITY_INFO = 'info';

    // Navigation targets
    private const NAVIGATION_MAP = [
        self::TYPE_PLAYER_INJURED => 'squad',
        self::TYPE_PLAYER_SUSPENDED => 'squad',
        self::TYPE_PLAYER_RECOVERED => 'squad',
        self::TYPE_LOW_FITNESS => 'squad',
        self::TYPE_TRANSFER_OFFER_RECEIVED => 'transfers',
        self::TYPE_TRANSFER_OFFER_EXPIRING => 'transfers',
        self::TYPE_SCOUT_REPORT_COMPLETE => 'scouting',
        self::TYPE_CONTRACT_EXPIRING => 'contracts',
        self::TYPE_LOAN_RETURN => 'squad',
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
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
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

    public function scopeRecent(Builder $query, int $limit = 10): Builder
    {
        return $query->orderByDesc('created_at')->limit($limit);
    }

    public function scopeOlderThan(Builder $query, int $days): Builder
    {
        return $query->where('created_at', '<', now()->subDays($days));
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
        $target = self::NAVIGATION_MAP[$this->type] ?? 'squad';

        return match ($target) {
            'squad' => 'game.squad',
            'transfers' => 'game.transfers',
            'scouting' => 'game.scouting',
            'contracts' => 'game.squad.contracts',
            default => 'show-game',
        };
    }

    /**
     * Get the CSS classes for priority-based styling.
     */
    public function getPriorityClasses(): array
    {
        return match ($this->priority) {
            self::PRIORITY_CRITICAL => [
                'bg' => 'bg-red-50',
                'border' => 'border-red-200',
                'text' => 'text-red-700',
                'icon' => 'text-red-600',
                'dot' => 'bg-red-500',
            ],
            self::PRIORITY_WARNING => [
                'bg' => 'bg-amber-50',
                'border' => 'border-amber-200',
                'text' => 'text-amber-700',
                'icon' => 'text-amber-600',
                'dot' => 'bg-amber-500',
            ],
            default => [
                'bg' => 'bg-sky-50',
                'border' => 'border-sky-200',
                'text' => 'text-sky-700',
                'icon' => 'text-sky-600',
                'dot' => 'bg-sky-500',
            ],
        };
    }

    /**
     * Get formatted game date for display.
     */
    public function getFormattedGameDate(): string
    {
        if ($this->game_date) {
            return $this->game_date->format('j M Y');
        }

        return $this->created_at->format('j M Y');
    }
}
