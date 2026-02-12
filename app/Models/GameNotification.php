<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
    public const TYPE_TRANSFER_COMPLETE = 'transfer_complete';

    // Priorities
    public const PRIORITY_MILESTONE = 'milestone';
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
        self::TYPE_LOAN_DESTINATION_FOUND => 'loans',
        self::TYPE_LOAN_SEARCH_FAILED => 'loans',
        self::TYPE_COMPETITION_ADVANCEMENT => 'competition',
        self::TYPE_COMPETITION_ELIMINATION => 'competition',
        self::TYPE_ACADEMY_PROSPECT => 'academy',
        self::TYPE_TRANSFER_COMPLETE => 'squad',
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
        $target = self::NAVIGATION_MAP[$this->type] ?? 'squad';

        return match ($target) {
            'squad' => 'game.squad',
            'transfers' => 'game.transfers',
            'scouting' => 'game.scouting',
            'contracts' => 'game.squad',
            'loans' => 'game.loans',
            'competition' => 'game.competition',
            'academy' => 'game.squad.academy',
            default => 'show-game',
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
     * Get the CSS classes for priority-based styling.
     */
    public function getPriorityClasses(): array
    {
        return match ($this->priority) {
            self::PRIORITY_MILESTONE => [
                'bg' => 'bg-emerald-50',
                'border' => 'border-emerald-200',
                'text' => 'text-emerald-700',
                'icon' => 'text-emerald-600',
                'dot' => 'bg-emerald-500',
            ],
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

}
