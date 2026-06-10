<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Season ticket pricing snapshot for a (game, season). Locked once the
 * first competitive league match has been played. The areas JSON is the
 * source of truth for per-area price/capacity/sold; total_sold and
 * total_revenue are denormalised aggregates so the attendance floor and
 * budget projection don't need to walk the JSON on every read.
 *
 * Each entry in `areas` shape:
 *   ['slug' => string, 'capacity' => int, 'price_cents' => int,
 *    'baseline_price_cents' => int, 'sold' => int, 'fill_rate' => float]
 *
 * @property string $id
 * @property string $game_id
 * @property int $season
 * @property array $areas
 * @property int $total_capacity
 * @property int $total_sold
 * @property int $total_revenue
 * @property string $pricing_preset
 * @property bool $is_default
 * @property-read \App\Models\Game $game
 */
class SeasonTicketPricing extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'game_id',
        'season',
        'areas',
        'total_capacity',
        'total_sold',
        'total_revenue',
        'pricing_preset',
        'is_default',
    ];

    protected $casts = [
        'areas' => 'array',
        'season' => 'integer',
        'total_capacity' => 'integer',
        'total_sold' => 'integer',
        'total_revenue' => 'integer',
        'is_default' => 'boolean',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /**
     * Fill rate across all areas (0-100, integer).
     */
    public function fillRatePercent(): int
    {
        return self::fillRateFor($this->total_sold, $this->total_capacity);
    }

    /**
     * Fill-rate helper usable on raw sold/capacity totals (e.g. when the
     * pricing row hasn't been persisted yet and we only have a default
     * preview payload).
     */
    public static function fillRateFor(int $sold, int $capacity): int
    {
        if ($capacity <= 0) {
            return 0;
        }

        return (int) round(($sold / $capacity) * 100);
    }
}
