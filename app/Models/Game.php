<?php

namespace App\Models;

use App\Modules\Transfer\TransferWindow;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Builder;

/**
 * @property string $id
 * @property int $user_id
 * @property string $player_name
 * @property string $team_id
 * @property string $season
 * @property \Illuminate\Support\Carbon|null $current_date
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property bool $needs_new_season_setup
 * @property bool $needs_welcome
 * @property bool $pre_season
 * @property bool $preseason_opponents_pending
 * @property bool $squad_registration_enabled
 * @property bool $release_clauses_enabled
 * @property string|null $season_goal
 * @property string $competition_id
 * @property string $game_mode
 * @property \Illuminate\Support\Carbon|null $setup_completed_at
 * @property string $country
 * @property int $manager_reputation_points
 * @property \Illuminate\Support\Carbon|null $deleting_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Loan> $activeLoans
 * @property-read int|null $active_loans_count
 * @property-read \App\Models\ScoutReport|null $activeScoutReport
 * @property-read \App\Models\Competition $competition
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CompetitionEntry> $competitionEntries
 * @property-read int|null $competition_entries_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CupTie> $cupTies
 * @property-read int|null $cup_ties_count
 * @property-read \App\Models\GameFinances|null $currentFinances
 * @property-read \App\Models\GameInvestment|null $currentInvestment
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\GameFinances> $finances
 * @property-read int|null $finances_count
 * @property-read string $formatted_season
 * @property-read \App\Models\GameMatch|null $next_match
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\GameInvestment> $investments
 * @property-read int|null $investments_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Loan> $loans
 * @property-read int|null $loans_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\GameMatch> $matches
 * @property-read int|null $matches_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\GameNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\GamePlayer> $players
 * @property-read int|null $players_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ScoutReport> $scoutReports
 * @property-read int|null $scout_reports_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\GamePlayer> $squad
 * @property-read int|null $squad_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\GameStanding> $standings
 * @property-read int|null $standings_count
 * @property-read \App\Models\Team $team
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\GameNotification> $unreadNotifications
 * @property-read int|null $unread_notifications_count
 * @property-read \App\Models\User $user
 * @method static \Database\Factories\GameFactory factory($count = null, $state = [])
 * @method static Builder<static>|Game newModelQuery()
 * @method static Builder<static>|Game newQuery()
 * @method static Builder<static>|Game query()
 * @method static Builder<static>|Game whereCompetitionId($value)
 * @method static Builder<static>|Game whereCountry($value)
 * @method static Builder<static>|Game whereCreatedAt($value)
 * @method static Builder<static>|Game whereCurrentDate($value)
 * @method static Builder<static>|Game whereDefaultFormation($value)
 * @method static Builder<static>|Game whereDefaultLineup($value)
 * @method static Builder<static>|Game whereDefaultMentality($value)
 * @method static Builder<static>|Game whereGameMode($value)
 * @method static Builder<static>|Game whereId($value)
 * @method static Builder<static>|Game whereNeedsNewSeasonSetup($value)
 * @method static Builder<static>|Game wherePlayerName($value)
 * @method static Builder<static>|Game whereSeason($value)
 * @method static Builder<static>|Game whereSeasonGoal($value)
 * @method static Builder<static>|Game whereSetupCompletedAt($value)
 * @method static Builder<static>|Game whereTeamId($value)
 * @method static Builder<static>|Game whereUpdatedAt($value)
 * @method static Builder<static>|Game whereUserId($value)
 * @mixin \Eloquent
 */
class Game extends Model
{
    use HasFactory, HasUuids;

    // Game modes
    public const MODE_CAREER = 'career';
    public const MODE_CAREER_PRO = 'career_pro';
    public const MODE_TOURNAMENT = 'tournament';

