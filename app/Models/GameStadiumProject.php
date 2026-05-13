<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $game_id
 * @property string $team_id
 * @property string $type
 * @property string $status
 * @property int $target_capacity
 * @property int $committed_season
 * @property \Illuminate\Support\Carbon $committed_date
 * @property \Illuminate\Support\Carbon|null $completion_date
 * @property int|null $completion_season
 * @property int $total_cost_cents
 * @property string $financing
 * @property int $paid_cents
 * @property string|null $stadium_loan_id
 * @property-read \App\Models\Game $game
 * @property-read \App\Models\StadiumLoan|null $loan
 * @property-read string $formatted_total_cost
 * @property-read string $formatted_paid
 */
class GameStadiumProject extends Model
{
    use HasUuids;

    public $timestamps = false;

    public const TYPE_SUPPLEMENTARY = 'supplementary';
    public const TYPE_STAND_EXPANSION = 'stand_expansion';
    public const TYPE_REBUILD = 'rebuild';

    public const STATUS_PENDING = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';

    public const FINANCING_CASH = 'cash';
    public const FINANCING_LOAN = 'loan';

    protected $fillable = [
        'game_id',
        'team_id',
        'type',
        'status',
        'target_capacity',
        'committed_season',
        'committed_date',
        'completion_date',
        'completion_season',
        'total_cost_cents',
        'financing',
        'paid_cents',
        'stadium_loan_id',
    ];

    protected $casts = [
        'target_capacity' => 'integer',
        'committed_season' => 'integer',
        'committed_date' => 'date',
        'completion_date' => 'date',
        'completion_season' => 'integer',
        'total_cost_cents' => 'integer',
        'paid_cents' => 'integer',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(StadiumLoan::class, 'stadium_loan_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_IN_PROGRESS]);
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function isSupplementary(): bool
    {
        return $this->type === self::TYPE_SUPPLEMENTARY;
    }

    public function isStandExpansion(): bool
    {
        return $this->type === self::TYPE_STAND_EXPANSION;
    }

    public function isRebuild(): bool
    {
        return $this->type === self::TYPE_REBUILD;
    }

    /**
     * True while a rebuild is actively under construction — the season in
     * which match capacity drops to the construction-time fraction.
     */
    public function reducesMatchCapacity(): bool
    {
        return $this->isRebuild() && $this->status === self::STATUS_IN_PROGRESS;
    }

    public function getFormattedTotalCostAttribute(): string
    {
        return Money::format($this->total_cost_cents);
    }

    public function getFormattedPaidAttribute(): string
    {
        return Money::format($this->paid_cents);
    }
}
