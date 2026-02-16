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
 * @property string $offering_team_id
 * @property string $offer_type
 * @property int $transfer_fee
 * @property string $status
 * @property \Illuminate\Support\Carbon $expires_at
 * @property \Illuminate\Support\Carbon $game_date
 * @property \Illuminate\Support\Carbon|null $resolved_at
 * @property string $direction
 * @property string|null $selling_team_id
 * @property int|null $asking_price
 * @property int|null $offered_wage
 * @property-read \App\Models\Game $game
 * @property-read \App\Models\GamePlayer $gamePlayer
 * @property-read int $days_until_expiry
 * @property-read string $formatted_asking_price
 * @property-read string $formatted_offered_wage
 * @property-read string $formatted_transfer_fee
 * @property-read string|null $selling_team_name
 * @property-read \App\Models\Team $offeringTeam
 * @property-read \App\Models\Team|null $sellingTeam
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransferOffer agreed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransferOffer newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransferOffer newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransferOffer pending()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransferOffer query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransferOffer whereAskingPrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransferOffer whereDirection($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransferOffer whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransferOffer whereGameDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransferOffer whereGameId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransferOffer whereGamePlayerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransferOffer whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransferOffer whereOfferType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransferOffer whereOfferedWage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransferOffer whereOfferingTeamId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransferOffer whereResolvedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransferOffer whereSellingTeamId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransferOffer whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransferOffer whereTransferFee($value)
 * @mixin \Eloquent
 */
class TransferOffer extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'game_id',
        'game_player_id',
        'offering_team_id',
        'offer_type',
        'transfer_fee',
        'status',
        'expires_at',
        'direction',
        'selling_team_id',
        'asking_price',
        'offered_wage',
        'game_date',
        'resolved_at',
    ];

    protected $casts = [
        'transfer_fee' => 'integer',
        'asking_price' => 'integer',
        'offered_wage' => 'integer',
        'expires_at' => 'date',
        'game_date' => 'date',
        'resolved_at' => 'date',
    ];

    // Offer types
    public const TYPE_LISTED = 'listed';
    public const TYPE_UNSOLICITED = 'unsolicited';
    public const TYPE_PRE_CONTRACT = 'pre_contract'; // Free transfer, contract expiring
    public const TYPE_USER_BID = 'user_bid';         // User buying a player
    public const TYPE_LOAN_IN = 'loan_in';           // User borrowing a player
    public const TYPE_LOAN_OUT = 'loan_out';         // User lending a player

    // Directions
    public const DIRECTION_OUTGOING = 'outgoing'; // User selling
    public const DIRECTION_INCOMING = 'incoming'; // User buying

    // Statuses
    public const STATUS_PENDING = 'pending';
    public const STATUS_AGREED = 'agreed';       // Deal agreed, waiting for transfer window
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_COMPLETED = 'completed'; // Transfer finalized at window

    // Timing constants
    public const PRE_CONTRACT_OFFER_EXPIRY_DAYS = 14;
    public const PRE_CONTRACT_RESPONSE_DAYS = 7;

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function gamePlayer(): BelongsTo
    {
        return $this->belongsTo(GamePlayer::class);
    }

    public function offeringTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'offering_team_id');
    }

    public function sellingTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'selling_team_id');
    }

    /**
     * Get the selling team - from relationship if set, otherwise from player's current team.
     */
    public function getSellingTeamNameAttribute(): ?string
    {
        if ($this->selling_team_id) {
            return $this->sellingTeam?->name;
        }

        return $this->gamePlayer?->team?->name;
    }

    /**
     * Check if this is an incoming transfer (user buying).
     */
    public function isIncoming(): bool
    {
        return $this->direction === self::DIRECTION_INCOMING;
    }

    /**
     * Check if this is a user bid.
     */
    public function isUserBid(): bool
    {
        return $this->offer_type === self::TYPE_USER_BID;
    }

    /**
     * Check if this is a loan-in offer.
     */
    public function isLoanIn(): bool
    {
        return $this->offer_type === self::TYPE_LOAN_IN;
    }

    /**
     * Get formatted asking price for display.
     */
    public function getFormattedAskingPriceAttribute(): string
    {
        return Money::format($this->asking_price ?? 0);
    }

    /**
     * Get formatted offered wage for display.
     */
    public function getFormattedOfferedWageAttribute(): string
    {
        return Money::format($this->offered_wage ?? 0);
    }

    /**
     * Check if the offer is still pending.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if the deal has been agreed (waiting for transfer window).
     */
    public function isAgreed(): bool
    {
        return $this->status === self::STATUS_AGREED;
    }

    /**
     * Check if the offer has expired (based on game's current date).
     */
    public function isExpired(): bool
    {
        return $this->status === self::STATUS_EXPIRED
            || ($this->isPending() && $this->expires_at->lte($this->game->current_date));
    }

    /**
     * Check if this is an unsolicited (poaching) offer.
     */
    public function isUnsolicited(): bool
    {
        return $this->offer_type === self::TYPE_UNSOLICITED;
    }

    /**
     * Check if this is a pre-contract offer (free transfer).
     */
    public function isPreContract(): bool
    {
        return $this->offer_type === self::TYPE_PRE_CONTRACT;
    }

    /**
     * Get days until expiry (based on game's current date, not real-world time).
     */
    public function getDaysUntilExpiryAttribute(): int
    {
        $gameDate = $this->game->current_date;

        if ($this->expires_at->lte($gameDate)) {
            return 0;
        }

        return (int) $gameDate->diffInDays($this->expires_at);
    }

    /**
     * Get formatted transfer fee for display.
     */
    public function getFormattedTransferFeeAttribute(): string
    {
        return Money::format($this->transfer_fee);
    }

    /**
     * Scope for pending offers.
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope for agreed transfers (waiting for window).
     */
    public function scopeAgreed($query)
    {
        return $query->where('status', self::STATUS_AGREED);
    }

}
