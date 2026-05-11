<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property int $user_id
 * @property string|null $game_id
 * @property string $team_id
 * @property string|null $competition_id
 * @property string|null $season
 * @property string $offer_type
 * @property string $status
 * @property string|null $source_reputation_level
 * @property string $target_reputation_level
 * @property \Illuminate\Support\Carbon|null $created_on_game_date
 * @property-read \App\Models\User $user
 * @property-read \App\Models\Game|null $game
 * @property-read \App\Models\Team $team
 * @property-read \App\Models\Competition|null $competition
 */
class ManagerJobOffer extends Model
{
    use HasUuids;

    public $timestamps = false;

    // Offer types
    public const TYPE_INITIAL = 'initial';
    public const TYPE_END_OF_SEASON = 'end_of_season';
    public const TYPE_POST_FIRING = 'post_firing';

    // Statuses
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'user_id',
        'game_id',
        'team_id',
        'competition_id',
        'season',
        'offer_type',
        'status',
        'source_reputation_level',
        'target_reputation_level',
        'created_on_game_date',
    ];

    protected $casts = [
        'created_on_game_date' => 'date',
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

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
