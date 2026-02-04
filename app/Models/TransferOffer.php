<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransferOffer extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'transfer_fee' => 'integer',
        'expires_at' => 'date',
    ];

    // Offer types
    public const TYPE_LISTED = 'listed';
    public const TYPE_UNSOLICITED = 'unsolicited';
    public const TYPE_PRE_CONTRACT = 'pre_contract'; // Free transfer, contract expiring

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
