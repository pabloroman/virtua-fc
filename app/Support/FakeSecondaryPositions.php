<?php

namespace App\Support;

/**
 * Temporary helper for Phase 0 UI prototyping.
 *
 * Generates deterministic fake secondary positions for any player based on their
 * UUID and primary position. This allows the UI to render secondary position badges
 * across all views without any database changes.
 *
 * Remove this class when real secondary positions are stored on the model (Phase 1).
 */
class FakeSecondaryPositions
{
    /**
     * Generate fake secondary positions for a player.
     *
     * ~50% of players get none, ~40% get one, ~10% get two.
     * Results are deterministic — the same player ID always produces the same result.
     *
     * @return string[]  Canonical position names (e.g. ["Defensive Midfield"])
     */
    public static function for(string $playerId, string $position): array
    {
        $seed = crc32($playerId);
        $rand = abs($seed) % 100;

        // 50% get no secondary positions
        if ($rand < 50) {
            return [];
        }

        $adjacent = PositionSlotMapper::getAdjacentPositions($position);
        if (empty($adjacent)) {
            return [];
        }

        $pick1 = $adjacent[abs($seed) % count($adjacent)];

        // 40% get one secondary position
        if ($rand < 90) {
            return [$pick1];
        }

        // 10% get two secondary positions
        $remaining = array_values(array_diff($adjacent, [$pick1]));
        if (empty($remaining)) {
            return [$pick1];
        }

        $pick2 = $remaining[abs($seed >> 8) % count($remaining)];

        return [$pick1, $pick2];
    }
}
