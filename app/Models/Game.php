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

    // Game modes
    public const MODE_CAREER = 'career';
    public const MODE_TOURNAMENT = 'tournament';

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
        'game_mode',
        'player_name',
        'team_id',
        'competition_id',
        'season',
        'current_date',
        'current_matchday',
        'default_formation',
        'default_lineup',
        'default_mentality',
        'season_goal',
        'needs_onboarding',
        'setup_completed_at',
    ];

    protected $casts = [
        'current_date' => 'date',
        'current_matchday' => 'integer',
        'default_formation' => 'string',
        'default_lineup' => 'array',
        'default_mentality' => 'string',
        'season_goal' => 'string',
        'needs_onboarding' => 'boolean',
        'setup_completed_at' => 'datetime',
    ];

    // ==========================================
    // Game Mode
    // ==========================================

    public function isCareerMode(): bool
    {
        return ($this->game_mode ?? self::MODE_CAREER) === self::MODE_CAREER;
    }

    public function isTournamentMode(): bool
    {
        return $this->game_mode === self::MODE_TOURNAMENT;
    }

    public function isSetupComplete(): bool
    {
        return $this->setup_completed_at !== null;
    }

    // ==========================================
    // Relationships
    // ==========================================

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
     * Get the currently searching scout report.
     */
    public function activeScoutReport(): HasOne
    {
        return $this->hasOne(ScoutReport::class)
            ->where('status', ScoutReport::STATUS_SEARCHING);
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

    public function competitionEntries(): HasMany
    {
        return $this->hasMany(CompetitionEntry::class);
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
     *
     * Also accounts for the gap between the last December match and the first
     * January match: current_date only advances when matches are played, so
     * when it's still December but the next match is in January, the calendar
     * has progressed past January 1st and the window should be open.
     */
    public function isWinterWindowOpen(): bool
    {
        if (!$this->current_date) {
            return false;
        }

        if ($this->current_date->month === 1) {
            return true;
        }

        if ($this->current_date->month === 12) {
            $nextMatch = $this->next_match;
            if ($nextMatch && $nextMatch->scheduled_date->month === 1) {
                return true;
            }
        }

        return false;
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
     *
     * Also accounts for the December→January gap (see isWinterWindowOpen).
     */
    public function isStartOfWinterWindow(): bool
    {
        if (!$this->current_date) {
            return false;
        }

        // First week of January
        if ($this->current_date->month === 1 && $this->current_date->day <= 7) {
            return true;
        }

        // December→January gap: next match is in early January
        if ($this->current_date->month === 12) {
            $nextMatch = $this->next_match;
            if ($nextMatch && $nextMatch->scheduled_date->month === 1 && $nextMatch->scheduled_date->day <= 7) {
                return true;
            }
        }

        return false;
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
    // Pre-Contract Period
    // ==========================================

    /**
     * Check if we're in the pre-contract offer period (January through May).
     * Players in their last year of contract can be approached for a free transfer.
     *
     * Also accounts for the December→January gap (see isWinterWindowOpen).
     */
    public function isPreContractPeriod(): bool
    {
        if (!$this->current_date) {
            return false;
        }

        $month = $this->current_date->month;

        if ($month >= 1 && $month <= 5) {
            return true;
        }

        if ($month === 12) {
            $nextMatch = $this->next_match;
            if ($nextMatch && $nextMatch->scheduled_date->month === 1) {
                return true;
            }
        }

        return false;
    }

    // ==========================================
    // Season Display
    // ==========================================

    /**
     * Format a season year for display: "2025" → "2025/26".
     */
    public static function formatSeason(string $season): string
    {
        if (str_contains($season, '/') || str_contains($season, '-')) {
            return $season;
        }

        $year = (int) $season;
        $nextYear = ($year + 1) % 100;

        return $season.'/'.str_pad((string) $nextYear, 2, '0', STR_PAD_LEFT);
    }

    /**
     * Get the season formatted for display (e.g. "2025/26").
     */
    public function getFormattedSeasonAttribute(): string
    {
        return self::formatSeason($this->season);
    }

    // ==========================================
    // Window Countdown
    // ==========================================

    /**
     * Get a countdown to the next window boundary (opening or closing).
     * Returns null when no boundary is within 10 matchdays.
     *
     * @return array{action: string, window: string, matchdays: int}|null
     */
    public function getWindowCountdown(): ?array
    {
        if (!$this->current_date) {
            return null;
        }

        $month = $this->current_date->month;

        // Determine the next interesting boundary date
        $year = $this->current_date->year;
        $boundaries = [];

        if ($this->isTransferWindowOpen()) {
            // Window is open — countdown to closing
            if ($this->isSummerWindowOpen()) {
                $boundaries[] = [
                    'date' => Carbon::createFromDate($year, 9, 1),
                    'action' => 'closes',
                    'window' => __('app.summer_window'),
                ];
            }
            if ($this->isWinterWindowOpen()) {
                $closeYear = $month === 12 ? $year + 1 : $year;
                $boundaries[] = [
                    'date' => Carbon::createFromDate($closeYear, 2, 1),
                    'action' => 'closes',
                    'window' => __('app.winter_window'),
                ];
            }
        } else {
            // Window is closed — countdown to opening
            if ($month >= 2 && $month <= 6) {
                $boundaries[] = [
                    'date' => Carbon::createFromDate($year, 7, 1),
                    'action' => 'opens',
                    'window' => __('app.summer_window'),
                ];
            }
            if ($month >= 9 && $month <= 12) {
                $boundaries[] = [
                    'date' => Carbon::createFromDate($year + 1, 1, 1),
                    'action' => 'opens',
                    'window' => __('app.winter_window'),
                ];
            }
        }

        if (empty($boundaries)) {
            return null;
        }

        // Pick the nearest boundary
        $nearest = collect($boundaries)->sortBy('date')->first();

        // Count unplayed matches between now and the boundary
        $matchdays = $this->matches()
            ->where('played', false)
            ->where(function ($query) {
                $query->where('home_team_id', $this->team_id)
                    ->orWhere('away_team_id', $this->team_id);
            })
            ->where('scheduled_date', '<', $nearest['date'])
            ->where('scheduled_date', '>=', $this->current_date)
            ->count();

        if ($matchdays > 10) {
            return null;
        }

        return [
            'action' => $nearest['action'],
            'window' => $nearest['window'],
            'matchdays' => $matchdays,
        ];
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
