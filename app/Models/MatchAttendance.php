<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-fixture attendance record. One row per GameMatch, written by
 * MatchAttendanceService before the match is simulated so the figure can
 * be displayed on the live-match screen and consumed by future atmosphere
 * events. capacity_at_match snapshots stadium capacity at the time of the
 * match so the row stays meaningful after later capacity expansions.
 *
 * @property string $id
 * @property string $game_id
 * @property string $game_match_id
 * @property int $attendance
 * @property int $capacity_at_match
 * @property \Illuminate\Support\Carbon $created_at
 * @property-read \App\Models\Game $game
 * @property-read \App\Models\GameMatch $gameMatch
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MatchAttendance newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MatchAttendance newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MatchAttendance query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MatchAttendance whereGameId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MatchAttendance whereGameMatchId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MatchAttendance whereId($value)
 * @mixin \Eloquent
 */
class MatchAttendance extends Model
{
    use HasUuids;

    const UPDATED_AT = null;

    protected $fillable = [
        'game_id',
        'game_match_id',
        'attendance',
        'capacity_at_match',
    ];

    protected $casts = [
        'attendance' => 'integer',
        'capacity_at_match' => 'integer',
        'created_at' => 'datetime',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function gameMatch(): BelongsTo
    {
        return $this->belongsTo(GameMatch::class);
    }

    /**
     * Attendance as a percentage of capacity (0-100, integer).
     * Returns 0 if capacity_at_match is somehow zero.
     */
    public function fillRatePercent(): int
    {
        if ($this->capacity_at_match <= 0) {
            return 0;
        }

        return (int) round(($this->attendance / $this->capacity_at_match) * 100);
    }
}
