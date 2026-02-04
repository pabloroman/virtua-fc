<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameFinances extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'balance' => 'integer',
        'wage_budget' => 'integer',
        'transfer_budget' => 'integer',
        'tv_revenue' => 'integer',
        'performance_bonus' => 'integer',
        'cup_bonus' => 'integer',
        'total_revenue' => 'integer',
        'wage_expense' => 'integer',
        'transfer_expense' => 'integer',
        'total_expense' => 'integer',
        'season_profit_loss' => 'integer',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /**
     * Check if the club is in debt.
     */
    public function isInDebt(): bool
    {
        return $this->balance < 0;
    }

    /**
     * Get formatted balance for display.
     */
    public function getFormattedBalanceAttribute(): string
    {
        return Money::format($this->balance);
    }

    /**
     * Get formatted wage budget for display.
     */
    public function getFormattedWageBudgetAttribute(): string
    {
        return Money::format($this->wage_budget);
    }

    /**
     * Get formatted transfer budget for display.
     */
    public function getFormattedTransferBudgetAttribute(): string
    {
        return Money::format($this->transfer_budget);
    }

    /**
     * Get formatted TV revenue for display.
     */
    public function getFormattedTvRevenueAttribute(): string
    {
        return Money::format($this->tv_revenue);
    }

    /**
     * Get formatted total revenue for display.
     */
    public function getFormattedTotalRevenueAttribute(): string
    {
        return Money::format($this->total_revenue);
    }

    /**
     * Get formatted total expense for display.
     */
    public function getFormattedTotalExpenseAttribute(): string
    {
        return Money::format($this->total_expense);
    }

    /**
     * Get formatted season profit/loss for display.
     */
    public function getFormattedSeasonProfitLossAttribute(): string
    {
        return Money::formatSigned($this->season_profit_loss);
    }

    /**
     * Get formatted wage expense for display.
     */
    public function getFormattedWageExpenseAttribute(): string
    {
        return Money::format($this->wage_expense);
    }
}
