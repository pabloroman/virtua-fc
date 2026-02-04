<?php

namespace App\Models;

use App\Support\CountryCodeMapper;
use App\Support\Money;
use App\Support\PositionMapper;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GamePlayer extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'market_value_cents' => 'integer',
        'contract_until' => 'date',
        'annual_wage' => 'integer',
        'pending_annual_wage' => 'integer',
        'joined_on' => 'date',
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

    /**
     * Get active (pending, non-expired) transfer offers for this player.
     */
    public function activeOffers(): HasMany
    {
        return $this->transferOffers()
            ->where('status', TransferOffer::STATUS_PENDING)
            ->where('expires_at', '>=', now());
    }

    /**
     * Check if player is transfer listed.
     */
    public function isTransferListed(): bool
    {
        return $this->transfer_status === self::TRANSFER_STATUS_LISTED;
    }

    /**
     * Check if player has an agreed transfer (waiting for window).
     */
    public function hasAgreedTransfer(): bool
    {
        return $this->transferOffers()
            ->where('status', TransferOffer::STATUS_AGREED)
            ->exists();
    }

    /**
     * Get the agreed transfer offer (if any).
     */
    public function agreedTransfer(): ?TransferOffer
    {
        return $this->transferOffers()
            ->where('status', TransferOffer::STATUS_AGREED)
            ->first();
    }

    /**
     * Check if player has an agreed pre-contract (leaving on free transfer at end of season).
     */
    public function hasPreContractAgreement(): bool
    {
        return $this->transferOffers()
            ->where('status', TransferOffer::STATUS_AGREED)
            ->where('offer_type', TransferOffer::TYPE_PRE_CONTRACT)
            ->exists();
    }

    /**
     * Get the agreed pre-contract offer (if any).
     */
    public function agreedPreContract(): ?TransferOffer
    {
        return $this->transferOffers()
            ->where('status', TransferOffer::STATUS_AGREED)
            ->where('offer_type', TransferOffer::TYPE_PRE_CONTRACT)
            ->first();
    }

    /**
     * Check if player's contract is expiring at end of current season.
     * Returns true if contract expires within the season (typically June 30).
     */
    public function isContractExpiring(): bool
    {
        if (!$this->contract_until) {
            return false;
        }

        $game = $this->game;
        if (!$game) {
            return false;
        }

        // Get the current season's end date (June 30 of season year)
        $seasonYear = (int) $game->season;
        $seasonEndDate = Carbon::createFromDate($seasonYear + 1, 6, 30);

        return $this->contract_until->lte($seasonEndDate);
    }

    /**
     * Check if player can receive pre-contract offers.
     * Available when contract expires at end of season and no agreement exists.
     */
    public function canReceivePreContractOffers(): bool
    {
        if (!$this->isContractExpiring()) {
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
     */
    public function canBeOfferedRenewal(): bool
    {
        if (!$this->isContractExpiring()) {
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
     *
     * @param Carbon|null $gameDate Date of the match (for injury check)
     * @param string|null $competitionId Competition ID (for suspension check)
     */
    public function isAvailable(?Carbon $gameDate = null, ?string $competitionId = null): bool
    {
        // Check competition-specific suspension
        if ($competitionId !== null && PlayerSuspension::isSuspended($this->id, $competitionId)) {
            return false;
        }

        // Check injury
        if ($this->injury_until && $gameDate && $this->injury_until->gt($gameDate)) {
            return false;
        }

        return true;
    }

    /**
     * Check if player is suspended for a given competition.
     */
    public function isSuspendedInCompetition(string $competitionId): bool
    {
        return PlayerSuspension::isSuspended($this->id, $competitionId);
    }

    /**
     * Get matches remaining in suspension for a competition.
     */
    public function getSuspensionMatchesRemaining(string $competitionId): int
    {
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

        $checkDate = $date ?? now();
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
            return "Suspended ({$remaining} match" . ($remaining > 1 ? 'es' : '') . ")";
        }

        if ($this->isInjured($gameDate)) {
            return $this->injury_type ? "{$this->injury_type}" : "Injured";
        }

        return null;
    }

    /**
     * Get player's age from the reference Player model.
     */
    public function getAgeAttribute(): int
    {
        return $this->player->age;
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
            ($this->current_technical_ability +
             $this->current_physical_ability +
             $this->fitness +
             $this->morale) / 4
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
     * Get 2-letter position abbreviation (GK, DF, MF, FW).
     */
    public function getPositionAbbreviationAttribute(): string
    {
        return PositionMapper::toAbbreviation($this->position);
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
