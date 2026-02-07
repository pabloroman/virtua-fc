<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransferOffer extends Model
{
    use HasUuids;

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
    ];

    protected $casts = [
        'transfer_fee' => 'integer',
        'asking_price' => 'integer',
        'offered_wage' => 'integer',
        'expires_at' => 'date',
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

        return $gameDate->diffInDays($this->expires_at);
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

    /**
     * Scope for non-expired offers.
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_PENDING)
            ->where('expires_at', '>=', now());
    }
}
