<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $game_id
 * @property string $competition_id
 * @property string $team_id
 * @property int $position
 * @property int|null $prev_position
 * @property int $played
 * @property int $won
 * @property int $drawn
 * @property int $lost
 * @property int $goals_for
 * @property int $goals_against
 * @property int $points
 * @property-read \App\Models\Competition $competition
 * @property-read \App\Models\Game $game
 * @property-read int $goal_difference
 * @property-read int $position_change
 * @property-read string $position_change_icon
 * @property-read \App\Models\Team $team
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameStanding newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameStanding newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameStanding query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameStanding whereCompetitionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameStanding whereDrawn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameStanding whereGameId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameStanding whereGoalsAgainst($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameStanding whereGoalsFor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameStanding whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameStanding whereLost($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameStanding wherePlayed($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameStanding wherePoints($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameStanding wherePosition($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameStanding wherePrevPosition($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameStanding whereTeamId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameStanding whereWon($value)
 * @mixin \Eloquent
 */
class GameStanding extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'game_id',
        'competition_id',
        'group_label',
        'team_id',
        'position',
        'prev_position',
        'played',
        'won',
        'drawn',
        'lost',
        'goals_for',
        'goals_against',
        'points',
    ];

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
