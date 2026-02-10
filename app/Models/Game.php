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

    // La Liga season goals
    public const GOAL_TITLE = 'title';
    public const GOAL_CHAMPIONS_LEAGUE = 'champions_league';
    public const GOAL_EUROPA_LEAGUE = 'europa_league';
    public const GOAL_TOP_HALF = 'top_half';
    public const GOAL_SURVIVAL = 'survival';

    // Segunda División season goals
    public const GOAL_PROMOTION = 'promotion';
    public const GOAL_PLAYOFF = 'playoff';

    protected $fillable = [
        'id',
        'user_id',
        'player_name',
        'team_id',
        'competition_id',
        'season',
        'current_date',
        'current_matchday',
        'default_formation',
        'default_lineup',
        'default_mentality',
        'cup_round',
        'cup_eliminated',
        'season_goal',
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
        'season_goal' => 'string',
        'needs_onboarding' => 'boolean',
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

    public function notifications(): HasMany
    {
        return $this->hasMany(GameNotification::class);
    }

    public function unreadNotifications(): HasMany
    {
        return $this->hasMany(GameNotification::class)->whereNull('read_at');
    }

    /**
     * Get the active scout report (searching or completed, most recent).
     */
    public function activeScoutReport(): HasOne
    {
        return $this->hasOne(ScoutReport::class)
            ->whereIn('status', [ScoutReport::STATUS_SEARCHING, ScoutReport::STATUS_COMPLETED])
            ->orderByDesc('game_date');
    }

    /**
     * Get players for the user's team.
     */
    public function squad(): HasMany
    {
        return $this->players()->where('team_id', $this->team_id);
    }

    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
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
            return __('app.summer_window');
        }

        if ($this->isWinterWindowOpen()) {
            return __('app.winter_window');
        }

        return null;
    }

    /**
     * Get the next transfer window name.
     */
    public function getNextWindowName(): string
    {
        if (!$this->current_date) {
            return __('app.summer_window');
        }

        $month = $this->current_date->month;

        // If we're before or in winter window (Jan), next is summer (July)
        // If we're after winter but before summer (Feb-Jun), next is summer
        // If we're in or after summer (Jul-Dec), next is winter (Jan)
        if ($month >= 2 && $month <= 6) {
            return __('app.summer_window');
        }

        if ($month >= 8 && $month <= 12) {
            return __('app.winter_window');
        }

        // Currently in a window
        return $this->getCurrentWindowName() ?? __('app.summer_window');
    }

    /**
     * Get the season start date (first match date, typically mid-August).
     */
    public function getSeasonStartDate(): ?Carbon
    {
        $firstMatch = $this->getFirstCompetitiveMatch();
        return $firstMatch?->scheduled_date;
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
