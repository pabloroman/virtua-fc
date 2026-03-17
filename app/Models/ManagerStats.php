<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string|null $game_id
 * @property string|null $team_id
 * @property int $matches_played
 * @property int $matches_won
 * @property int $matches_drawn
 * @property int $matches_lost
 * @property float $win_percentage
 * @property int $current_unbeaten_streak
 * @property int $longest_unbeaten_streak
 * @property int $seasons_completed
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $user
 * @property-read \App\Models\Game|null $game
 * @property-read \App\Models\Team|null $team
 */
class ManagerStats extends Model
{
    public const UPDATED_AT = 'updated_at';
    public const CREATED_AT = null;

    protected $fillable = [
        'user_id',
        'game_id',
        'team_id',
        'matches_played',
        'matches_won',
        'matches_drawn',
        'matches_lost',
        'win_percentage',
        'current_unbeaten_streak',
        'longest_unbeaten_streak',
        'seasons_completed',
    ];

    protected $casts = [
        'matches_played' => 'integer',
        'matches_won' => 'integer',
        'matches_drawn' => 'integer',
        'matches_lost' => 'integer',
        'win_percentage' => 'decimal:2',
        'current_unbeaten_streak' => 'integer',
        'longest_unbeaten_streak' => 'integer',
        'seasons_completed' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Recalculate win percentage from current W/D/L counts.
     */
    public function recalculateWinPercentage(): void
    {
        $this->win_percentage = $this->matches_played > 0
            ? round(($this->matches_won / $this->matches_played) * 100, 2)
            : 0;
    }

    /**
     * Record a match result and update all derived stats.
     */
    public function recordResult(string $result): void
    {
        $this->matches_played++;

        match ($result) {
            'win' => $this->matches_won++,
            'draw' => $this->matches_drawn++,
            'loss' => $this->matches_lost++,
        };

        $this->recalculateWinPercentage();

        if ($result === 'loss') {
            $this->current_unbeaten_streak = 0;
        } else {
            $this->current_unbeaten_streak++;
            if ($this->current_unbeaten_streak > $this->longest_unbeaten_streak) {
                $this->longest_unbeaten_streak = $this->current_unbeaten_streak;
            }
        }

        $this->save();
    }
}
