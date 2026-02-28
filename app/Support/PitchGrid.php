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
        'GK' => [3, 5, 0, 1],
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
     * Convert a grid cell to pitch coordinates (0-100 range).
     *
     * @return array{x: float, y: float}
     */
    public static function cellToCoordinates(int $col, int $row): array
    {
        return [
            'x' => round($col * (100 / self::GRID_COLS) + (100 / (self::GRID_COLS * 2)), 1),
            'y' => round($row * (100 / self::GRID_ROWS) + (100 / (self::GRID_ROWS * 2)), 1),
        ];
    }

    /**
     * Find the nearest grid cell for given pitch coordinates.
     *
     * @return array{col: int, row: int}
     */
    public static function coordinatesToCell(float $x, float $y): array
    {
        $col = (int) round(($x - 100 / (self::GRID_COLS * 2)) / (100 / self::GRID_COLS));
        $row = (int) round(($y - 100 / (self::GRID_ROWS * 2)) / (100 / self::GRID_ROWS));

        return [
            'col' => max(0, min(self::GRID_COLS - 1, $col)),
            'row' => max(0, min(self::GRID_ROWS - 1, $row)),
        ];
    }

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
     * Get all cells in a slot's zone.
     *
     * @return array<array{col: int, row: int}>
     */
    public static function getZoneCells(string $slotLabel): array
    {
        $zone = self::SLOT_ZONES[$slotLabel] ?? null;
        if (! $zone) {
            return [];
        }

        [$colMin, $colMax, $rowMin, $rowMax] = $zone;
        $cells = [];

        for ($col = $colMin; $col <= $colMax; $col++) {
            for ($row = $rowMin; $row <= $rowMax; $row++) {
                $cells[] = ['col' => $col, 'row' => $row];
            }
        }

        return $cells;
    }

    /**
     * Map a formation's pitch slots to their nearest default grid cells.
     *
     * @return array<int, array{col: int, row: int}> Keyed by slot ID
     */
    public static function getDefaultCells(Formation $formation): array
    {
        $cells = [];
        foreach ($formation->pitchSlots() as $slot) {
            $cells[$slot['id']] = self::coordinatesToCell($slot['x'], $slot['y']);
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
