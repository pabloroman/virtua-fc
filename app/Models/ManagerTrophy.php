<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $game_id
 * @property string $team_id
 * @property string $competition_id
 * @property string $season
 * @property string $trophy_type
 * @property-read \App\Models\User $user
 * @property-read \App\Models\Game $game
 * @property-read \App\Models\Team $team
 * @property-read \App\Models\Competition $competition
 */
class ManagerTrophy extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'game_id',
        'team_id',
        'competition_id',
        'season',
        'trophy_type',
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

    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }
}
