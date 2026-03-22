<?php

namespace App\Support;

use App\Modules\Lineup\Enums\Formation;

/**
 * Defines the 9×14 pitch grid for advanced player positioning.
 *
 * The grid is purely visual — it controls WHERE player badges render on the pitch
 * but has no effect on match simulation. Each slot label (GK, CB, LM, etc.) has
 * a rectangular zone of valid cells. Players can be placed in any cell within
 * their slot's zone.
 */
class PitchGrid
{
    public const GRID_COLS = 9;

    public const GRID_ROWS = 14;

    /**
     * Zones per slot label: [colMin, colMax, rowMin, rowMax] (inclusive).
     */
    public const SLOT_ZONES = [
        'GK' => [4, 4, 0, 0],
        'CB' => [2, 6, 2, 4],
        'LB' => [0, 2, 2, 5],
        'RB' => [6, 8, 2, 5],
        'LWB' => [0, 2, 3, 6],
        'RWB' => [6, 8, 3, 6],
        'DM' => [2, 6, 4, 6],
        'CM' => [2, 6, 5, 8],
        'AM' => [2, 6, 7, 9],
        'LM' => [0, 2, 5, 9],
        'RM' => [6, 8, 5, 9],
        'LW' => [0, 2, 8, 12],
        'RW' => [6, 8, 8, 12],
        'CF' => [2, 6, 9, 13],
    ];

    /**
     * Check if a cell is valid for a slot.
     * GK can be at its own zone or any outfield cell (for swap scenarios).
     * Outfield players can go anywhere on rows 1-13 or the GK cell (4,0).
     */
    public static function isValidCell(string $slotLabel, int $col, int $row): bool
    {
        if ($col < 0 || $col >= self::GRID_COLS || $row < 0 || $row >= self::GRID_ROWS) {
            return false;
        }

        if ($slotLabel === 'GK') {
            $zone = self::SLOT_ZONES['GK'];
            $inOwnZone = $col >= $zone[0] && $col <= $zone[1] && $row >= $zone[2] && $row <= $zone[3];

            return $inOwnZone || $row >= 1;
        }

        // Outfield: rows 1-13 OR the GK cell (4,0) for swap scenarios
        return $row >= 1 || ($col === 4 && $row === 0);
    }

    /**
     * Map a formation's pitch slots to their default grid cells.
     *
     * @return array<int, array{col: int, row: int}> Keyed by slot ID
     */
    public static function getDefaultCells(Formation $formation): array
    {
        $cells = [];
        foreach ($formation->pitchSlots() as $slot) {
            $cells[$slot['id']] = ['col' => $slot['col'], 'row' => $slot['row']];
        }

        return $cells;
    }

    /**
     * Get the full grid configuration for the frontend.
     *
     * @return array{cols: int, rows: int, zones: array, defaultCells: array}
     */
    public static function getGridConfig(): array
    {
        $defaultCells = [];
        foreach (Formation::cases() as $formation) {
            $defaultCells[$formation->value] = self::getDefaultCells($formation);
        }

        return [
            'cols' => self::GRID_COLS,
            'rows' => self::GRID_ROWS,
            'zones' => self::SLOT_ZONES,
            'defaultCells' => $defaultCells,
        ];
    }
}
