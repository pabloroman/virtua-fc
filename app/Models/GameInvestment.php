<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $game_id
 * @property int $season
 * @property int $available_surplus
 * @property int $youth_academy_amount
 * @property int $youth_academy_tier
 * @property int $medical_amount
 * @property int $medical_tier
 * @property int $scouting_amount
 * @property int $scouting_tier
 * @property int $facilities_amount
 * @property int $facilities_tier
 * @property int $transfer_budget
 * @property-read \App\Models\Game $game
 * @property-read float $facilities_multiplier
 * @property-read string $formatted_available_surplus
 * @property-read string $formatted_facilities_amount
 * @property-read string $formatted_medical_amount
 * @property-read string $formatted_scouting_amount
 * @property-read string $formatted_total_infrastructure
 * @property-read string $formatted_transfer_budget
 * @property-read string $formatted_youth_academy_amount
 * @property-read int $total_infrastructure
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameInvestment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameInvestment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameInvestment query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameInvestment whereAvailableSurplus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameInvestment whereFacilitiesAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameInvestment whereFacilitiesTier($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameInvestment whereGameId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameInvestment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameInvestment whereMedicalAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameInvestment whereMedicalTier($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameInvestment whereScoutingAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameInvestment whereScoutingTier($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameInvestment whereSeason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameInvestment whereTransferBudget($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameInvestment whereYouthAcademyAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameInvestment whereYouthAcademyTier($value)
 * @mixin \Eloquent
 */
class GameInvestment extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'game_id',
        'season',
        'available_surplus',
        'youth_academy_amount',
        'youth_academy_tier',
        'medical_amount',
        'medical_tier',
        'scouting_amount',
        'scouting_tier',
        'facilities_amount',
        'facilities_tier',
        'transfer_budget',
    ];

    /**
     * Investment tier thresholds (in cents).
     */
    public const TIER_THRESHOLDS = [
        'youth_academy' => [
            1 => 50_000_000,      // €500K
            2 => 200_000_000,     // €2M
            3 => 800_000_000,     // €8M
            4 => 2_000_000_000,   // €20M
        ],
        'medical' => [
            1 => 30_000_000,      // €300K
            2 => 150_000_000,     // €1.5M
            3 => 500_000_000,     // €5M
            4 => 1_000_000_000,   // €10M
        ],
        'scouting' => [
            1 => 20_000_000,      // €200K
            2 => 100_000_000,     // €1M
            3 => 400_000_000,     // €4M
            4 => 1_000_000_000,   // €10M
        ],
        'facilities' => [
            1 => 50_000_000,      // €500K
            2 => 300_000_000,     // €3M
            3 => 1_000_000_000,   // €10M
            4 => 2_500_000_000,   // €25M
        ],
    ];

    /**
     * Default investment tiers by club reputation level.
     */
    public const DEFAULT_TIERS_BY_REPUTATION = [
        'elite' => ['youth_academy' => 4, 'medical' => 4, 'scouting' => 4, 'facilities' => 4],
        'contenders' => ['youth_academy' => 3, 'medical' => 4, 'scouting' => 4, 'facilities' => 3],
        'continental' => ['youth_academy' => 3, 'medical' => 3, 'scouting' => 3, 'facilities' => 3],
        'established' => ['youth_academy' => 2, 'medical' => 3, 'scouting' => 3, 'facilities' => 2],
        'modest' => ['youth_academy' => 1, 'medical' => 2, 'scouting' => 2, 'facilities' => 2],
        'professional' => ['youth_academy' => 1, 'medical' => 2, 'scouting' => 2, 'facilities' => 1],
        'local' => ['youth_academy' => 1, 'medical' => 1, 'scouting' => 1, 'facilities' => 1],
    ];

    /**
     * Minimum required investment for professional leagues (Tier 1 in all areas).
     */
    public const MINIMUM_TOTAL_INVESTMENT = 150_000_000; // €1.5M in cents

    /**
     * Maximum investment ceilings per area (Tier 4 threshold - no benefit beyond this).
     */
    public const INVESTMENT_CEILINGS = [
        'youth_academy' => 2_000_000_000,   // €20M
        'medical' => 1_000_000_000,         // €10M
        'scouting' => 1_000_000_000,        // €10M
        'facilities' => 2_500_000_000,      // €25M
    ];

    /**
     * Facilities multiplier by tier.
     */
    public const FACILITIES_MULTIPLIER = [
        1 => 1.0,
        2 => 1.15,
        3 => 1.35,
        4 => 1.6,
    ];

    protected $casts = [
        'season' => 'integer',
        'available_surplus' => 'integer',
        'youth_academy_amount' => 'integer',
        'youth_academy_tier' => 'integer',
        'medical_amount' => 'integer',
        'medical_tier' => 'integer',
        'scouting_amount' => 'integer',
        'scouting_tier' => 'integer',
        'facilities_amount' => 'integer',
        'facilities_tier' => 'integer',
        'transfer_budget' => 'integer',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /**
     * Calculate tier from investment amount.
     */
    public static function calculateTier(string $area, int $amount): int
    {
        $thresholds = self::TIER_THRESHOLDS[$area] ?? [];

        for ($tier = 4; $tier >= 1; $tier--) {
            if ($amount >= $thresholds[$tier]) {
                return $tier;
            }
        }

        return 0; // Below minimum
    }

    /**
     * Get facilities multiplier for matchday revenue.
     */
    public function getFacilitiesMultiplierAttribute(): float
    {
        return self::FACILITIES_MULTIPLIER[$this->facilities_tier] ?? 1.0;
    }

    /**
     * Get total infrastructure investment.
     */
    public function getTotalInfrastructureAttribute(): int
    {
        return $this->youth_academy_amount
            + $this->medical_amount
            + $this->scouting_amount
            + $this->facilities_amount;
    }

    /**
     * Check if minimum investment requirements are met.
     */
    public function meetsMinimumRequirements(): bool
    {
        return $this->youth_academy_tier >= 1
            && $this->medical_tier >= 1
            && $this->scouting_tier >= 1
            && $this->facilities_tier >= 1;
    }

    /**
     * Get default investment tiers for a reputation level, reduced if surplus is insufficient.
     */
    public static function defaultTiersForReputation(string $reputation, int $availableSurplus): array
    {
        $tiers = self::DEFAULT_TIERS_BY_REPUTATION[$reputation]
            ?? self::DEFAULT_TIERS_BY_REPUTATION['professional'];

        while (true) {
            $totalCost = 0;
            foreach ($tiers as $area => $tier) {
                $totalCost += self::TIER_THRESHOLDS[$area][$tier];
            }

            if ($totalCost <= $availableSurplus) {
                return $tiers;
            }

            // All already at minimum — can't reduce further
            if (max($tiers) <= 1) {
                return $tiers;
            }

            // Uniformly reduce all tiers by 1 (minimum 1)
            $tiers = array_map(fn (int $t) => max(1, $t - 1), $tiers);
        }
    }

    // Formatted accessors
    public function getFormattedAvailableSurplusAttribute(): string
    {
        return Money::format($this->available_surplus);
    }

    public function getFormattedTotalInfrastructureAttribute(): string
    {
        return Money::format($this->total_infrastructure);
    }

    public function getFormattedTransferBudgetAttribute(): string
    {
        return Money::format($this->transfer_budget);
    }

    public function getFormattedYouthAcademyAmountAttribute(): string
    {
        return Money::format($this->youth_academy_amount);
    }

    public function getFormattedMedicalAmountAttribute(): string
    {
        return Money::format($this->medical_amount);
    }

    public function getFormattedScoutingAmountAttribute(): string
    {
        return Money::format($this->scouting_amount);
    }

    public function getFormattedFacilitiesAmountAttribute(): string
    {
        return Money::format($this->facilities_amount);
    }
}
