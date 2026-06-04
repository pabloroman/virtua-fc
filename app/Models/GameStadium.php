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
 * @property string|null $stadium_name
 * @property int|null $name_changed_season
 * @property int $base_capacity
 * @property int $supplementary_seats
 * @property int|null $rebuilt_capacity
 * @property int|null $base_uefa_level
 * @property int|null $rebuilt_uefa_level
 * @property-read \App\Models\Game $game
 * @property-read int $effective_capacity
 * @property-read int $supplementary_headroom
 * @property-read int|null $effective_uefa_level
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
        'stadium_name',
        'name_changed_season',
        'base_capacity',
        'supplementary_seats',
        'rebuilt_capacity',
        'base_uefa_level',
        'rebuilt_uefa_level',
    ];

    protected $casts = [
        'name_changed_season' => 'integer',
        'base_capacity' => 'integer',
        'supplementary_seats' => 'integer',
        'rebuilt_capacity' => 'integer',
        'base_uefa_level' => 'integer',
        'rebuilt_uefa_level' => 'integer',
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

    /**
     * Current UEFA category for this stadium. Returns the rebuilt level
     * if the team has commissioned an upgrade project; otherwise the
     * baseline carried over from the team's real-world ground. Null when
     * the ground is uncategorised (sub-200-seat / placeholder teams).
     */
    public function getEffectiveUefaLevelAttribute(): ?int
    {
        return $this->rebuilt_uefa_level ?? $this->base_uefa_level;
    }
}
