<?php

namespace App\Models;

use App\Modules\Stadium\Enums\StadiumProjectFinancing;
use App\Modules\Stadium\Enums\StadiumProjectStatus;
use App\Modules\Stadium\Enums\StadiumProjectType;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $game_id
 * @property string $team_id
 * @property StadiumProjectType $type
 * @property StadiumProjectStatus $status
 * @property int $target_capacity
 * @property int $committed_season
 * @property \Illuminate\Support\Carbon $committed_date
 * @property \Illuminate\Support\Carbon|null $completion_date
 * @property int|null $completion_season
 * @property int $total_cost_cents
 * @property StadiumProjectFinancing $financing
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
        'type' => StadiumProjectType::class,
        'status' => StadiumProjectStatus::class,
        'financing' => StadiumProjectFinancing::class,
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
        return $query->whereIn('status', [
            StadiumProjectStatus::Pending->value,
            StadiumProjectStatus::InProgress->value,
        ]);
    }

    public function scopeOfType(Builder $query, StadiumProjectType $type): Builder
    {
        return $query->where('type', $type->value);
    }

    public function isSupplementary(): bool
    {
        return $this->type === StadiumProjectType::Supplementary;
    }

    public function isStandExpansion(): bool
    {
        return $this->type === StadiumProjectType::StandExpansion;
    }

    public function isRebuild(): bool
    {
        return $this->type === StadiumProjectType::Rebuild;
    }

    public function isUefaUpgrade(): bool
    {
        return $this->type === StadiumProjectType::UefaUpgrade;
    }

    /**
     * True while a rebuild is actively under construction — the season in
     * which match capacity drops to the construction-time fraction.
     */
    public function reducesMatchCapacity(): bool
    {
        return $this->isRebuild() && $this->status === StadiumProjectStatus::InProgress;
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
