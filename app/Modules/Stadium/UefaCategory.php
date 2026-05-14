<?php

namespace App\Modules\Stadium;

/**
 * UEFA stadium category constants and helpers.
 *
 * UEFA classifies stadiums into four categories (1 lowest → 4 elite). The
 * minimum-seat thresholds below mirror UEFA's published infrastructure rules
 * — they're the *floor* to qualify for the category, not a derivation rule.
 *
 * The DERIVATION_THRESHOLDS table below is a separate game-side heuristic
 * used at seed time to give each real-world team a plausible starting
 * category from its `stadium_seats` value (we don't ingest UEFA categories
 * from upstream data). It is intentionally stricter than the bare UEFA
 * floors so a 5,000-seat Segunda B ground doesn't land at Cat 2 just because
 * it scrapes the minimum.
 */
final class UefaCategory
{
    public const MIN = 1;
    public const MAX = 4;

    /**
     * Real UEFA minimum-capacity floors a stadium must clear to qualify for
     * a given category. Used to gate upgrades server-side and surface
     * "expand the stadium first" hints in the UI.
     *
     * @return array<int, int> category => minimum seats
     */
    public static function capacityFloors(): array
    {
        return [
            1 => 200,
            2 => 1_500,
            3 => 4_500,
            4 => 8_000,
        ];
    }

    public static function capacityFloor(int $category): int
    {
        return self::capacityFloors()[$category] ?? 0;
    }

    /**
     * Heuristic for deriving a team's starting UEFA category from its
     * stadium capacity at seed time. Uses thresholds that match the rough
     * empirical mapping in real Spanish football — Cat 4 for top-flight
     * stadiums (≥40k), Cat 3 for mid-sized first/second-tier grounds
     * (≥15k), Cat 2 for lower-division grounds (≥4.5k), Cat 1 otherwise.
     *
     * Returns null for grounds below the smallest UEFA floor (≤200 seats),
     * which we treat as "uncategorised".
     */
    public static function deriveFromCapacity(?int $seats): ?int
    {
        $seats = (int) ($seats ?? 0);

        if ($seats >= 40_000) {
            return 4;
        }
        if ($seats >= 15_000) {
            return 3;
        }
        if ($seats >= 4_500) {
            return 2;
        }
        if ($seats >= 200) {
            return 1;
        }

        return null;
    }
}
