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
 * @property int|null $projected_position
 * @property int $projected_tv_revenue
 * @property int $projected_solidarity_funds_revenue
 * @property int $projected_matchday_revenue
 * @property int $projected_commercial_revenue
 * @property int $projected_total_revenue
 * @property int $projected_wages
 * @property int $projected_surplus
 * @property int $actual_tv_revenue
 * @property int $actual_cup_bonus_revenue
 * @property int $actual_matchday_revenue
 * @property int $actual_commercial_revenue
 * @property int $actual_transfer_income
 * @property int $actual_total_revenue
 * @property int $actual_wages
 * @property int $actual_surplus
 * @property int $variance
 * @property int $carried_debt
 * @property int $projected_operating_expenses
 * @property int $projected_taxes
 * @property int $actual_operating_expenses
 * @property int $actual_taxes
 * @property int $projected_subsidy_revenue
 * @property int $actual_subsidy_revenue
 * @property int $actual_solidarity_funds_revenue
 * @property-read \App\Models\Game $game
 * @property-read int $available_surplus
 * @property-read string $formatted_actual_commercial_revenue
 * @property-read string $formatted_actual_cup_bonus_revenue
 * @property-read string $formatted_actual_matchday_revenue
 * @property-read string $formatted_actual_operating_expenses
 * @property-read string $formatted_actual_solidarity_funds_revenue
 * @property-read string $formatted_actual_surplus
 * @property-read string $formatted_actual_total_revenue
 * @property-read string $formatted_actual_transfer_income
 * @property-read string $formatted_actual_tv_revenue
 * @property-read string $formatted_actual_wages
 * @property-read string $formatted_available_surplus
 * @property-read string $formatted_carried_debt
 * @property-read string $formatted_projected_commercial_revenue
 * @property-read string $formatted_projected_matchday_revenue
 * @property-read string $formatted_projected_operating_expenses
 * @property-read string $formatted_projected_solidarity_funds_revenue
 * @property-read string $formatted_projected_subsidy_revenue
 * @property-read string $formatted_projected_surplus
 * @property-read string $formatted_projected_total_revenue
 * @property-read string $formatted_projected_tv_revenue
 * @property-read string $formatted_projected_wages
 * @property-read string $formatted_variance
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameFinances newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameFinances newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameFinances query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameFinances whereActualCommercialRevenue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameFinances whereActualCupBonusRevenue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameFinances whereActualMatchdayRevenue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameFinances whereActualOperatingExpenses($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameFinances whereActualSolidarityFundsRevenue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameFinances whereActualSubsidyRevenue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameFinances whereActualSurplus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameFinances whereActualTaxes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameFinances whereActualTotalRevenue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameFinances whereActualTransferIncome($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameFinances whereActualTvRevenue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameFinances whereActualWages($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameFinances whereCarriedDebt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameFinances whereGameId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameFinances whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameFinances whereProjectedCommercialRevenue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameFinances whereProjectedMatchdayRevenue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameFinances whereProjectedOperatingExpenses($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameFinances whereProjectedPosition($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameFinances whereProjectedSolidarityFundsRevenue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameFinances whereProjectedSubsidyRevenue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameFinances whereProjectedSurplus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameFinances whereProjectedTaxes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameFinances whereProjectedTotalRevenue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameFinances whereProjectedTvRevenue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameFinances whereProjectedWages($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameFinances whereSeason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameFinances whereVariance($value)
 * @mixin \Eloquent
 */
class GameFinances extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'game_id',
        'season',
        'projected_position',
        'projected_tv_revenue',
        'projected_solidarity_funds_revenue',
        'projected_matchday_revenue',
        'projected_commercial_revenue',
        'projected_subsidy_revenue',
        'projected_total_revenue',
        'projected_wages',
        'projected_operating_expenses',
        'projected_taxes',
        'projected_surplus',
        'actual_tv_revenue',
        'actual_solidarity_funds_revenue',
        'actual_cup_bonus_revenue',
        'actual_matchday_revenue',
        'actual_commercial_revenue',
        'actual_subsidy_revenue',
        'actual_transfer_income',
        'actual_total_revenue',
        'actual_wages',
        'actual_operating_expenses',
        'actual_taxes',
        'actual_surplus',
        'variance',
        'carried_debt',
    ];

    protected $casts = [
        'season' => 'integer',
        // Projections
        'projected_position' => 'integer',
        'projected_tv_revenue' => 'integer',
        'projected_solidarity_funds_revenue' => 'integer',
        'projected_matchday_revenue' => 'integer',
        'projected_commercial_revenue' => 'integer',
        'projected_subsidy_revenue' => 'integer',
        'projected_total_revenue' => 'integer',
        'projected_wages' => 'integer',
        'projected_operating_expenses' => 'integer',
        'projected_taxes' => 'integer',
        'projected_surplus' => 'integer',
        // Actuals
        'actual_tv_revenue' => 'integer',
        'actual_solidarity_funds_revenue' => 'integer',
        'actual_cup_bonus_revenue' => 'integer',
        'actual_matchday_revenue' => 'integer',
        'actual_commercial_revenue' => 'integer',
        'actual_subsidy_revenue' => 'integer',
        'actual_transfer_income' => 'integer',
        'actual_total_revenue' => 'integer',
        'actual_wages' => 'integer',
        'actual_operating_expenses' => 'integer',
        'actual_taxes' => 'integer',
        'actual_surplus' => 'integer',
        // Settlement
        'variance' => 'integer',
        'carried_debt' => 'integer',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /**
     * Check if the club has debt carried from previous season.
     */
    public function hasCarriedDebt(): bool
    {
        return $this->carried_debt > 0;
    }

    /**
     * Check if season ended with negative variance (underperformed).
     */
    public function hasNegativeVariance(): bool
    {
        return $this->variance < 0;
    }

    /**
     * Calculate available surplus for budget allocation.
     * Projected surplus minus any carried debt.
     */
    public function getAvailableSurplusAttribute(): int
    {
        return max(0, $this->projected_surplus - $this->carried_debt);
    }

    // Formatted accessors for projections
    public function getFormattedProjectedTvRevenueAttribute(): string
    {
        return Money::format($this->projected_tv_revenue);
    }

    public function getFormattedProjectedMatchdayRevenueAttribute(): string
    {
        return Money::format($this->projected_matchday_revenue);
    }

    public function getFormattedProjectedCommercialRevenueAttribute(): string
    {
        return Money::format($this->projected_commercial_revenue);
    }

    public function getFormattedProjectedSubsidyRevenueAttribute(): string
    {
        return Money::format($this->projected_subsidy_revenue);
    }

    public function getFormattedProjectedSolidarityFundsRevenueAttribute(): string
    {
        return Money::format($this->projected_solidarity_funds_revenue);
    }

    public function getFormattedProjectedTotalRevenueAttribute(): string
    {
        return Money::format($this->projected_total_revenue);
    }

    public function getFormattedProjectedWagesAttribute(): string
    {
        return Money::format($this->projected_wages);
    }

    public function getFormattedProjectedOperatingExpensesAttribute(): string
    {
        return Money::format($this->projected_operating_expenses);
    }

    public function getFormattedProjectedSurplusAttribute(): string
    {
        return Money::format($this->projected_surplus);
    }

    // Formatted accessors for actuals
    public function getFormattedActualTvRevenueAttribute(): string
    {
        return Money::format($this->actual_tv_revenue);
    }

    public function getFormattedActualMatchdayRevenueAttribute(): string
    {
        return Money::format($this->actual_matchday_revenue);
    }

    public function getFormattedActualCommercialRevenueAttribute(): string
    {
        return Money::format($this->actual_commercial_revenue);
    }

    public function getFormattedActualSolidarityFundsRevenueAttribute(): string
    {
        return Money::format($this->actual_solidarity_funds_revenue);
    }

    public function getFormattedActualCupBonusRevenueAttribute(): string
    {
        return Money::format($this->actual_cup_bonus_revenue);
    }

    public function getFormattedActualTransferIncomeAttribute(): string
    {
        return Money::format($this->actual_transfer_income);
    }

    public function getFormattedActualTotalRevenueAttribute(): string
    {
        return Money::format($this->actual_total_revenue);
    }

    public function getFormattedActualWagesAttribute(): string
    {
        return Money::format($this->actual_wages);
    }

    public function getFormattedActualOperatingExpensesAttribute(): string
    {
        return Money::format($this->actual_operating_expenses);
    }

    public function getFormattedActualSurplusAttribute(): string
    {
        return Money::format($this->actual_surplus);
    }

    // Formatted accessors for settlement
    public function getFormattedVarianceAttribute(): string
    {
        return Money::formatSigned($this->variance);
    }

    public function getFormattedCarriedDebtAttribute(): string
    {
        return Money::format($this->carried_debt);
    }

    public function getFormattedAvailableSurplusAttribute(): string
    {
        return Money::format($this->available_surplus);
    }
}
