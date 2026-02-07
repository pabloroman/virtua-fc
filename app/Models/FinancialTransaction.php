<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialTransaction extends Model
{
    use HasUuids;

    protected $fillable = [
        'game_id',
        'type',
        'category',
        'amount',
        'description',
        'related_player_id',
        'transaction_date',
    ];

    protected $casts = [
        'amount' => 'integer',
        'transaction_date' => 'date',
    ];

    // Transaction types
    public const TYPE_INCOME = 'income';
    public const TYPE_EXPENSE = 'expense';

    // Categories
    public const CATEGORY_TRANSFER_IN = 'transfer_in';       // Selling a player
    public const CATEGORY_TRANSFER_OUT = 'transfer_out';     // Buying a player
    public const CATEGORY_WAGE = 'wage';                     // Wage payments
    public const CATEGORY_TV_RIGHTS = 'tv_rights';           // TV revenue
    public const CATEGORY_PERFORMANCE_BONUS = 'performance_bonus';
    public const CATEGORY_CUP_BONUS = 'cup_bonus';
    public const CATEGORY_SIGNING_BONUS = 'signing_bonus';   // Bonus paid to player on signing

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function relatedPlayer(): BelongsTo
    {
        return $this->belongsTo(GamePlayer::class, 'related_player_id');
    }

    /**
     * Get formatted amount for display.
     */
    public function getFormattedAmountAttribute(): string
    {
        return Money::format($this->amount);
    }

    /**
     * Get signed formatted amount (+ for income, - for expense).
     */
    public function getSignedAmountAttribute(): string
    {
        $formatted = Money::format($this->amount);

        return $this->type === self::TYPE_INCOME
            ? '+' . $formatted
            : '-' . $formatted;
    }

    /**
     * Get human-readable category label.
     */
    public function getCategoryLabelAttribute(): string
    {
        return match ($this->category) {
            self::CATEGORY_TRANSFER_IN => 'Player Sale',
            self::CATEGORY_TRANSFER_OUT => 'Player Purchase',
            self::CATEGORY_WAGE => 'Wages',
            self::CATEGORY_TV_RIGHTS => 'TV Rights',
            self::CATEGORY_PERFORMANCE_BONUS => 'Performance Bonus',
            self::CATEGORY_CUP_BONUS => 'Cup Prize Money',
            self::CATEGORY_SIGNING_BONUS => 'Signing Bonus',
            default => ucfirst(str_replace('_', ' ', $this->category)),
        };
    }

    /**
     * Check if this is an income transaction.
     */
    public function isIncome(): bool
    {
        return $this->type === self::TYPE_INCOME;
    }

    /**
     * Check if this is an expense transaction.
     */
    public function isExpense(): bool
    {
        return $this->type === self::TYPE_EXPENSE;
    }

    /**
     * Create an income transaction.
     */
    public static function recordIncome(
        string $gameId,
        string $category,
        int $amount,
        string $description,
        string $transactionDate,
        ?string $relatedPlayerId = null,
    ): self {
        return self::create([
            'game_id' => $gameId,
            'type' => self::TYPE_INCOME,
            'category' => $category,
            'amount' => $amount,
            'description' => $description,
            'transaction_date' => $transactionDate,
            'related_player_id' => $relatedPlayerId,
        ]);
    }

    /**
     * Create an expense transaction.
     */
    public static function recordExpense(
        string $gameId,
        string $category,
        int $amount,
        string $description,
        string $transactionDate,
        ?string $relatedPlayerId = null,
    ): self {
        return self::create([
            'game_id' => $gameId,
            'type' => self::TYPE_EXPENSE,
            'category' => $category,
            'amount' => $amount,
            'description' => $description,
            'transaction_date' => $transactionDate,
            'related_player_id' => $relatedPlayerId,
        ]);
    }
}