    // Season goals
    public const GOAL_TITLE = 'title';
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
        'country',
        'player_name',
        'team_id',
        'reserve_team_id',
        'competition_id',
        'season',
        'current_date',
        'season_goal',
        'needs_new_season_setup',
        'needs_welcome',
        'pre_season',
        'preseason_opponents_pending',
        'squad_registration_enabled',
        'release_clauses_enabled',
        'fast_mode_entered_on',
        'pending_actions',
        'setup_completed_at',
        'season_transitioning_at',
        'season_transition_step',
        'season_transition_data',
        'career_actions_processing_at',
        'pending_finalization_match_id',
        'matchday_advancing_at',
        'matchday_advance_result',
        'deleting_at',
        'pending_team_switch',
        'season_offers_generated_for',
        'manager_reputation_points',
    ];

    protected $casts = [
        'current_date' => 'date',
        'season_goal' => 'string',
        'needs_new_season_setup' => 'boolean',
        'needs_welcome' => 'boolean',
        'pre_season' => 'boolean',
        'preseason_opponents_pending' => 'boolean',
        'squad_registration_enabled' => 'boolean',
        'release_clauses_enabled' => 'boolean',
        'fast_mode_entered_on' => 'date',
        'pending_actions' => 'array',
        'setup_completed_at' => 'datetime',
        'season_transitioning_at' => 'datetime',
        'season_transition_step' => 'integer',
        'season_transition_data' => 'json',
        'career_actions_processing_at' => 'datetime',
        'matchday_advancing_at' => 'datetime',
        'matchday_advance_result' => 'array',
        'deleting_at' => 'datetime',
        'manager_reputation_points' => 'integer',
    ];

    /**
     * Mirror the games.game_mode DB default into the in-memory model.
     * Without this, factory-created models leave $game->game_mode as null
     * until refreshed — and listeners like UpdateManagerStats that copy
     * the field forward into manager_stats end up sending null and
     * tripping the NOT NULL constraint on that column. Real game-creation
     * paths (GameCreationService / TournamentCreationService) always set
     * game_mode explicitly, so this default only matters for tests and
     * any future caller that omits the field.
     */
    protected $attributes = [
        'game_mode' => self::MODE_CAREER,
    ];

    // ==========================================
    // Game Mode
    // ==========================================

    public function isCareerMode(): bool
    {
        $mode = $this->game_mode ?? self::MODE_CAREER;

        // Pro-manager mode is a flavour of career mode: every career-mode
        // pipeline (full SeasonSetupPipeline, finances, cups, transfers, etc.)
        // runs for it. The only difference is end-of-season team switching,
        // gated by isProManagerMode() below.
        return $mode === self::MODE_CAREER || $mode === self::MODE_CAREER_PRO;
    }

    public function isProManagerMode(): bool
    {
        return $this->game_mode === self::MODE_CAREER_PRO;
    }

    public function isTournamentMode(): bool
    {
        return $this->game_mode === self::MODE_TOURNAMENT;
    }

    public function isSetupComplete(): bool
    {
        return $this->setup_completed_at !== null;
    }

    /**
     * Re-dispatch the appropriate setup job for this game's mode.
     */
    public function redispatchSetupJob(): void
    {
        if ($this->isTournamentMode()) {
            \App\Modules\Season\Jobs\SetupTournamentGame::dispatch(
                gameId: $this->id,
                teamId: $this->team_id,
            );
        } else {
            \App\Modules\Season\Jobs\SetupNewGame::dispatch(
                gameId: $this->id,
                teamId: $this->team_id,
                competitionId: $this->competition_id,
                season: $this->season,
                gameMode: $this->game_mode ?? self::MODE_CAREER,
            );
        }
    }

    public function isTransitioningSeason(): bool
    {
        return $this->season_transitioning_at !== null;
    }

    public function isProcessingCareerActions(): bool
    {
        return $this->career_actions_processing_at !== null;
    }

    public function isAdvancingMatchday(): bool
    {
        return $this->matchday_advancing_at !== null;
    }

    public function isDeleting(): bool
    {
        return $this->deleting_at !== null;
    }

    /**
     * Clear a stuck matchday advance flag (> 2 minutes old).
     */
    public function clearStuckMatchdayAdvance(): bool
    {
        return $this->clearStuckFlag('matchday_advancing_at', ['matchday_advance_result']);
    }

    /**
     * Clear a stuck career actions flag (> 2 minutes old).
     */
    public function clearStuckCareerActions(): bool
    {
        return $this->clearStuckFlag('career_actions_processing_at');
    }

    /**
     * Clear a stuck processing flag if it's older than 2 minutes.
     */
    private function clearStuckFlag(string $column, array $extraColumns = []): bool
    {
        if ($this->$column === null) {
            return false;
        }

        if (! $this->$column->lt(now()->subMinutes(2))) {
            return false;
        }

        $this->update(array_merge(
            [$column => null],
            array_fill_keys($extraColumns, null),
        ));

        return true;
    }

    // ==========================================
    // Date Advancement
    // ==========================================

    /**
     * Advance current_date forward to the next unplayed match.
     *
     * This is the canonical forward-looking jump, used at match finalization
     * to move the calendar to the upcoming match. Only moves forward — never
     * regresses or holds the date (returns false in those cases).
     *
     * Note: batch processing (MatchResultProcessor) deliberately does NOT use
     * this — it pins current_date to the matchday being processed so it can't
     * jump past unplayed matches and steal the GameDateAdvanced event that
     * finalization dispatches.
     *
     * @return bool Whether the date actually changed.
     */
    public function advanceDateToNextMatch(): bool
    {
        $nextMatch = GameMatch::where('game_id', $this->id)
            ->where('played', false)
            ->orderBy('scheduled_date')
            ->first();

        if (! $nextMatch) {
            return false;
        }

        if ($this->current_date && $nextMatch->scheduled_date->lte($this->current_date)) {
            return false;
        }

        $this->update(['current_date' => $nextMatch->scheduled_date->toDateString()]);
        $this->refresh();

        return true;
    }

    // ==========================================
    // Pending Actions (Game Progress Blocking)
    // ==========================================

    public function hasPendingActions(): bool
    {
        return !empty($this->pending_actions);
    }

    public function getFirstPendingAction(): ?array
    {
        return $this->pending_actions[0] ?? null;
    }

    public function hasPendingAction(string $type): bool
    {
        foreach ($this->pending_actions ?? [] as $action) {
            if ($action['type'] === $type) {
                return true;
            }
        }
        return false;
    }

    public function addPendingAction(string $type, string $route): void
    {
        $actions = $this->pending_actions ?? [];

        foreach ($actions as $action) {
            if ($action['type'] === $type) {
                return;
            }
        }

        $actions[] = ['type' => $type, 'route' => $route];
        $this->update(['pending_actions' => $actions]);
    }

    public function removePendingAction(string $type): void
    {
        $actions = $this->pending_actions ?? [];
        $actions = array_values(array_filter($actions, fn ($a) => $a['type'] !== $type));
        $this->update(['pending_actions' => empty($actions) ? null : $actions]);
    }

    public function clearPendingActions(): void
    {
        $this->update(['pending_actions' => null]);
    }

    /**
     * Check if a match in the given competition is pending finalization.
     *
     * When the user plays a live match, side effects (standings, GK stats,
     * cup tie resolution) are deferred until finalization. Round generation
     * must wait until finalization completes so it reads correct standings
     * and all cup ties are resolved.
     */
    public function hasPendingFinalizationForCompetition(string $competitionId): bool
    {
        if (! $this->pending_finalization_match_id) {
            return false;
        }

        return GameMatch::where('id', $this->pending_finalization_match_id)
            ->where('competition_id', $competitionId)
            ->exists();
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

    /**
     * Team to render in user-facing chrome (loading screens, etc.). When a
     * pro-manager has accepted an end-of-season offer but the setup pipeline
     * hasn't applied the switch yet, team_id still points at the outgoing
     * club — preview the destination club instead so the crest matches the
     * user's just-made choice.
     */
    public function displayTeam(): ?Team
    {
        if ($this->pending_team_switch) {
            $offer = ManagerJobOffer::with('team')->find($this->pending_team_switch);
            if ($offer?->team) {
                return $offer->team;
            }
        }

        return $this->team;
    }

    /**
     * Was the manager fired at the end of the current $game->season? Derived
     * from the existence of a post-firing offer row for this season: the only
     * code path that creates such offers is JobOfferService for grade=disaster.
     *
     * Must be called while $game->season still points at the season whose
     * firing outcome we care about — the closing pipeline runs first (so
     * SnapshotManagerSeasonRecordProcessor sees the old season), then the
     * season is advanced, then the setup pipeline runs. Setup-time callers
     * should read offer_type off the accepted offer instead.
     */
    public function wasFiredThisSeason(): bool
    {
        return ManagerJobOffer::where('game_id', $this->id)
            ->where('season', $this->season)
            ->where('offer_type', ManagerJobOffer::TYPE_POST_FIRING)
            ->exists();
    }

    public function reserveTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'reserve_team_id');
    }

    /**
     * Whether the given team is owned by the user in this game (first team
     * or, for parent clubs, the filial reserve team).
     */
    public function ownsTeam(?string $teamId): bool
    {
        if ($teamId === null) {
            return false;
        }

        return $teamId === $this->team_id || $teamId === $this->reserve_team_id;
    }

    /**
     * IDs of all teams the user manages in this game (first team, plus the
     * reserve team for parent clubs). Null entries are filtered out.
     *
     * @return array<int, string>
     */
    public function userTeamIds(): array
    {
        return array_values(array_filter([$this->team_id, $this->reserve_team_id]));
    }

    public function isFilial(): bool
    {
        return $this->reserve_team_id !== null;
    }

    public function tactics(): HasOne
    {
        return $this->hasOne(GameTactics::class);
    }

    public function tacticalPresets(): HasMany
    {
        return $this->hasMany(GameTacticalPreset::class)->orderBy('sort_order');
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
        // games.season is string(10), game_finances.season is integer — cast
        // explicitly so PostgreSQL doesn't have to coerce a varchar parameter
        // against an integer column on every lookup.
        return $this->hasOne(GameFinances::class)->where('season', (int) $this->season);
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
        // games.season is string(10), game_investments.season is integer —
        // cast explicitly so the relation lookup matches the column type.
        return $this->hasOne(GameInvestment::class)->where('season', (int) $this->season);
    }

    /**
     * Get the investment record from the previous season, if any.
     */
    public function previousSeasonInvestment(): ?GameInvestment
    {
        $previousSeason = (int) $this->season - 1;

        if ($previousSeason < 1) {
            return null;
        }

        return GameInvestment::where('game_id', $this->id)
            ->where('season', $previousSeason)
            ->first();
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    public function activeLoans(): HasMany
    {
        return $this->hasMany(Loan::class)->where('status', Loan::STATUS_ACTIVE);
    }

    public function budgetLoans(): HasMany
    {
        return $this->hasMany(BudgetLoan::class);
    }

    public function activeBudgetLoan(): HasOne
    {
        return $this->hasOne(BudgetLoan::class)->where('status', BudgetLoan::STATUS_ACTIVE);
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

    public function teamReputations(): HasMany
    {
        return $this->hasMany(TeamReputation::class);
    }

    public function getNextMatchAttribute(): ?GameMatch
    {
        /** @var GameMatch|null */
        return $this->matches()
            ->where('played', false)
            ->where(function ($query) {
                $query->where('home_team_id', $this->team_id)
                    ->orWhere('away_team_id', $this->team_id);
            })
            ->orderBy('scheduled_date')
            ->first();
    }

    public function getNextLeagueMatchdayAttribute(): ?int
    {
        return $this->matches()
            ->where('played', false)
            ->whereNull('cup_tie_id')
            ->orderBy('scheduled_date')
            ->value('round_number');
    }

    // ==========================================
    // Transfer Window Logic (delegates to TransferWindow value object)
    // ==========================================

    public function transferWindow(): TransferWindow
    {
        return new TransferWindow($this->current_date ?? Carbon::now());
    }

    public function isSummerWindowOpen(): bool
    {
        return $this->current_date && $this->transferWindow()->isSummer();
    }

    public function isWinterWindowOpen(): bool
    {
        return $this->current_date && $this->transferWindow()->isWinter();
    }

    public function isTransferWindowOpen(): bool
    {
        return $this->current_date && $this->transferWindow()->isOpen();
    }

    public function getCurrentWindowName(): ?string
    {
        return $this->current_date ? $this->transferWindow()->displayName() : null;
    }

    public function getNextWindowName(): string
    {
        if (! $this->current_date) {
            return __('app.summer_window');
        }

        // If a window is currently open, that is the window any pending "agreed"
        // transfer will complete in — not the chronologically next one.
        $tw = $this->transferWindow();

        return ($tw->isOpen() ? $tw->displayName() : null)
            ?? $tw->nextWindowDisplayName();
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
     * Reference date used to display "age at registration" on the squad
     * registration screen. Frozen at January 1 of the season-start year so the
     * displayed age doesn't flip mid-season. NOT the U-23 eligibility cutoff —
     * use getU23BirthCutoff() for filial / "ficha del filial" slot checks.
     */
    public function getRegistrationReferenceDate(): Carbon
    {
        return Carbon::createFromDate((int) $this->season, 1, 1);
    }

    /**
     * Date-of-birth cutoff for U-23 registration eligibility (filial slots).
     *
     * A player is U-23 for the season iff their date_of_birth is on or after
     * this cutoff: FIFA-style "U-23 for season Y = born on or after Jan 1 of
     * (Y - 23)". A player born Dec 31, (Y - 24) is NOT U-23 even though they
     * are still 23 on the season's Jan 1 reference date — they turn 24 during
     * the calendar year the season starts, which disqualifies them.
     *
     * Use with `where('date_of_birth', '>=', $cutoff)` for query-side filters
     * and `$player->date_of_birth->greaterThanOrEqualTo($cutoff)` in PHP.
     *
     * Pass an explicit $seasonYear when evaluating eligibility for a season
     * other than the current one (e.g. season-close auto-promotion needs the
     * next season's cutoff before $game->season is incremented).
     */
    public function getU23BirthCutoff(?int $seasonYear = null): Carbon
    {
        $seasonYear ??= (int) $this->season;
        return Carbon::createFromDate($seasonYear - 23, 1, 1);
    }

    /**
     * Effective date a newly-finalized loan will start on: today if the
     * transfer window is open, otherwise the next window opening date.
     * Used so loans agreed while the window is closed record the date on
     * which the player actually moves, not the date the deal was agreed.
     */
    public function getLoanEffectiveStartDate(): Carbon
    {
        $window = $this->transferWindow();

        return $window->isOpen()
            ? $this->current_date->copy()
            : ($window->openingBoundaryDate() ?? $this->current_date->copy());
    }

    /**
     * June 30 of the football season that contains the given date.
     * A season runs July 1 → June 30 of the following calendar year.
     */
    public function getSeasonEndDateFor(Carbon $date): Carbon
    {
        $endYear = $date->month >= 7 ? $date->year + 1 : $date->year;

        return Carbon::createFromDate($endYear, 6, 30);
    }

    /**
     * Get the first competitive match of the season.
     */
    public function getFirstCompetitiveMatch(): ?GameMatch
    {
        /** @var GameMatch|null */
        return $this->matches()
            ->where('played', false)
            ->whereNull('cup_tie_id') // League match
            ->orderBy('scheduled_date')
            ->first();
    }

    // ==========================================
    // Pre-Contract Period
    // ==========================================

    public function isPreContractPeriod(): bool
    {
        return $this->current_date && $this->transferWindow()->isPreContractPeriod();
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
     * @return array{action: string, window: string, matchdays: int, date: Carbon}|null
     */
    public function getWindowCountdown(): ?array
    {
        if (! $this->current_date) {
            return null;
        }

        $tw = $this->transferWindow();
        $boundary = $tw->isOpen()
            ? $tw->closingBoundaryDate()
            : $tw->openingBoundaryDate();

        if (! $boundary) {
            return null;
        }

        $action = $tw->isOpen() ? 'closes' : 'opens';
        $windowName = $tw->isOpen()
            ? $tw->displayName()
            : $tw->nextWindowDisplayName();

        // Count unplayed matches between now and the boundary
        $matchdays = $this->matches()
            ->where('played', false)
            ->where(function ($query) {
                $query->where('home_team_id', $this->team_id)
                    ->orWhere('away_team_id', $this->team_id);
            })
            ->where('scheduled_date', '<', $boundary)
            ->where('scheduled_date', '>=', $this->current_date)
            ->count();

        if ($matchdays > 10) {
            return null;
        }

        return [
            'action' => $action,
            'window' => $windowName,
            'matchdays' => $matchdays,
            'date' => $boundary,
        ];
    }

    // ==========================================
    // Welcome & New Season Setup
    // ==========================================

    /**
     * Check if the game needs the welcome tutorial.
     */
    public function needsWelcome(): bool
    {
        return $this->needs_welcome ?? false;
    }

    /**
     * Complete the welcome tutorial.
     */
    public function completeWelcome(): void
    {
        $this->update(['needs_welcome' => false]);
    }

    /**
     * Check if the game needs new-season setup (season budget allocation).
     */
    public function needsNewSeasonSetup(): bool
    {
        return $this->needs_new_season_setup ?? false;
    }

    /**
     * Complete the new-season setup process.
     */
    public function completeNewSeasonSetup(): void
    {
        $this->update(['needs_new_season_setup' => false]);
    }

    // ==========================================
    // Pre-Season
    // ==========================================

    public function isInPreSeason(): bool
    {
        return $this->pre_season ?? false;
    }

    /**
     * Whether the player still needs to choose their pre-season opponents.
     * Gates the dashboard behind the mandatory pre-season setup screen at the
     * start of every career-mode season (initial and transitions).
     */
    public function needsPreseasonOpponentSelection(): bool
    {
        return $this->isInPreSeason() && ($this->preseason_opponents_pending ?? false);
    }

    public function requiresSquadEnrollment(): bool
    {
        return $this->squad_registration_enabled && !$this->isInPreSeason();
    }

    public function endPreSeason(): void
    {
        $this->update(['pre_season' => false]);
    }

    // ==========================================
    // Fast Mode
    // ==========================================

    public function isFastMode(): bool
    {
        return $this->fast_mode_entered_on !== null;
    }
}
