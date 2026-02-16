<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $game_id
 * @property string $status
 * @property array<array-key, mixed> $filters
 * @property int $weeks_total
 * @property int $weeks_remaining
 * @property array<array-key, mixed>|null $player_ids
 * @property \Illuminate\Support\Carbon $game_date
 * @property-read \App\Models\Game $game
 * @property-read mixed $players
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScoutReport newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScoutReport newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScoutReport query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScoutReport whereFilters($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScoutReport whereGameDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScoutReport whereGameId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScoutReport whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScoutReport wherePlayerIds($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScoutReport whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScoutReport whereWeeksRemaining($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScoutReport whereWeeksTotal($value)
 * @mixin \Eloquent
 */
class ScoutReport extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'game_id',
        'status',
        'filters',
        'weeks_total',
        'weeks_remaining',
        'player_ids',
        'game_date',
    ];

    protected $casts = [
        'filters' => 'array',
        'player_ids' => 'array',
        'weeks_total' => 'integer',
        'weeks_remaining' => 'integer',
        'game_date' => 'date',
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
     * Excludes players who have since joined the user's team.
     */
    public function getPlayersAttribute()
    {
        if (!$this->isCompleted() || empty($this->player_ids)) {
            return collect();
        }

        return GamePlayer::with(['player', 'team'])
            ->whereIn('id', $this->player_ids)
            ->where('team_id', '!=', $this->game->team_id) // Exclude players now on user's team
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
