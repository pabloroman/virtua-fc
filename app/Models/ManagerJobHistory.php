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
 * @property string $season_start
 * @property string|null $season_end
 * @property string $end_reason
 * @property-read \App\Models\Game $game
 * @property-read \App\Models\User $user
 * @property-read \App\Models\Team $team
 * @property-read \App\Models\Competition $competition
 */
class ManagerJobHistory extends Model
{
    use HasUuids;

    public $timestamps = false;

    public const REASON_STILL_ACTIVE = 'still_active';
    public const REASON_LEFT_VOLUNTARILY = 'left_voluntarily';
    public const REASON_FIRED = 'fired';

    protected $fillable = [
        'game_id',
        'user_id',
        'team_id',
        'competition_id',
        'season_start',
        'season_end',
        'end_reason',
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
