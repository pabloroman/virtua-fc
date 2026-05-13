<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Multi-year flat-principal loan that funds a stadium rebuild. Each
 * season, the club pays:
 *   annual_principal = principal_cents / term_years          (constant)
 *   annual_interest  = remaining_principal × interest_rate   (declining)
 * so total annual payment is highest in year 1 and declines over the
 * life of the loan.
 *
 * @property string $id
 * @property string $game_id
 * @property string $stadium_project_id
 * @property int $principal_cents
 * @property int $term_years
 * @property int $interest_rate_bps
 * @property int $remaining_principal_cents
 * @property int $season_started
 * @property string $status
 * @property-read \App\Models\Game $game
 * @property-read \App\Models\GameStadiumProject $project
 * @property-read int $annual_principal_payment_cents
 * @property-read int $next_payment_cents
 * @property-read string $formatted_principal
 * @property-read string $formatted_remaining_principal
 * @property-read string $formatted_next_payment
 */
class StadiumLoan extends Model
{
    use HasUuids;

    public $timestamps = false;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_REPAID = 'repaid';

    protected $fillable = [
        'game_id',
        'stadium_project_id',
        'principal_cents',
        'term_years',
        'interest_rate_bps',
        'remaining_principal_cents',
        'season_started',
        'status',
    ];

    protected $casts = [
        'principal_cents' => 'integer',
        'term_years' => 'integer',
        'interest_rate_bps' => 'integer',
        'remaining_principal_cents' => 'integer',
        'season_started' => 'integer',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(GameStadiumProject::class, 'stadium_project_id');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Flat principal slice — the same amount every year for term_years.
     * The final year may be slightly larger to absorb integer rounding.
     */
    public function getAnnualPrincipalPaymentCentsAttribute(): int
    {
        return intdiv($this->principal_cents, $this->term_years);
    }

    /**
     * Year-N instalment: this year's principal slice + interest on the
     * still-outstanding balance. Used both to bill the season and to
     * preview the next payment to the user.
     */
    public function getNextPaymentCentsAttribute(): int
    {
        if (! $this->isActive()) {
            return 0;
        }

        $interest = (int) round($this->remaining_principal_cents * $this->interest_rate_bps / 10000);
        $principal = min($this->annual_principal_payment_cents, $this->remaining_principal_cents);

        // Last year: pay off whatever rounding left behind.
        if ($this->remaining_principal_cents - $principal < $this->annual_principal_payment_cents) {
            $principal = $this->remaining_principal_cents;
        }

        return $principal + $interest;
    }

    public function getFormattedPrincipalAttribute(): string
    {
        return Money::format($this->principal_cents);
    }

    public function getFormattedRemainingPrincipalAttribute(): string
    {
        return Money::format($this->remaining_principal_cents);
    }

    public function getFormattedNextPaymentAttribute(): string
    {
        return Money::format($this->next_payment_cents);
    }
}
