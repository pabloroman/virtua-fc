<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScoutReport extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'filters' => 'array',
        'player_ids' => 'array',
        'weeks_total' => 'integer',
        'weeks_remaining' => 'integer',
    ];

    public const STATUS_SEARCHING = 'searching';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function isSearching(): bool
    {
        return $this->status === self::STATUS_SEARCHING;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Get the scouted players (only when completed).
     */
    public function getPlayersAttribute()
    {
        if (!$this->isCompleted() || empty($this->player_ids)) {
            return collect();
        }

        return GamePlayer::with(['player', 'team'])
            ->whereIn('id', $this->player_ids)
            ->get();
    }

    /**
     * Tick one week off the search timer.
     * Returns true if the search is now complete.
     */
    public function tickWeek(): bool
    {
        if (!$this->isSearching()) {
            return false;
        }

        $this->decrement('weeks_remaining');
        $this->refresh();

        return $this->weeks_remaining <= 0;
    }
}
