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
 * @property int $transfer_budget
 * @property array<string, int>|null $staged_downgrades
 * @property-read \App\Models\Game $game
 * @property-read string $formatted_available_surplus
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
        'transfer_budget',
        'staged_downgrades',
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
    ];

    /**
     * Tier-0 baseline thresholds (in cents). Only available to clubs competing
     * in Primera RFEF (competition tier 3). These represent the minimum
     * operational spend a third-tier club realistically shoulders — dropping
     * the infrastructure floor from €1.5M to €500K without letting it hit zero.
     */
    public const TIER_0_THRESHOLDS = [
        'youth_academy' => 17_000_000,  // €170K
        'medical'       =>  9_000_000,  // €90K
        'scouting'      =>  5_000_000,  // €50K
    ];

    /**
     * Competition tiers where tier 0 is an accepted investment floor.
     */
    public const TIER_0_COMPETITION_TIERS = [3];

    /**
     * Default investment tiers by club reputation level.
     */
    public const DEFAULT_TIERS_BY_REPUTATION = [
        'elite' => ['youth_academy' => 4, 'medical' => 4, 'scouting' => 4],
        'continental' => ['youth_academy' => 3, 'medical' => 3, 'scouting' => 3],
        'established' => ['youth_academy' => 2, 'medical' => 3, 'scouting' => 3],
        'modest' => ['youth_academy' => 1, 'medical' => 2, 'scouting' => 2],
        'local' => ['youth_academy' => 1, 'medical' => 1, 'scouting' => 1],
    ];

    /**
     * Maximum investment ceilings per area (Tier 4 threshold - no benefit beyond this).
     */
    public const INVESTMENT_CEILINGS = [
        'youth_academy' => 2_000_000_000,   // €20M
        'medical' => 1_000_000_000,         // €10M
        'scouting' => 1_000_000_000,        // €10M
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
        'transfer_budget' => 'integer',
        'staged_downgrades' => 'array',
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

        return 0; // Below tier 1 — may still be a valid tier-0 allocation for Primera RFEF
    }

    /**
     * Whether tier 0 is an accepted investment floor for the given competition tier.
     */
    public static function allowsTierZero(int $competitionTier): bool
    {
        return in_array($competitionTier, self::TIER_0_COMPETITION_TIERS, true);
    }

    /**
     * Minimum tier users can allocate at for the given competition tier.
     */
    public static function minimumTierForCompetitionTier(int $competitionTier): int
    {
        return self::allowsTierZero($competitionTier) ? 0 : 1;
    }

    /**
     * Minimum per-area amount users must allocate for the given competition tier.
     *
     * @return array<string, int>
     */
    public static function minimumAmountsForCompetitionTier(int $competitionTier): array
    {
        if (self::allowsTierZero($competitionTier)) {
            return self::TIER_0_THRESHOLDS;
        }

        return [
            'youth_academy' => self::TIER_THRESHOLDS['youth_academy'][1],
            'medical'       => self::TIER_THRESHOLDS['medical'][1],
            'scouting'      => self::TIER_THRESHOLDS['scouting'][1],
        ];
    }

    /**
     * Minimum total infrastructure spend guaranteed for the given competition tier.
     * Used by the subsidy calculation to size the floor below which public
     * subsidies kick in.
     */
    public static function minimumInfrastructureForCompetitionTier(int $competitionTier): int
    {
        return array_sum(self::minimumAmountsForCompetitionTier($competitionTier));
    }

    /**
     * Tier threshold map for the given competition tier. Primera RFEF (tier 3)
     * merges in the tier-0 baseline so the UI can render T0 as a selectable
     * rung alongside T1–T4.
     *
     * @return array<string, array<int, int>>
     */
    public static function thresholdsForCompetitionTier(int $competitionTier): array
    {
        if (! self::allowsTierZero($competitionTier)) {
            return self::TIER_THRESHOLDS;
        }

        $merged = [];
        foreach (self::TIER_THRESHOLDS as $area => $tiers) {
            $merged[$area] = [0 => self::TIER_0_THRESHOLDS[$area]] + $tiers;
        }

        return $merged;
    }

    /**
     * Get total infrastructure investment.
     */
    public function getTotalInfrastructureAttribute(): int
    {
        return $this->youth_academy_amount
            + $this->medical_amount
            + $this->scouting_amount;
    }

    /**
     * Check if minimum investment requirements are met.
     */
    public function meetsMinimumRequirements(): bool
    {
        return $this->youth_academy_tier >= 1
            && $this->medical_tier >= 1
            && $this->scouting_tier >= 1;
    }

    /**
     * Get default investment tiers for a reputation level, trimmed so the spend
     * leaves a transfer-budget reserve (config `finances.default_infra_transfer_reserve`).
     *
     * Starts from the reputation default and reduces the most expensive area one
     * tier at a time until the infrastructure spend fits within the surplus minus
     * the reserve. When $minTier is 0 (Primera RFEF), reduction can bottom out at
     * tier 0 using the tier-0 baseline thresholds.
     */
    public static function defaultTiersForReputation(string $reputation, int $availableSurplus, int $minTier = 1): array
    {
        $tiers = self::DEFAULT_TIERS_BY_REPUTATION[$reputation]
            ?? self::DEFAULT_TIERS_BY_REPUTATION['modest'];

        // Reserve a share of the surplus for transfers so infrastructure can't
        // swallow the whole budget. Without it, a club whose surplus just clears
        // its reputation default spends it all on infrastructure and is left with
        // almost nothing to spend — so the highest-revenue club in a division can
        // end up with the smallest transfer budget.
        $reserve = (float) config('finances.default_infra_transfer_reserve', 0.45);
        $infraBudget = (int) floor($availableSurplus * (1 - $reserve));

        return self::trimTiersToBudget($tiers, $infraBudget, $minTier);
    }

    /**
     * Trim a tier selection — most expensive area first — until its total spend
     * fits within $infraBudget, never dropping an area below $minTier. Reducing
     * one area at a time (most expensive first) lets infra settle near the cap
     * instead of collapsing all four tiers in a single step. Once every area
     * sits at $minTier the floor wins: minimum infra is mandatory even if it
     * exceeds the budget. Reused for both the reputation default and carrying
     * last season's picks forward into a (possibly smaller) new-season surplus.
     *
     * @param  array<string, int>  $tiers
     * @return array<string, int>
     */
    public static function trimTiersToBudget(array $tiers, int $infraBudget, int $minTier = 1): array
    {
        $costFor = static function (string $area, int $tier): int {
            if ($tier === 0) {
                return self::TIER_0_THRESHOLDS[$area];
            }

            return self::TIER_THRESHOLDS[$area][$tier];
        };

        while (true) {
            $totalCost = 0;
            foreach ($tiers as $area => $tier) {
                $totalCost += $costFor($area, $tier);
            }

            if ($totalCost <= $infraBudget) {
                return $tiers;
            }

            $reduceArea = null;
            $reduceCost = -1;
            foreach ($tiers as $area => $tier) {
                if ($tier > $minTier && $costFor($area, $tier) > $reduceCost) {
                    $reduceArea = $area;
                    $reduceCost = $costFor($area, $tier);
                }
            }

            if ($reduceArea === null) {
                return $tiers;
            }

            $tiers[$reduceArea]--;
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
}
