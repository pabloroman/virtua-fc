<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A stadium naming-rights deal for the user's club in a game. One table
 * holds the whole lifecycle: pre-season offers (`pending`), the accepted
 * contract (`active`), and the trail of expired/rejected rows (history).
 *
 * Income settles proportional to attendance (see NamingRightsService), so a
 * sponsor pays more for a packed ground than an empty one.
 *
 * @property string $id
 * @property string $game_id
 * @property string $team_id
 * @property string $sponsor_name
 * @property string $proposed_stadium_name
 * @property string|null $previous_stadium_name
 * @property int $annual_value_cents
 * @property int $contract_seasons
 * @property string $status
 * @property int $offered_season
 * @property int|null $start_season
 * @property int|null $end_season
 * @property-read \App\Models\Game $game
 * @property-read \App\Models\Team $team
 */
class GameStadiumNamingDeal extends Model
{
    use HasUuids;

    public $timestamps = false;

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'game_id',
        'team_id',
        'sponsor_name',
        'proposed_stadium_name',
        'previous_stadium_name',
        'annual_value_cents',
        'contract_seasons',
        'status',
        'offered_season',
        'start_season',
        'end_season',
    ];

    protected $casts = [
        'annual_value_cents' => 'integer',
        'contract_seasons' => 'integer',
        'offered_season' => 'integer',
        'start_season' => 'integer',
        'end_season' => 'integer',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * The single active naming-rights deal for a club in a game, if any.
     */
    public static function activeForGame(string $gameId, string $teamId): ?self
    {
        return self::query()
            ->where('game_id', $gameId)
            ->where('team_id', $teamId)
            ->where('status', self::STATUS_ACTIVE)
            ->first();
    }
}
