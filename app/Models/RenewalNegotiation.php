<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $game_id
 * @property string $game_player_id
 * @property string $status
 * @property int $round
 * @property int|null $player_demand
 * @property int|null $preferred_years
 * @property int|null $user_offer
 * @property int|null $offered_years
 * @property int|null $counter_offer
 * @property int|null $contract_years
 * @property float|null $disposition
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Game $game
 * @property-read \App\Models\GamePlayer $gamePlayer
 * @property-read string $formatted_counter_offer
 * @property-read string $formatted_player_demand
 * @property-read string $formatted_user_offer
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RenewalNegotiation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RenewalNegotiation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RenewalNegotiation query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RenewalNegotiation whereContractYears($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RenewalNegotiation whereCounterOffer($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RenewalNegotiation whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RenewalNegotiation whereDisposition($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RenewalNegotiation whereGameId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RenewalNegotiation whereGamePlayerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RenewalNegotiation whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RenewalNegotiation whereOfferedYears($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RenewalNegotiation wherePlayerDemand($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RenewalNegotiation wherePreferredYears($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RenewalNegotiation whereRound($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RenewalNegotiation whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RenewalNegotiation whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RenewalNegotiation whereUserOffer($value)
 * @mixin \Eloquent
 */
class RenewalNegotiation extends Model
{
    use HasUuids;

    public const STATUS_OFFER_PENDING = 'offer_pending';
    public const STATUS_PLAYER_COUNTERED = 'player_countered';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_PLAYER_REJECTED = 'player_rejected';
    public const STATUS_CLUB_DECLINED = 'club_declined';
    public const STATUS_CLUB_RECONSIDERED = 'club_reconsidered';
    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'game_id',
        'game_player_id',
        'status',
        'round',
        'player_demand',
        'preferred_years',
        'user_offer',
        'offered_years',
        'counter_offer',
        'contract_years',
        'disposition',
    ];

    protected $casts = [
        'round' => 'integer',
        'player_demand' => 'integer',
        'preferred_years' => 'integer',
        'user_offer' => 'integer',
        'offered_years' => 'integer',
        'counter_offer' => 'integer',
        'contract_years' => 'integer',
        'disposition' => 'float',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function gamePlayer(): BelongsTo
    {
        return $this->belongsTo(GamePlayer::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_OFFER_PENDING;
    }

    public function isCountered(): bool
    {
        return $this->status === self::STATUS_PLAYER_COUNTERED;
    }

    public function isAccepted(): bool
    {
        return $this->status === self::STATUS_ACCEPTED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_PLAYER_REJECTED;
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_OFFER_PENDING, self::STATUS_PLAYER_COUNTERED]);
    }

    public function isBlocking(): bool
    {
        return in_array($this->status, [self::STATUS_PLAYER_REJECTED, self::STATUS_CLUB_DECLINED]);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [
            self::STATUS_ACCEPTED,
            self::STATUS_PLAYER_REJECTED,
            self::STATUS_CLUB_DECLINED,
            self::STATUS_CLUB_RECONSIDERED,
            self::STATUS_EXPIRED,
        ]);
    }

    public function getFormattedUserOfferAttribute(): string
    {
        return Money::format($this->user_offer);
    }

    public function getFormattedCounterOfferAttribute(): string
    {
        return Money::format($this->counter_offer);
    }

    public function getFormattedPlayerDemandAttribute(): string
    {
        return Money::format($this->player_demand);
    }
}
