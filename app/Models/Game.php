<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Game extends Model
{
    use HasFactory, HasUuids;

    protected $guarded = [];

    protected $casts = [
        'current_date' => 'date',
        'current_matchday' => 'integer',
        'default_formation' => 'string',
        'default_lineup' => 'array',
        'default_mentality' => 'string',
        'cup_round' => 'integer',
        'cup_eliminated' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(GameMatch::class);
    }

    public function standings(): HasMany
    {
        return $this->hasMany(GameStanding::class);
    }

    public function players(): HasMany
    {
        return $this->hasMany(GamePlayer::class);
    }

    public function cupTies(): HasMany
    {
        return $this->hasMany(CupTie::class);
    }

    public function finances(): HasOne
    {
        return $this->hasOne(GameFinances::class);
    }

    /**
     * Get players for the user's team.
     */
    public function squad(): HasMany
    {
        return $this->players()->where('team_id', $this->team_id);
    }

    public function getCompetitionIdAttribute(): string
    {
        // Determine competition based on team
        return $this->team?->competitions()->first()?->id ?? 'ESP1';
    }

    public function getNextMatchAttribute(): ?GameMatch
    {
        return $this->matches()
            ->where('played', false)
            ->where(function ($query) {
                $query->where('home_team_id', $this->team_id)
                    ->orWhere('away_team_id', $this->team_id);
            })
            ->orderBy('scheduled_date')
            ->first();
    }

    // ==========================================
    // Transfer Window Logic (Calendar-based)
    // ==========================================

    /**
     * Summer transfer window: July 1 - August 31
     * This is when the season starts, contracts renew, etc.
     */
    public function isSummerWindowOpen(): bool
    {
        if (!$this->current_date) {
            return false;
        }

        $month = $this->current_date->month;
        return $month === 7 || $month === 8;
    }

    /**
     * Winter transfer window: January 1 - January 31
     * Mid-season transfer period.
     */
    public function isWinterWindowOpen(): bool
    {
        if (!$this->current_date) {
            return false;
        }

        return $this->current_date->month === 1;
    }

    /**
     * Check if any transfer window is currently open.
     */
    public function isTransferWindowOpen(): bool
    {
        return $this->isSummerWindowOpen() || $this->isWinterWindowOpen();
    }

    /**
     * Check if we've just entered the summer window (July 1).
     * Used to trigger one-time events like wage payments, TV rights, etc.
     */
    public function isStartOfSummerWindow(): bool
    {
        if (!$this->current_date) {
            return false;
        }

        // First day of July
        return $this->current_date->month === 7 && $this->current_date->day <= 7;
    }

    /**
     * Check if we've just entered the winter window (January 1).
     * Used to trigger one-time events like wage payments.
     */
    public function isStartOfWinterWindow(): bool
    {
        if (!$this->current_date) {
            return false;
        }

        // First week of January
        return $this->current_date->month === 1 && $this->current_date->day <= 7;
    }

    /**
     * Check if we're at the start of either transfer window.
     * This is when financial events (wages, TV rights) should be processed.
     */
    public function isTransferWindowStart(): bool
    {
        return $this->isStartOfSummerWindow() || $this->isStartOfWinterWindow();
    }

    /**
     * Get the current transfer window name, or null if none is open.
     */
    public function getCurrentWindowName(): ?string
    {
        if ($this->isSummerWindowOpen()) {
            return 'Summer';
        }

        if ($this->isWinterWindowOpen()) {
            return 'Winter';
        }

        return null;
    }

    /**
     * Get the next transfer window name.
     */
    public function getNextWindowName(): string
    {
        if (!$this->current_date) {
            return 'Summer';
        }

        $month = $this->current_date->month;

        // If we're before or in winter window (Jan), next is summer (July)
        // If we're after winter but before summer (Feb-Jun), next is summer
        // If we're in or after summer (Jul-Dec), next is winter (Jan)
        if ($month >= 2 && $month <= 6) {
            return 'Summer';
        }

        if ($month >= 8 && $month <= 12) {
            return 'Winter';
        }

        // Currently in a window
        return $this->getCurrentWindowName() ?? 'Summer';
    }
}
