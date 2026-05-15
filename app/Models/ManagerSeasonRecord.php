<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $game_id
 * @property int $user_id
 * @property string $team_id
 * @property string $competition_id
 * @property string $season
 * @property string|null $season_goal
 * @property string|null $season_goal_label
 * @property int|null $final_position
 * @property bool|null $goal_achieved
 * @property string|null $goal_grade
 * @property string|null $end_reason
 * @property \Illuminate\Support\Carbon|null $recorded_at
 * @property-read \App\Models\Game $game
 * @property-read \App\Models\User $user
 * @property-read \App\Models\Team $team
 * @property-read \App\Models\Competition $competition
 */
class ManagerSeasonRecord extends Model
{
    use HasUuids;

    public $timestamps = false;

    public const END_REASON_STILL_ACTIVE = 'still_active';
    public const END_REASON_LEFT_VOLUNTARILY = 'left_voluntarily';
    public const END_REASON_FIRED = 'fired';

    protected $fillable = [
        'game_id',
        'user_id',
        'team_id',
        'competition_id',
        'season',
        'season_goal',
        'season_goal_label',
        'final_position',
        'goal_achieved',
        'goal_grade',
        'end_reason',
        'recorded_at',
    ];

    protected $casts = [
        'final_position' => 'integer',
        'goal_achieved' => 'boolean',
        'recorded_at' => 'datetime',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }
}
