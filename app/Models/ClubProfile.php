<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClubProfile extends Model
{
    use HasUuids;

    protected $fillable = [
        'team_id',
        'reputation_level',
        'commercial_revenue',
    ];

    public const REPUTATION_ELITE = 'elite';
    public const REPUTATION_CONTENDERS = 'contenders';
    public const REPUTATION_CONTINENTAL = 'continental';
    public const REPUTATION_ESTABLISHED = 'established';
    public const REPUTATION_MODEST = 'modest';
    public const REPUTATION_LOCAL = 'local';

    /**
     * Revenue per seat per season based on reputation level.
     * Higher reputation = premium pricing, better hospitality, merchandise.
     * Values in cents.
     */
    public const REVENUE_PER_SEAT = [
        self::REPUTATION_ELITE => 150_000, // €1,500/seat
        self::REPUTATION_CONTENDERS => 110_000, // €1,100/seat
        self::REPUTATION_CONTINENTAL => 80_000, // €800/seat
        self::REPUTATION_ESTABLISHED => 50_000, // €500/seat
        self::REPUTATION_MODEST => 35_000, // €350/seat
        self::REPUTATION_LOCAL => 20_000, // €200/seat
    ];

    protected $casts = [
        'commercial_revenue' => 'integer',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get revenue per seat for this club's reputation.
     */
    public function getRevenuePerSeatAttribute(): int
    {
        return self::REVENUE_PER_SEAT[$this->reputation_level] ?? self::REVENUE_PER_SEAT[self::REPUTATION_MODEST];
    }

    /**
     * Calculate base matchday revenue (before multipliers).
     * Returns value in cents.
     */
    public function calculateBaseMatchdayRevenue(): int
    {
        $seats = $this->team->stadium_seats ?? 0;
        return $seats * $this->revenue_per_seat;
    }

    /**
     * Get formatted commercial revenue.
     */
    public function getFormattedCommercialRevenueAttribute(): string
    {
        return Money::format($this->commercial_revenue);
    }
}
