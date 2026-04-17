<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $team_id
 * @property string $reputation_level
 * @property int $fan_loyalty
 * @property-read \App\Models\Team $team
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubProfile newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubProfile newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubProfile query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubProfile whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubProfile whereReputationLevel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubProfile whereFanLoyalty($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubProfile whereTeamId($value)
 * @mixin \Eloquent
 */
class ClubProfile extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'team_id',
        'reputation_level',
        'fan_loyalty',
    ];

    protected $casts = [
        'fan_loyalty' => 'integer',
    ];

    public const REPUTATION_ELITE = 'elite';
    public const REPUTATION_CONTINENTAL = 'continental';
    public const REPUTATION_ESTABLISHED = 'established';
    public const REPUTATION_MODEST = 'modest';
    public const REPUTATION_LOCAL = 'local';

    /**
     * Curated fan_loyalty on a coarse 0-10 scale — an editorial judgment,
     * not a measurement. A club's cultural tendency to fill its stadium
     * regardless of competitive tier. Calibration:
     *
     *   10 — iconic / cult loyalty (Athletic Bilbao, St. Pauli, Celtic)
     *    9 — huge, passionate followings (Red Star, Dortmund, Marseille)
     *    8 — strong loyal support (Real Madrid, Union Berlin, PAOK)
     *    7 — good loyal support (Real Sociedad, Leeds, Benfica)
     *    6 — slightly above average
     *    5 — average (the scale midpoint; uncurated clubs default here)
     *    4 — notably below average (Villarreal, Nantes)
     *    3 — small local following
     *    2 — minor-league / new-market following (reference only; not
     *        typical for clubs in the game)
     *    1 — essentially no following
     *
     * Copied into TeamReputation.base_loyalty at game start (scaled to
     * the 0-100 internal range used by the demand curve) and never
     * changes after that; loyalty_points drifts from there.
     */
    public const FAN_LOYALTY_MIN = 0;
    public const FAN_LOYALTY_MAX = 10;
    public const FAN_LOYALTY_DEFAULT = 5;

    /**
     * Reputation tiers ordered from lowest to highest (0 = local, 4 = elite).
     */
    public const REPUTATION_TIERS = [
        self::REPUTATION_LOCAL,        // 0
        self::REPUTATION_MODEST,       // 1
        self::REPUTATION_ESTABLISHED,  // 2
        self::REPUTATION_CONTINENTAL,  // 3
        self::REPUTATION_ELITE,        // 4
    ];

    /**
     * Get the numeric index of a reputation level (0 = local, 4 = elite).
     */
    public static function getReputationTierIndex(string $level): int
    {
        $index = array_search($level, self::REPUTATION_TIERS, true);

        return $index !== false ? $index : 0;
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
