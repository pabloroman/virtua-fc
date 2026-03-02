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
     * Check if a cell is within a slot's valid zone.
     */
    public static function isValidCell(string $slotLabel, int $col, int $row): bool
    {
        $zone = self::SLOT_ZONES[$slotLabel] ?? null;
        if (! $zone) {
            return false;
        }

        [$colMin, $colMax, $rowMin, $rowMax] = $zone;

        return $col >= $colMin && $col <= $colMax && $row >= $rowMin && $row <= $rowMax;
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
