<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameStanding extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'position' => 'integer',
        'prev_position' => 'integer',
        'played' => 'integer',
        'won' => 'integer',
        'drawn' => 'integer',
        'lost' => 'integer',
        'goals_for' => 'integer',
        'goals_against' => 'integer',
        'points' => 'integer',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function getGoalDifferenceAttribute(): int
    {
        return $this->goals_for - $this->goals_against;
    }

    public function getPositionChangeAttribute(): int
    {
        if ($this->prev_position === null) {
            return 0;
        }
        return $this->prev_position - $this->position;
    }

    public function getPositionChangeIconAttribute(): string
    {
        $change = $this->position_change;
        if ($change > 0) {
            return '▲';
        }
        if ($change < 0) {
            return '▼';
        }
        return '–';
    }
}
