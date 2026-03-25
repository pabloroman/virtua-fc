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
 * @property int $amount
 * @property int $interest_rate
 * @property int $repayment_amount
 * @property string $status
 * @property-read \App\Models\Game $game
 * @property-read string $formatted_amount
 * @property-read string $formatted_repayment_amount
 * @property-read int $interest_amount
 * @property-read string $formatted_interest_amount
 */
class BudgetLoan extends Model
{
    use HasUuids;

    public $timestamps = false;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_REPAID = 'repaid';

    protected $fillable = [
        'game_id',
        'season',
        'amount',
        'interest_rate',
        'repayment_amount',
        'status',
    ];

    protected $casts = [
        'season' => 'integer',
        'amount' => 'integer',
        'interest_rate' => 'integer',
        'repayment_amount' => 'integer',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function getInterestAmountAttribute(): int
    {
        return $this->repayment_amount - $this->amount;
    }

    public function getFormattedAmountAttribute(): string
    {
        return Money::format($this->amount);
    }

    public function getFormattedRepaymentAmountAttribute(): string
    {
        return Money::format($this->repayment_amount);
    }

    public function getFormattedInterestAmountAttribute(): string
    {
        return Money::format($this->interest_amount);
    }
}
