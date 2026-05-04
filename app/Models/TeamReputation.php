<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class TeamReputation extends Model
{
    use HasUuids;

    public $timestamps = false;

    /**
     * In-process memo for resolveLevel/resolveLevels keyed by "{gameId}|{teamId}".
     * reputation_level is only mutated by ReputationUpdateProcessor (season
     * closing) and SetupNewGame (game creation), so this is safe to cache for
     * the life of the request. busted explicitly via flushCacheFor() so that
     * long-lived workers (queue, octane) don't serve stale values after
     * a season transition.
     */
    private static array $memo = [];

    /** Persistent-cache key for a (game, team) reputation level. */
    private static function cacheKey(string $gameId, string $teamId): string
    {
        return "team_rep:{$gameId}:{$teamId}";
    }

    /** Forget the cached reputation level for a (game, team) pair. */
    public static function flushCacheFor(string $gameId, string $teamId): void
    {
        unset(self::$memo["{$gameId}|{$teamId}"]);
        Cache::forget(self::cacheKey($gameId, $teamId));
    }

    protected $fillable = [
        'game_id',
        'team_id',
        'reputation_level',
        'base_reputation_level',
        'reputation_points',
        'base_loyalty',
        'loyalty_points',
    ];

    protected $casts = [
        'base_loyalty' => 'integer',
        'loyalty_points' => 'integer',
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
     * Bounds for the loyalty stats (0-100). loyalty_points drifts season to
     * season driven by on-pitch outcomes, pricing policy, and homegrown
     * stars; base_loyalty is the curated anchor from ClubProfile.fan_loyalty
     * that stays put for the life of the game.
     */
    public const LOYALTY_MIN = 0;
    public const LOYALTY_MAX = 100;

    /**
     * Loyalty can't drop more than this many points below base_loyalty.
     * Captures the "Newcastle doesn't lose its fans in the Championship"
     * floor — cultural identity cushions the fall when form collapses.
     */
    public const MAX_LOYALTY_DROP_BELOW_BASE = 15;

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
        return (self::TIER_THRESHOLDS[$level] ?? 0) + 50;
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
        $memoKey = "{$gameId}|{$teamId}";
        if (isset(self::$memo[$memoKey])) {
            return self::$memo[$memoKey];
        }

        $level = Cache::rememberForever(self::cacheKey($gameId, $teamId), function () use ($gameId, $teamId) {
            $value = self::where('game_id', $gameId)
                ->where('team_id', $teamId)
                ->value('reputation_level');

            if ($value) {
                return $value;
            }

            return ClubProfile::where('team_id', $teamId)
                ->value('reputation_level') ?? ClubProfile::REPUTATION_LOCAL;
        });

        return self::$memo[$memoKey] = $level;
    }

    /**
     * Bulk-resolve reputation levels for multiple teams in a game.
     *
     * @return \Illuminate\Support\Collection<string, string> team_id => reputation_level
     */
    public static function resolveLevels(string $gameId, array $teamIds): \Illuminate\Support\Collection
    {
        $resolved = collect();
        $needLookup = [];

        // Layer 1: in-process memo
        foreach ($teamIds as $teamId) {
            $memoKey = "{$gameId}|{$teamId}";
            if (isset(self::$memo[$memoKey])) {
                $resolved[$teamId] = self::$memo[$memoKey];
            } else {
                $needLookup[] = $teamId;
            }
        }

        if (empty($needLookup)) {
            return $resolved;
        }

        // Layer 2: persistent cache (one Cache::many round-trip for all misses)
        $cacheKeys = array_map(fn ($id) => self::cacheKey($gameId, $id), $needLookup);
        $cached = Cache::many($cacheKeys);
        $needDb = [];
        foreach ($needLookup as $teamId) {
            $cacheValue = $cached[self::cacheKey($gameId, $teamId)] ?? null;
            if ($cacheValue !== null) {
                $resolved[$teamId] = $cacheValue;
                self::$memo["{$gameId}|{$teamId}"] = $cacheValue;
            } else {
                $needDb[] = $teamId;
            }
        }

        if (empty($needDb)) {
            return $resolved;
        }

        // Layer 3: DB
        $fromGame = self::where('game_id', $gameId)
            ->whereIn('team_id', $needDb)
            ->pluck('reputation_level', 'team_id');

        $stillMissing = array_diff($needDb, $fromGame->keys()->all());
        $fromProfile = !empty($stillMissing)
            ? ClubProfile::whereIn('team_id', $stillMissing)->pluck('reputation_level', 'team_id')
            : collect();

        foreach ($needDb as $teamId) {
            $level = $fromGame[$teamId] ?? $fromProfile[$teamId] ?? ClubProfile::REPUTATION_LOCAL;
            $resolved[$teamId] = $level;
            self::$memo["{$gameId}|{$teamId}"] = $level;
            Cache::forever(self::cacheKey($gameId, $teamId), $level);
        }

        return $resolved;
    }
}
