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
 * @property-read \App\Models\Team $team
 * @property-read \App\Models\Competition $competition
 */
class ManagerTrophy extends Model
{
    /**
     * Trophies are cross-game artifacts (one row per (user_id, game_id,
     * competition_id, season)) read aggregated across games on the manager
     * profile and reputation pages. Lives on the control plane alongside
     * users, teams and competitions. The `game_id` column stays as a logical
     * reference so per-season trophy counts can still be scoped to a single
     * career save, but no Eloquent relation walks back to Game (that would
     * be a forbidden control → tenant relation per CLAUDE.md).
     */
    protected $connection = 'pgsql_control';

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

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }
}
