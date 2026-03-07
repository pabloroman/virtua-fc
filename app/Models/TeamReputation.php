<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamReputation extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'game_id',
        'team_id',
        'reputation_level',
        'base_reputation_level',
        'reputation_points',
    ];

    /**
     * Points thresholds for each reputation tier.
     * A team is at a given tier when its points >= that tier's threshold
     * and < the next tier's threshold.
     */
    public const TIER_THRESHOLDS = [
        ClubProfile::REPUTATION_LOCAL        => 0,
        ClubProfile::REPUTATION_MODEST       => 100,
        ClubProfile::REPUTATION_ESTABLISHED  => 200,
        ClubProfile::REPUTATION_CONTINENTAL  => 300,
        ClubProfile::REPUTATION_ELITE        => 400,
    ];

    /**
     * Maximum tiers a team can drop below its seeded base reputation.
     */
    public const MAX_TIER_DROP_BELOW_BASE = 2;

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Convert a reputation level to its starting points value (midpoint of tier).
     */
    public static function pointsForTier(string $level): int
    {
        return self::TIER_THRESHOLDS[$level] ?? 0;
    }

    /**
     * Determine the reputation tier for a given points value,
     * respecting the floor (max drop below base).
     */
    public static function tierFromPoints(int $points, string $baseLevel): string
    {
        // Calculate raw tier from points
        $rawTier = self::rawTierFromPoints($points);

        // Enforce floor: cannot drop more than MAX_TIER_DROP_BELOW_BASE below base
        $baseIndex = ClubProfile::getReputationTierIndex($baseLevel);
        $rawIndex = ClubProfile::getReputationTierIndex($rawTier);
        $minIndex = max(0, $baseIndex - self::MAX_TIER_DROP_BELOW_BASE);

        if ($rawIndex < $minIndex) {
            return ClubProfile::REPUTATION_TIERS[$minIndex];
        }

        return $rawTier;
    }

    /**
     * Calculate raw tier from points without floor enforcement.
     */
    private static function rawTierFromPoints(int $points): string
    {
        $tier = ClubProfile::REPUTATION_LOCAL;

        foreach (self::TIER_THRESHOLDS as $level => $threshold) {
            if ($points >= $threshold) {
                $tier = $level;
            }
        }

        return $tier;
    }

    /**
     * Recalculate the reputation_level from current points and base.
     */
    public function recalculateTier(): string
    {
        $newLevel = self::tierFromPoints($this->reputation_points, $this->base_reputation_level);
        $this->reputation_level = $newLevel;

        return $newLevel;
    }

    /**
     * Resolve the effective reputation level for a team in a game.
     * Falls back to ClubProfile if no game-scoped record exists.
     */
    public static function resolveLevel(string $gameId, string $teamId): string
    {
        $level = self::where('game_id', $gameId)
            ->where('team_id', $teamId)
            ->value('reputation_level');

        if ($level) {
            return $level;
        }

        return ClubProfile::where('team_id', $teamId)
            ->value('reputation_level') ?? ClubProfile::REPUTATION_LOCAL;
    }

    /**
     * Bulk-resolve reputation levels for multiple teams in a game.
     *
     * @return \Illuminate\Support\Collection<string, string> team_id => reputation_level
     */
    public static function resolveLevels(string $gameId, array $teamIds): \Illuminate\Support\Collection
    {
        $levels = self::where('game_id', $gameId)
            ->whereIn('team_id', $teamIds)
            ->pluck('reputation_level', 'team_id');

        // Fill in any missing teams from ClubProfile
        $missing = array_diff($teamIds, $levels->keys()->all());
        if (!empty($missing)) {
            $fallback = ClubProfile::whereIn('team_id', $missing)
                ->pluck('reputation_level', 'team_id');
            $levels = $levels->merge($fallback);
        }

        return $levels;
    }
}
