<?php

namespace App\Modules\Stadium\Services;

use App\Models\GameStadium;

/**
 * Resolves the *game-scoped* stadium display name for a team. Wraps the
 * per-game name overlay (game_stadiums.stadium_name), which is set by a
 * manual rename or by an active naming-rights deal.
 *
 * Falls back to the control-plane Team.stadium_name when no per-game name
 * has been set (the common case — most stadiums keep their original name,
 * and non-user teams never get an override in v1).
 *
 * Caches results per request — bound short-lived in the container, but a
 * fixture list can pull the same home team many times, so this keeps the
 * venue-name lookup from turning into an N+1.
 */
class GameStadiumNameResolver
{
    /** @var array<string, ?string> game_id|team_id => resolved name | null */
    private array $cache = [];

    public function effectiveName(string $gameId, string $teamId, ?string $fallback): ?string
    {
        $key = $gameId.'|'.$teamId;
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key] ?? $fallback;
        }

        $override = GameStadium::query()
            ->where('game_id', $gameId)
            ->where('team_id', $teamId)
            ->value('stadium_name');

        $this->cache[$key] = $override;

        return $override ?? $fallback;
    }

    public function clearCache(): void
    {
        $this->cache = [];
    }
}
