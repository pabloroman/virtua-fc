<?php

namespace App\Models;

use App\Game\Services\ContractService;
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
        return ContractService::formatWage($this->balance);
    }

    /**
     * Get formatted wage budget for display.
     */
    public function getFormattedWageBudgetAttribute(): string
    {
        return ContractService::formatWage($this->wage_budget);
    }

    /**
     * Get formatted transfer budget for display.
     */
    public function getFormattedTransferBudgetAttribute(): string
    {
        return ContractService::formatWage($this->transfer_budget);
    }

    /**
     * Get formatted TV revenue for display.
     */
    public function getFormattedTvRevenueAttribute(): string
    {
        return ContractService::formatWage($this->tv_revenue);
    }

    /**
     * Get formatted total revenue for display.
     */
    public function getFormattedTotalRevenueAttribute(): string
    {
        return ContractService::formatWage($this->total_revenue);
    }

    /**
     * Get formatted total expense for display.
     */
    public function getFormattedTotalExpenseAttribute(): string
    {
        return ContractService::formatWage($this->total_expense);
    }

    /**
     * Get formatted season profit/loss for display.
     */
    public function getFormattedSeasonProfitLossAttribute(): string
    {
        $prefix = $this->season_profit_loss >= 0 ? '+' : '';
        return $prefix . ContractService::formatWage($this->season_profit_loss);
    }

    /**
     * Get formatted wage expense for display.
     */
    public function getFormattedWageExpenseAttribute(): string
    {
        return ContractService::formatWage($this->wage_expense);
    }
}
