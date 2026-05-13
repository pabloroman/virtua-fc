<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-game stadium state for a team. Overrides the control-plane
 * Team.stadium_seats baseline once the user starts upgrading.
 *
 * @property string $id
 * @property string $game_id
 * @property string $team_id
 * @property int $base_capacity
 * @property int $supplementary_seats
 * @property int|null $rebuilt_capacity
 * @property-read \App\Models\Game $game
 * @property-read int $effective_capacity
 * @property-read int $supplementary_headroom
 */
class GameStadium extends Model
{
    use HasUuids;

    public $timestamps = false;

    /**
     * Across-the-stadium-lifetime cap on supplementary stands (gradas
     * supletorias). A rebuild folds existing supletorias into
     * rebuilt_capacity and resets supplementary_seats to zero, restoring
     * the full 5,000-seat headroom.
     */
    public const SUPPLEMENTARY_SEATS_CAP = 5000;

    protected $fillable = [
        'game_id',
        'team_id',
        'base_capacity',
        'supplementary_seats',
        'rebuilt_capacity',
    ];

    protected $casts = [
        'base_capacity' => 'integer',
        'supplementary_seats' => 'integer',
        'rebuilt_capacity' => 'integer',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function getEffectiveCapacityAttribute(): int
    {
        $base = $this->rebuilt_capacity ?? $this->base_capacity;

        return $base + $this->supplementary_seats;
    }

    public function getSupplementaryHeadroomAttribute(): int
    {
        return max(0, self::SUPPLEMENTARY_SEATS_CAP - $this->supplementary_seats);
    }
}
