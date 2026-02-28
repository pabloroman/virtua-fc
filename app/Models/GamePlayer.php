<?php

namespace App\Models;

use App\Modules\Squad\Services\InjuryService;
use App\Support\CountryCodeMapper;
use App\Support\Money;
use App\Support\PositionMapper;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property string $id
 * @property string $game_id
 * @property string $player_id
 * @property string $team_id
 * @property string $position
 * @property string|null $market_value
 * @property int $market_value_cents
 * @property \Illuminate\Support\Carbon|null $contract_until
 * @property int $fitness
 * @property int $morale
 * @property \Illuminate\Support\Carbon|null $injury_until
 * @property string|null $injury_type
 * @property int|null $suspended_until_matchday
 * @property int $appearances
 * @property int $goals
 * @property int $own_goals
 * @property int $assists
 * @property int $yellow_cards
 * @property int $red_cards
 * @property int|null $game_technical_ability
 * @property int|null $game_physical_ability
 * @property int|null $potential
 * @property int|null $potential_low
 * @property int|null $potential_high
 * @property int $season_appearances
 * @property int $goals_conceded
 * @property int $clean_sheets
 * @property int $annual_wage
 * @property string|null $transfer_status
 * @property \Illuminate\Support\Carbon|null $transfer_listed_at
 * @property int|null $pending_annual_wage
 * @property int $durability
 * @property string|null $retiring_at_season
 * @property int|null $number
 * @property-read \App\Models\Loan|null $activeLoan
 * @property-read int|null $active_offers_count
 * @property-read \App\Models\RenewalNegotiation|null $activeRenewalNegotiation
 * @property-read \App\Models\Game $game
 * @property-read int $age
 * @property-read int $annual_wage_euros
 * @property-read int|null $contract_expiry_year
 * @property-read int $current_physical_ability
 * @property-read int $current_technical_ability
 * @property-read string $development_status
 * @property-read string $formatted_market_value
 * @property-read string|null $formatted_pending_wage
 * @property-read string $formatted_wage
 * @property-read string $name
 * @property-read array|null $nationality
 * @property-read array{name: string, flag: string}|null $nationality_flag
 * @property-read int $overall_score
 * @property-read int $physical_ability
 * @property-read string $position_abbreviation
 * @property-read array{abbreviation: string, name: string}|null $position_display
 * @property-read string $position_group
 * @property-read string $position_name
 * @property-read string $potential_range
 * @property-read int $technical_ability
 * @property-read \App\Models\RenewalNegotiation|null $latestRenewalNegotiation
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\MatchEvent> $matchEvents
 * @property-read int|null $match_events_count
 * @property-read \App\Models\Player $player
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PlayerSuspension> $suspensions
 * @property-read int|null $suspensions_count
 * @property-read \App\Models\Team $team
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TransferOffer> $transferOffers
 * @property-read int|null $transfer_offers_count
 * @method static \Database\Factories\GamePlayerFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer whereAnnualWage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer whereAppearances($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer whereAssists($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer whereCleanSheets($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer whereContractUntil($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer whereDurability($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer whereFitness($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer whereGameId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer whereGamePhysicalAbility($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer whereGameTechnicalAbility($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer whereGoals($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer whereGoalsConceded($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer whereInjuryType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer whereInjuryUntil($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer whereMarketValue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer whereMarketValueCents($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer whereMorale($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer whereNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer whereOwnGoals($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer wherePendingAnnualWage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer wherePlayerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer wherePosition($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer wherePotential($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer wherePotentialHigh($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer wherePotentialLow($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer whereRedCards($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer whereRetiringAtSeason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer whereSeasonAppearances($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer whereSuspendedUntilMatchday($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer whereTeamId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer whereTransferListedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer whereTransferStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer whereYellowCards($value)
 * @mixin \Eloquent
 */
class GamePlayer extends Model

{
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'game_id',
        'player_id',
        'team_id',
        'number',
        'position',
        'market_value',
        'market_value_cents',
        'contract_until',
        'annual_wage',
        'pending_annual_wage',
        'fitness',
        'morale',
        'durability',
        'injury_until',
        'injury_type',
        'suspended_until_matchday',
        'appearances',
        'goals',
        'own_goals',
        'assists',
        'yellow_cards',
        'red_cards',
        'goals_conceded',
        'clean_sheets',
        'game_technical_ability',
        'game_physical_ability',
        'potential',
        'potential_low',
        'potential_high',
        'season_appearances',
        'transfer_status',
        'transfer_listed_at',
        'retiring_at_season',
    ];

    protected $casts = [
        'number' => 'integer',
        'market_value_cents' => 'integer',
        'contract_until' => 'date',
        'annual_wage' => 'integer',
        'pending_annual_wage' => 'integer',
        'fitness' => 'integer',
        'morale' => 'integer',
        'durability' => 'integer',
        'injury_until' => 'date',
        'suspended_until_matchday' => 'integer',
        'appearances' => 'integer',
        'goals' => 'integer',
        'own_goals' => 'integer',
        'assists' => 'integer',
        'yellow_cards' => 'integer',
        'red_cards' => 'integer',
        'goals_conceded' => 'integer',
        'clean_sheets' => 'integer',
        // Development fields
        'game_technical_ability' => 'integer',
        'game_physical_ability' => 'integer',
        'potential' => 'integer',
        'potential_low' => 'integer',
        'potential_high' => 'integer',
        'season_appearances' => 'integer',
        // Transfer fields
        'transfer_listed_at' => 'datetime',
    ];

    // Transfer status constants
    public const TRANSFER_STATUS_LISTED = 'listed';
    public const TRANSFER_STATUS_LOAN_SEARCH = 'loan_search';

    /**
     * Find the next available squad number for a team.
     * Scans 2–99 and returns the first unused number.
     */
    public static function nextAvailableNumber(string $gameId, string $teamId): int
    {
        $taken = static::where('game_id', $gameId)
            ->where('team_id', $teamId)
            ->whereNotNull('number')
            ->pluck('number')
            ->all();

        for ($n = 2; $n <= 99; $n++) {
            if (!in_array($n, $taken)) {
                return $n;
            }
        }

        return 99;
    }

    /**
     * Check if player has announced retirement.
     */
    public function isRetiring(): bool
    {
        return $this->retiring_at_season !== null;
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function matchEvents(): HasMany
    {
        return $this->hasMany(MatchEvent::class);
    }

    public function transferOffers(): HasMany
    {
        return $this->hasMany(TransferOffer::class);
    }

    public function suspensions(): HasMany
    {
        return $this->hasMany(PlayerSuspension::class);
    }

    /**
     * Get the active loan for this player (if any).
     */
    public function activeLoan(): HasOne
    {
        return $this->hasOne(Loan::class)->where('status', Loan::STATUS_ACTIVE);
    }

    /**
     * Get the active renewal negotiation for this player (if any).
     */
    public function activeRenewalNegotiation(): HasOne
    {
        return $this->hasOne(RenewalNegotiation::class)
            ->whereIn('status', [RenewalNegotiation::STATUS_OFFER_PENDING, RenewalNegotiation::STATUS_PLAYER_COUNTERED]);
    }

    /**
     * Get the latest renewal negotiation (by creation date).
     */
    public function latestRenewalNegotiation(): HasOne
    {
        return $this->hasOne(RenewalNegotiation::class)->latest();
    }

    /**
     * Check if player has an active renewal negotiation in progress.
     */
    public function hasActiveNegotiation(): bool
    {
        if ($this->relationLoaded('activeRenewalNegotiation')) {
            return $this->activeRenewalNegotiation !== null;
        }

        return $this->activeRenewalNegotiation()->exists();
    }

    /**
     * Check if player has a declined/rejected renewal (blocking further offers).
     */
    public function hasDeclinedRenewal(): bool
    {
        if ($this->relationLoaded('latestRenewalNegotiation')) {
            $latest = $this->latestRenewalNegotiation;
            return $latest !== null && $latest->isBlocking();
        }

        /** @var RenewalNegotiation|null $latest */
        $latest = $this->latestRenewalNegotiation()->first();
        return $latest !== null && $latest->isBlocking();
    }

    /**
     * Check if player is currently on loan (borrowed by another team).
     */
    public function isOnLoan(): bool
    {
        return $this->activeLoan()->exists();
    }

    /**
     * Check if this player is loaned out by the user's team.
     * The player's parent_team_id matches user team but they're playing elsewhere.
     */
    public function isLoanedOut(string $userTeamId): bool
    {
        return Loan::where('game_player_id', $this->id)
            ->where('parent_team_id', $userTeamId)
            ->where('status', Loan::STATUS_ACTIVE)
            ->exists();
    }

    /**
     * Check if this player is loaned in by the user's team.
     * The player's loan_team_id matches user team.
     */
    public function isLoanedIn(string $userTeamId): bool
    {
        if ($this->relationLoaded('activeLoan')) {
            return $this->activeLoan !== null && $this->activeLoan->loan_team_id === $userTeamId;
        }

        return Loan::where('game_player_id', $this->id)
            ->where('loan_team_id', $userTeamId)
            ->where('status', Loan::STATUS_ACTIVE)
            ->exists();
    }

    /**
     * Check if player is transfer listed.
     */
    public function isTransferListed(): bool
    {
        return $this->transfer_status === self::TRANSFER_STATUS_LISTED;
    }

    /**
     * Check if player has an active loan search in progress.
     */
    public function hasActiveLoanSearch(): bool
    {
        return $this->transfer_status === self::TRANSFER_STATUS_LOAN_SEARCH;
    }

    /**
     * Check if player has an agreed transfer (waiting for window).
     */
    public function hasAgreedTransfer(): bool
    {
        if ($this->relationLoaded('transferOffers')) {
            return $this->transferOffers->contains('status', TransferOffer::STATUS_AGREED);
        }

        return $this->transferOffers()
            ->where('status', TransferOffer::STATUS_AGREED)
            ->exists();
    }

    /**
     * Get the agreed transfer offer (if any).
     */
    public function agreedTransfer(): ?TransferOffer
    {
        /** @var TransferOffer|null */
        return $this->transferOffers()
            ->where('status', TransferOffer::STATUS_AGREED)
            ->first();
    }

    /**
     * Check if player has an agreed pre-contract (leaving on free transfer at end of season).
     */
    public function hasPreContractAgreement(): bool
    {
        if ($this->relationLoaded('transferOffers')) {
            return $this->transferOffers->contains(function ($offer) {
                return $offer->status === TransferOffer::STATUS_AGREED
                    && $offer->offer_type === TransferOffer::TYPE_PRE_CONTRACT;
            });
        }

        return $this->transferOffers()
            ->where('status', TransferOffer::STATUS_AGREED)
            ->where('offer_type', TransferOffer::TYPE_PRE_CONTRACT)
            ->exists();
    }

    /**
     * Check if player's contract is expiring at end of current season.
     * Returns true if contract expires within the season (typically June 30).
     *
     * @param Carbon|null $seasonEndDate The season end date. If null, calculated from game.
     */
    public function isContractExpiring(?Carbon $seasonEndDate = null): bool
    {
        if (!$this->contract_until) {
            return false;
        }

        if ($seasonEndDate === null) {
            $seasonEndDate = $this->game->getSeasonEndDate();
        }

        return $this->contract_until->lte($seasonEndDate);
    }

    /**
     * Check if player can receive pre-contract offers.
     * Available when contract expires at end of season and no agreement exists.
     *
     * @param Carbon|null $seasonEndDate The season end date. If null, calculated from game.
     */
    public function canReceivePreContractOffers(?Carbon $seasonEndDate = null): bool
    {
        if (!$this->isContractExpiring($seasonEndDate)) {
            return false;
        }

        // Retiring players won't sign pre-contracts
        if ($this->isRetiring()) {
            return false;
        }

        // Already has a pre-contract agreement
        if ($this->hasPreContractAgreement()) {
            return false;
        }

        // Already has an agreed transfer (shouldn't happen, but be safe)
        if ($this->hasAgreedTransfer()) {
            return false;
        }

        // Contract was renewed (no longer expiring)
        if ($this->hasRenewalAgreed()) {
            return false;
        }

        // Renewal explicitly declined or rejected — contract situation is settled
        if ($this->hasDeclinedRenewal()) {
            return false;
        }

        // Active negotiation blocks pre-contract offers
        if ($this->hasActiveNegotiation()) {
            return false;
        }

        return true;
    }

    /**
     * Check if player has a pending contract renewal (new wage takes effect at end of season).
     */
    public function hasRenewalAgreed(): bool
    {
        return $this->pending_annual_wage !== null;
    }

    /**
     * Check if player can be offered a contract renewal.
     * Only for players with expiring contracts who haven't already agreed to leave.
     *
     * @param Carbon|null $seasonEndDate The season end date. If null, calculated from game.
     */
    public function canBeOfferedRenewal(?Carbon $seasonEndDate = null): bool
    {
        if (!$this->isContractExpiring($seasonEndDate)) {
            return false;
        }

        // Retiring players won't renew
        if ($this->isRetiring()) {
            return false;
        }

        // Already agreed to leave on pre-contract
        if ($this->hasPreContractAgreement()) {
            return false;
        }

        // Already has a renewal agreed
        if ($this->hasRenewalAgreed()) {
            return false;
        }

        // Renewal explicitly declined or rejected
        if ($this->hasDeclinedRenewal()) {
            return false;
        }

        // Already in active negotiation
        if ($this->hasActiveNegotiation()) {
            return false;
        }

        return true;
    }

    /**
     * Get the formatted pending wage for display.
     */
    public function getFormattedPendingWageAttribute(): ?string
    {
        if ($this->pending_annual_wage === null) {
            return null;
        }

        return Money::format($this->pending_annual_wage);
    }

    /**
     * Check if player is available for selection (not injured or suspended).
     * Uses eager-loaded suspensions relationship when available to avoid N+1.
     *
     * @param Carbon|null $gameDate Date of the match (for injury check)
     * @param string|null $competitionId Competition ID (for suspension check)
     */
    public function isAvailable(?Carbon $gameDate = null, ?string $competitionId = null): bool
    {
        if ($competitionId !== null && $this->isSuspendedInCompetition($competitionId)) {
            return false;
        }

        if ($this->injury_until && $gameDate && $this->injury_until->gt($gameDate)) {
            return false;
        }

        return true;
    }

    /**
     * Check if player is suspended for a given competition.
     * Uses eager-loaded suspensions relationship when available to avoid N+1.
     */
    public function isSuspendedInCompetition(string $competitionId): bool
    {
        if ($this->relationLoaded('suspensions')) {
            return $this->suspensions
                ->where('competition_id', $competitionId)
                ->where('matches_remaining', '>', 0)
                ->isNotEmpty();
        }

        return PlayerSuspension::isSuspended($this->id, $competitionId);
    }

    /**
     * Get matches remaining in suspension for a competition.
     * Uses eager-loaded suspensions relationship when available to avoid N+1.
     */
    public function getSuspensionMatchesRemaining(string $competitionId): int
    {
        if ($this->relationLoaded('suspensions')) {
            return $this->suspensions
                ->where('competition_id', $competitionId)
                ->where('matches_remaining', '>', 0)
                ->first()
                ->matches_remaining ?? 0;
        }

        return PlayerSuspension::getMatchesRemaining($this->id, $competitionId);
    }

    /**
     * Check if player is injured on a given date.
     */
    public function isInjured(?Carbon $date = null): bool
    {
        if ($this->injury_until === null) {
            return false;
        }

        $checkDate = $date ?? $this->game->current_date;
        return $this->injury_until->gt($checkDate);
    }

    /**
     * Get the unavailability reason if player is not available.
     *
     * @param Carbon|null $gameDate Date of the match (for injury check)
     * @param string|null $competitionId Competition ID (for suspension check)
     */
    public function getUnavailabilityReason(?Carbon $gameDate = null, ?string $competitionId = null): ?string
    {
        if ($competitionId !== null && $this->isSuspendedInCompetition($competitionId)) {
            $remaining = $this->getSuspensionMatchesRemaining($competitionId);
            return trans_choice('squad.suspended_matches', $remaining, ['count' => $remaining]);
        }

        if ($this->isInjured($gameDate)) {
            $translationKey = InjuryService::INJURY_TRANSLATION_MAP[$this->injury_type] ?? null;
            $injuryName = $translationKey ? __($translationKey) : __('squad.injured_generic');

            $checkDate = $gameDate ?? $this->game->current_date;
            $weeksRemaining = (int) ceil($checkDate->diffInDays($this->injury_until) / 7);
            $duration = trans_choice('squad.injury_weeks', max(1, $weeksRemaining), ['count' => max(1, $weeksRemaining)]);

            return "$injuryName ($duration)";
        }

        return null;
    }

    /**
     * Get player's age based on the game's current date (not wall-clock today).
     */
    public function getAgeAttribute(): int
    {
        $referenceDate = $this->game->current_date;

        return (int) $this->player->date_of_birth->diffInYears($referenceDate);
    }

    /**
     * Get player's name from the reference Player model.
     */
    public function getNameAttribute(): string
    {
        return $this->player->name;
    }

    /**
     * Get player's nationality from the reference Player model.
     */
    public function getNationalityAttribute(): ?array
    {
        return $this->player->nationality;
    }

    /**
     * Group position into category for display.
     */
    public function getPositionGroupAttribute(): string
    {
        return match ($this->position) {
            'Goalkeeper' => 'Goalkeeper',
            'Centre-Back', 'Left-Back', 'Right-Back' => 'Defender',
            'Defensive Midfield', 'Central Midfield', 'Attacking Midfield',
            'Left Midfield', 'Right Midfield' => 'Midfielder',
            'Left Winger', 'Right Winger', 'Centre-Forward', 'Second Striker' => 'Forward',
            default => 'Midfielder',
        };
    }

    /**
     * Calculate overall score from 4 attributes.
     * Technical + Physical (game-specific) + Fitness + Morale
     */
    public function getOverallScoreAttribute(): int
    {
        return (int) round(
            $this->current_technical_ability * 0.35 +
            $this->current_physical_ability * 0.35 +
            $this->fitness * 0.15 +
            $this->morale * 0.15
        );
    }

    /**
     * Get current technical ability (game-specific or fallback to Player reference).
     */
    public function getCurrentTechnicalAbilityAttribute(): int
    {
        return $this->game_technical_ability ?? $this->player->technical_ability;
    }

    /**
     * Get current physical ability (game-specific or fallback to Player reference).
     */
    public function getCurrentPhysicalAbilityAttribute(): int
    {
        return $this->game_physical_ability ?? $this->player->physical_ability;
    }

    /**
     * Get technical ability - uses game-specific value if set.
     */
    public function getTechnicalAbilityAttribute(): int
    {
        return $this->current_technical_ability;
    }

    /**
     * Get physical ability - uses game-specific value if set.
     */
    public function getPhysicalAbilityAttribute(): int
    {
        return $this->current_physical_ability;
    }

    /**
     * Get potential display range for UI.
     */
    public function getPotentialRangeAttribute(): string
    {
        if ($this->potential_low && $this->potential_high) {
            return "{$this->potential_low}-{$this->potential_high}";
        }
        return '?';
    }

    /**
     * Get formatted annual wage for display (e.g., "€2.5M", "€450K").
     */
    public function getFormattedWageAttribute(): string
    {
        return Money::format($this->annual_wage);
    }

    public function getFormattedMarketValueAttribute(): string
    {
        return Money::format($this->market_value_cents);
    }

    /**
     * Get annual wage in euros (not cents).
     */
    public function getAnnualWageEurosAttribute(): int
    {
        return (int) ($this->annual_wage / 100);
    }

    /**
     * Get contract expiry year for display.
     */
    public function getContractExpiryYearAttribute(): ?int
    {
        return $this->contract_until?->year;
    }

    /**
     * Get development status based on age (growing/peak/declining).
     */
    public function getDevelopmentStatusAttribute(): string
    {
        $age = $this->age;
        if ($age <= 23) {
            return 'growing';
        }
        if ($age <= 28) {
            return 'peak';
        }
        return 'declining';
    }

    /**
     * Get localized position abbreviation (PO, CT, MC, etc.).
     */
    public function getPositionAbbreviationAttribute(): string
    {
        return PositionMapper::toAbbreviation($this->position);
    }

    /**
     * Get localized display name for this player's position.
     */
    public function getPositionNameAttribute(): string
    {
        return PositionMapper::toDisplayName($this->position);
    }

    /**
     * Get position display data including abbreviation and CSS colors.
     *
     * @return array{abbreviation: string, bg: string, text: string}
     */
    public function getPositionDisplayAttribute(): array
    {
        return PositionMapper::getPositionDisplay($this->position);
    }

    /**
     * Get primary nationality flag data (first nationality only).
     *
     * @return array{name: string, code: string}|null
     */
    public function getNationalityFlagAttribute(): ?array
    {
        $nationalities = $this->nationality ?? [];

        if (empty($nationalities)) {
            return null;
        }

        $code = CountryCodeMapper::toCode($nationalities[0]);

        if ($code === null) {
            return null;
        }

        return [
            'name' => $nationalities[0],
            'code' => $code,
        ];
    }
}
