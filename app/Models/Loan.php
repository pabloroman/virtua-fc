<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $game_id
 * @property string $game_player_id
 * @property string $parent_team_id
 * @property string $loan_team_id
 * @property \Illuminate\Support\Carbon $started_at
 * @property \Illuminate\Support\Carbon $return_at
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Game $game
 * @property-read \App\Models\GamePlayer $gamePlayer
 * @property-read \App\Models\Team $loanTeam
 * @property-read \App\Models\Team $parentTeam
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Loan active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Loan newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Loan newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Loan query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Loan whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Loan whereGameId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Loan whereGamePlayerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Loan whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Loan whereLoanTeamId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Loan whereParentTeamId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Loan whereReturnAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Loan whereStartedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Loan whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Loan whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Loan extends Model
{
    use HasUuids;

    protected $fillable = [
        'game_id',
        'game_player_id',
        'parent_team_id',
        'loan_team_id',
        'started_at',
        'return_at',
        'status',
    ];

    protected $casts = [
        'started_at' => 'date',
        'return_at' => 'date',
    ];

    public const STATUS_ACTIVE = 'active';
    public const STATUS_COMPLETED = 'completed';

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function gamePlayer(): BelongsTo
    {
        return $this->belongsTo(GamePlayer::class);
    }

    public function parentTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'parent_team_id');
    }

    public function loanTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'loan_team_id');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }
}
