<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Builder;

class Game extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'id',
        'user_id',
        'player_name',
        'team_id',
        'season',
        'current_date',
        'current_matchday',
        'default_formation',
        'default_lineup',
        'default_mentality',
        'cup_round',
        'cup_eliminated',
        'is_preseason',
        'preseason_week',
        'needs_onboarding',
    ];

    protected $casts = [
        'current_date' => 'date',
        'current_matchday' => 'integer',
        'default_formation' => 'string',
        'default_lineup' => 'array',
        'default_mentality' => 'string',
        'cup_round' => 'integer',
        'cup_eliminated' => 'boolean',
        'is_preseason' => 'boolean',
        'preseason_week' => 'integer',
        'needs_onboarding' => 'boolean',
    ];

    // Pre-season configuration
    public const PRESEASON_TOTAL_WEEKS = 7;
    public const PRESEASON_START_MONTH = 7; // July
    public const PRESEASON_START_DAY = 1;

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

    public function finances(): HasMany
    {
        return $this->hasMany(GameFinances::class);
    }

    /**
     * Get the finances for the current season.
     * Note: Use lazy loading ($game->currentFinances) rather than eager loading.
     */
    public function currentFinances(): HasOne
    {
        return $this->hasOne(GameFinances::class)->where('season', $this->season);
    }

    public function investments(): HasMany
    {
        return $this->hasMany(GameInvestment::class);
    }

    /**
     * Get the investment for the current season.
     * Note: Use lazy loading ($game->currentInvestment) rather than eager loading.
     */
    public function currentInvestment(): HasOne
    {
        return $this->hasOne(GameInvestment::class)->where('season', $this->season);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    public function activeLoans(): HasMany
    {
        return $this->hasMany(Loan::class)->where('status', Loan::STATUS_ACTIVE);
    }

    public function scoutReports(): HasMany
    {
        return $this->hasMany(ScoutReport::class);
    }

    /**
     * Get the active scout report (searching or completed, most recent).
     */
    public function activeScoutReport(): HasOne
    {
        return $this->hasOne(ScoutReport::class)
            ->whereIn('status', [ScoutReport::STATUS_SEARCHING, ScoutReport::STATUS_COMPLETED])
            ->latest();
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

    // ==========================================
    // Pre-season Logic
    // ==========================================

    /**
     * Check if the game is currently in pre-season.
     */
    public function isInPreseason(): bool
    {
        return $this->is_preseason;
    }

    /**
     * Get the current pre-season week (1-based).
     */
    public function getPreseasonWeek(): int
    {
        return $this->preseason_week;
    }

    /**
     * Get the total number of pre-season weeks.
     */
    public function getPreseasonTotalWeeks(): int
    {
        return self::PRESEASON_TOTAL_WEEKS;
    }

    /**
     * Get weeks remaining in pre-season.
     */
    public function getPreseasonWeeksRemaining(): int
    {
        return max(0, self::PRESEASON_TOTAL_WEEKS - $this->preseason_week);
    }

    /**
     * Check if pre-season is complete.
     */
    public function isPreseasonComplete(): bool
    {
        return $this->preseason_week >= self::PRESEASON_TOTAL_WEEKS;
    }

    /**
     * Start pre-season mode.
     */
    public function startPreseason(): void
    {
        $this->update([
            'is_preseason' => true,
            'preseason_week' => 0,
            'current_date' => $this->getPreseasonStartDate(),
        ]);
    }

    /**
     * Advance pre-season by one week.
     */
    public function advancePreseasonWeek(): void
    {
        $this->increment('preseason_week');
        $this->update([
            'current_date' => $this->current_date->addWeek(),
        ]);
    }

    /**
     * End pre-season mode.
     */
    public function endPreseason(): void
    {
        $this->update([
            'is_preseason' => false,
            'preseason_week' => 0,
        ]);
    }

    /**
     * Get the pre-season start date (July 1 of the season year).
     */
    public function getPreseasonStartDate(): Carbon
    {
        $seasonYear = (int) $this->season;
        return Carbon::createFromDate($seasonYear, self::PRESEASON_START_MONTH, self::PRESEASON_START_DAY);
    }

    /**
     * Get the season end date (June 30 of the following year).
     */
    public function getSeasonEndDate(): Carbon
    {
        $seasonYear = (int) $this->season;
        return Carbon::createFromDate($seasonYear + 1, 6, 30);
    }

    /**
     * Get the first competitive match of the season.
     */
    public function getFirstCompetitiveMatch(): ?GameMatch
    {
        return $this->matches()
            ->where('played', false)
            ->whereNull('cup_tie_id') // League match
            ->orderBy('scheduled_date')
            ->first();
    }

    /**
     * Get pre-season progress as a percentage.
     */
    public function getPreseasonProgressPercent(): int
    {
        if (self::PRESEASON_TOTAL_WEEKS === 0) {
            return 100;
        }

        return (int) (($this->preseason_week / self::PRESEASON_TOTAL_WEEKS) * 100);
    }

    // ==========================================
    // Onboarding
    // ==========================================

    /**
     * Check if the game needs onboarding (first-time setup).
     */
    public function needsOnboarding(): bool
    {
        return $this->needs_onboarding ?? false;
    }

    /**
     * Complete the onboarding process.
     */
    public function completeOnboarding(): void
    {
        $this->update(['needs_onboarding' => false]);
    }
}
