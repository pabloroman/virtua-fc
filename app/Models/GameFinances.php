<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameFinances extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'game_id',
        'season',
        'projected_position',
        'projected_tv_revenue',
        'projected_prize_revenue',
        'projected_matchday_revenue',
        'projected_commercial_revenue',
        'projected_subsidy_revenue',
        'projected_total_revenue',
        'projected_wages',
        'projected_operating_expenses',
        'projected_taxes',
        'projected_surplus',
        'actual_tv_revenue',
        'actual_prize_revenue',
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
        'projected_prize_revenue' => 'integer',
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
        'actual_prize_revenue' => 'integer',
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

    public function getFormattedActualPrizeRevenueAttribute(): string
    {
        return Money::format($this->actual_prize_revenue);
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
