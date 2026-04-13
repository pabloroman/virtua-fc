<?php

namespace App\Models;

use App\Modules\Player\PlayerAge;
use App\Modules\Player\Services\InjuryService;
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
 * @property int $appearances
 * @property int $goals
 * @property int $own_goals
 * @property int $assists
 * @property int $yellow_cards
 * @property int $red_cards
 * @property int|null $game_technical_ability
 * @property int|null $game_physical_ability
 * @property int $tier
 * @property int|null $potential
 * @property int|null $potential_low
 * @property int|null $potential_high
 * @property int $season_appearances
 * @property int $goals_conceded
 * @property int $clean_sheets
 * @property int $annual_wage
 * @property int|null $pending_annual_wage
 * @property int $durability
 * @property string|null $retiring_at_season
 * @property int|null $number
 * @property-read \App\Models\Loan|null $activeLoan
 * @property-read int|null $active_offers_count
 * @property-read \App\Models\RenewalNegotiation|null $activeRenewalNegotiation
 * @property-read \App\Models\Game $game
 * @property-read int $annual_wage_euros
 * @property-read int|null $contract_expiry_year
 * @property-read int $current_physical_ability
 * @property-read int $current_technical_ability
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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GamePlayer whereTeamId($value)
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
        'secondary_positions',
        'market_value',
        'market_value_cents',
        'contract_until',
        'annual_wage',
        'pending_annual_wage',
        'durability',
        'game_technical_ability',
        'game_physical_ability',
        'tier',
        'potential',
        'potential_low',
        'potential_high',
        'retiring_at_season',
    ];

    protected $casts = [
        'number' => 'integer',
        'secondary_positions' => 'array',
        'market_value_cents' => 'integer',
        'contract_until' => 'date',
        'annual_wage' => 'integer',
        'pending_annual_wage' => 'integer',
        'durability' => 'integer',
        // Development fields
        'game_technical_ability' => 'integer',
        'game_physical_ability' => 'integer',
        'tier' => 'integer',
        'potential' => 'integer',
        'potential_low' => 'integer',
        'potential_high' => 'integer',
    ];

    /**
     * All positions this player can play.
     *
     * Merges the primary position with any secondary positions, de-duplicated
     * and capped at 3 total. The primary position always comes first.
     *
     * @return string[]
     */
    public function getPositionsAttribute(): array
    {
        $secondary = $this->secondary_positions ?? [];
        $positions = array_values(array_unique(array_merge([$this->position], $secondary)));

        return array_slice($positions, 0, 3);
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

    /**
     * Sparse satellite holding the per-matchday hot-write columns
     * (fitness, morale, injury, match stats). Only "active" players
     * have a row — see {@see \App\Modules\Player\Support\GamePlayerScopeResolver}.
     *
     * Read access is mediated by the get*Attribute() delegates below so
     * existing call sites that use `$player->fitness`, `$player->goals`
     * etc. work transparently. Write paths must update the satellite
     * directly via `GamePlayerMatchState` queries — never via
     * `$player->fitness = X`.
     */
    public function matchState(): HasOne
    {
        return $this->hasOne(GamePlayerMatchState::class, 'game_player_id');
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

    public function transferListing(): HasOne
    {
        return $this->hasOne(TransferListing::class);
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
     * Get the latest renewal negotiation.
     */
    public function latestRenewalNegotiation(): HasOne
    {
        return $this->hasOne(RenewalNegotiation::class)->latest('id');
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
     * Check if player has a renewal negotiation cooldown active.
     */
    public function hasRenewalCooldown($currentDate): bool
    {
        return RenewalNegotiation::hasRenewalCooldown($this->id, $currentDate);
    }

    /**
     * Check if player is currently on loan (borrowed by another team).
     */
    public function isOnLoan(): bool
    {
        if ($this->relationLoaded('activeLoan')) {
            return $this->activeLoan !== null;
        }

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
     * Scope: players owned by a team.
     *
     * A team "owns" a player if either:
     *   - the player is currently at the team and is not a borrowed (loaned-in) player, OR
     *   - the player is loaned out from the team (an active Loan exists with parent_team_id = $teamId).
     *
     * Loaned-in players (currently at the team but belonging to another club)
     * are explicitly excluded. Use this for contract-related operations
     * (renewals, pre-contracts) where the owning club retains authority even
     * while a player is away on loan.
     */
    public function scopeOwnedByTeam($query, string $teamId)
    {
        return $query->where(function ($q) use ($teamId) {
            $q->where(function ($present) use ($teamId) {
                $present->where('team_id', $teamId)
                    ->whereDoesntHave('activeLoan');
            })->orWhereHas('activeLoan', fn ($loanQuery) => $loanQuery->where('parent_team_id', $teamId));
        });
    }

    /**
     * Check if player is transfer listed.
     */
    public function isTransferListed(): bool
    {
        if ($this->relationLoaded('transferListing')) {
            return $this->transferListing?->status === TransferListing::STATUS_LISTED;
        }

        return $this->transferListing()->where('status', TransferListing::STATUS_LISTED)->exists();
    }

    /**
     * Check if player has an active loan search in progress.
     */
    public function hasActiveLoanSearch(): bool
    {
        if ($this->relationLoaded('transferListing')) {
            return $this->transferListing?->status === TransferListing::STATUS_LOAN_SEARCH;
        }

        return $this->transferListing()->where('status', TransferListing::STATUS_LOAN_SEARCH)->exists();
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
    public function canBeOfferedRenewal(?Carbon $seasonEndDate = null, ?Carbon $currentDate = null): bool
    {
        if (!$this->isContractExpiring($seasonEndDate)) {
            return false;
        }

        // Loaned-in players belong to another club — we can't renew them.
        // Loaned-out players (ours, playing elsewhere) can still be renewed.
        if ($this->isLoanedIn($this->game->team_id)) {
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

        // Renewal explicitly declined by club
        if ($this->hasDeclinedRenewal()) {
            return false;
        }

        // Cooldown after player rejection (wait one matchday)
        if ($currentDate && $this->hasRenewalCooldown($currentDate)) {
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

        if ($this->injury_until && $gameDate && $this->injury_until->gte($gameDate)) {
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
        return $this->injury_until->gte($checkDate);
    }

    /**
     * Get the unavailability reason if player is not available.
     *
     * @param Carbon|null $gameDate Date of the match (for injury check)
     * @param string|null $competitionId Competition ID (for suspension check)
     */
    public function getUnavailabilityReason(
        ?Carbon $gameDate = null,
        ?string $competitionId = null,
    ): ?string {
        if ($competitionId !== null && $this->isSuspendedInCompetition($competitionId)) {
            $remaining = $this->getSuspensionMatchesRemaining($competitionId);
            return trans_choice('squad.suspended_matches', $remaining, ['count' => $remaining]);
        }

        if ($this->isInjured($gameDate)) {
            $translationKey = InjuryService::INJURY_TRANSLATION_MAP[$this->injury_type] ?? null;
            $injuryName = $translationKey ? __($translationKey) : __('squad.injured_generic');

            if ($this->injury_until) {
                $returnDate = __('squad.injury_return_date', [
                    'date' => $this->injury_until->translatedFormat('j M Y'),
                ]);

                return "$injuryName ($returnDate)";
            }

            return $injuryName;
        }

        return null;
    }

    /**
     * Get player's age based on a reference date (typically the game's current date).
     */
    public function age(Carbon|\DateTimeInterface $currentDate): int
    {
        return (int) $this->player->date_of_birth->diffInYears($currentDate);
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
     * Technical + Physical (game-specific) + Energy + Morale
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
    public function developmentStatus(Carbon|\DateTimeInterface $currentDate): string
    {
        return PlayerAge::developmentStatus($this->age($currentDate));
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

    // ==========================================================================
    // Match-state query scopes
    //
    // These scopes hide the satellite join so callers never reference
    // the table name or column names directly.
    // ==========================================================================

    private static array $validMatchStatColumns;

    private static function validMatchStatColumns(): array
    {
        return self::$validMatchStatColumns ??= array_keys(GamePlayerMatchState::DEFAULTS);
    }

    private static function assertValidMatchStatColumn(string $column): void
    {
        if (! in_array($column, self::validMatchStatColumns(), true)) {
            throw new \InvalidArgumentException("Invalid match stat column: {$column}");
        }
    }

    /**
     * INNER JOIN the satellite table (only players with a satellite row).
     */
    public function scopeJoinMatchState($query)
    {
        return $query
            ->join('game_player_match_state', 'game_players.id', '=', 'game_player_match_state.game_player_id')
            ->select('game_players.*');
    }

    /**
     * LEFT JOIN the satellite table (includes pool players without a row).
     */
    public function scopeLeftJoinMatchState($query)
    {
        return $query
            ->leftJoin('game_player_match_state', 'game_players.id', '=', 'game_player_match_state.game_player_id')
            ->select('game_players.*');
    }

    /**
     * Order by a column on the satellite table.
     */
    public function scopeOrderByMatchStat($query, string $column, string $direction = 'desc')
    {
        self::assertValidMatchStatColumn($column);

        return $query->orderBy("game_player_match_state.{$column}", $direction);
    }

    /**
     * Filter by a column on the satellite table.
     */
    public function scopeWhereMatchStat($query, string $column, string $operator, $value = null)
    {
        self::assertValidMatchStatColumn($column);

        return $query->where("game_player_match_state.{$column}", $operator, $value);
    }

    /**
     * Filter by a satellite column being not null.
     */
    public function scopeWhereMatchStatNotNull($query, string $column)
    {
        self::assertValidMatchStatColumn($column);

        return $query->whereNotNull("game_player_match_state.{$column}");
    }

    /**
     * Exclude injured players (injury_until is null or in the past).
     * Joins the satellite table if not already joined.
     */
    public function scopeNotInjuredOn($query, Carbon $date)
    {
        // Check if the satellite join is already present
        $joins = $query->getQuery()->joins ?? [];
        $alreadyJoined = collect($joins)->contains(fn ($join) => $join->table === 'game_player_match_state');

        if (! $alreadyJoined) {
            $query->join('game_player_match_state', 'game_players.id', '=', 'game_player_match_state.game_player_id')
                ->select('game_players.*');
        }

        return $query->where(function ($q) use ($date) {
            $q->whereNull('game_player_match_state.injury_until')
                ->orWhere('game_player_match_state.injury_until', '<', $date->toDateString());
        });
    }

    // ==========================================================================
    // Match-state delegates
    //
    // The 13 hot-write columns (fitness, morale, injury, match stats) live on
    // {@see GamePlayerMatchState}. The accessors below let existing read sites
    // keep using `$player->fitness` etc. without knowing about the satellite.
    //
    // Read priority:
    //   1. In-memory override (`isDirty`) — e.g. CompetitionViewService
    //      decorating a model with per-competition tallies.
    //   2. Satellite (when eager-loaded) — preferred to avoid N+1.
    //   3. Lazy-load satellite / default.
    //
    // To avoid N+1 in hot paths, callers should `with('matchState')` when
    // loading collections. The match simulator, squad service, lineup loader
    // and dashboard all do this.
    // ==========================================================================

    /**
     * Read a match-state value through the standard priority chain.
     */
    private function matchStateValue(string $column, mixed $default): mixed
    {
        // 1. In-memory override (e.g. decoration for display)
        if ($this->isDirty($column)) {
            return $this->attributes[$column];
        }

        // 2. Satellite (authoritative when eager-loaded)
        if ($this->relationLoaded('matchState') && $this->matchState !== null) {
            return $this->matchState->{$column};
        }

        // 3. Lazy-load satellite (or fall back to default)
        return $this->matchState?->{$column} ?? $default;
    }

    public function getFitnessAttribute(): int
    {
        return (int) $this->matchStateValue('fitness', GamePlayerMatchState::DEFAULTS['fitness']);
    }

    public function getMoraleAttribute(): int
    {
        return (int) $this->matchStateValue('morale', GamePlayerMatchState::DEFAULTS['morale']);
    }

    public function getInjuryUntilAttribute(): ?Carbon
    {
        $value = $this->matchStateValue('injury_until', null);
        if ($value === null) {
            return null;
        }

        return $value instanceof Carbon ? $value : Carbon::parse($value);
    }

    public function getInjuryTypeAttribute(): ?string
    {
        return $this->matchStateValue('injury_type', null);
    }

    public function getAppearancesAttribute(): int
    {
        return (int) $this->matchStateValue('appearances', GamePlayerMatchState::DEFAULTS['appearances']);
    }

    public function getSeasonAppearancesAttribute(): int
    {
        return (int) $this->matchStateValue('season_appearances', GamePlayerMatchState::DEFAULTS['season_appearances']);
    }

    public function getGoalsAttribute(): int
    {
        return (int) $this->matchStateValue('goals', GamePlayerMatchState::DEFAULTS['goals']);
    }

    public function getOwnGoalsAttribute(): int
    {
        return (int) $this->matchStateValue('own_goals', GamePlayerMatchState::DEFAULTS['own_goals']);
    }

    public function getAssistsAttribute(): int
    {
        return (int) $this->matchStateValue('assists', GamePlayerMatchState::DEFAULTS['assists']);
    }

    public function getYellowCardsAttribute(): int
    {
        return (int) $this->matchStateValue('yellow_cards', GamePlayerMatchState::DEFAULTS['yellow_cards']);
    }

    public function getRedCardsAttribute(): int
    {
        return (int) $this->matchStateValue('red_cards', GamePlayerMatchState::DEFAULTS['red_cards']);
    }

    public function getGoalsConcededAttribute(): int
    {
        return (int) $this->matchStateValue('goals_conceded', GamePlayerMatchState::DEFAULTS['goals_conceded']);
    }

    public function getCleanSheetsAttribute(): int
    {
        return (int) $this->matchStateValue('clean_sheets', GamePlayerMatchState::DEFAULTS['clean_sheets']);
    }
}
