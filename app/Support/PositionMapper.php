<?php

namespace App\Support;

class PositionMapper
{
    /**
     * Map canonical (English) position names to Spanish abbreviations.
     */
    private static array $positionToAbbreviation = [
        'Goalkeeper' => 'PO',
        'Centre-Back' => 'CT',
        'Left-Back' => 'LI',
        'Right-Back' => 'LD',
        'Defensive Midfield' => 'MCD',
        'Central Midfield' => 'MC',
        'Attacking Midfield' => 'MP',
        'Left Midfield' => 'MI',
        'Right Midfield' => 'MD',
        'Left Winger' => 'EI',
        'Right Winger' => 'ED',
        'Centre-Forward' => 'DC',
        'Second Striker' => 'SD',
    ];

    /**
     * Map canonical (English) position names to Spanish display names.
     */
    private static array $positionToDisplayName = [
        'Goalkeeper' => 'Portero',
        'Centre-Back' => 'Central',
        'Left-Back' => 'Lateral Izquierdo',
        'Right-Back' => 'Lateral Derecho',
        'Defensive Midfield' => 'Mediocentro Defensivo',
        'Central Midfield' => 'Centrocampista',
        'Attacking Midfield' => 'Mediapunta',
        'Left Midfield' => 'Medio Izquierdo',
        'Right Midfield' => 'Medio Derecho',
        'Left Winger' => 'Extremo Izquierdo',
        'Right Winger' => 'Extremo Derecho',
        'Centre-Forward' => 'Delantero Centro',
        'Second Striker' => 'Segundo Delantero',
    ];

    /**
     * Map internal slot codes (used in formations/compatibility) to Spanish abbreviations.
     */
    private static array $slotToDisplayAbbreviation = [
        'GK' => 'PO',
        'CB' => 'CT',
        'LB' => 'LI',
        'RB' => 'LD',
        'LWB' => 'CRI',
        'RWB' => 'CRD',
        'DM' => 'MCD',
        'CM' => 'MC',
        'AM' => 'MP',
        'LM' => 'MI',
        'RM' => 'MD',
        'LW' => 'EI',
        'RW' => 'ED',
        'CF' => 'DC',
        'SS' => 'SD',
    ];

    /**
     * CSS color classes keyed by Spanish abbreviation.
     */
    private static array $abbreviationColors = [
        'PO' => ['bg' => 'bg-amber-500', 'text' => 'text-white'],

        // Defenders
        'CT' => ['bg' => 'bg-blue-600', 'text' => 'text-white'],
        'LI' => ['bg' => 'bg-blue-600', 'text' => 'text-white'],
        'LD' => ['bg' => 'bg-blue-600', 'text' => 'text-white'],
        'CRI' => ['bg' => 'bg-blue-600', 'text' => 'text-white'],
        'CRD' => ['bg' => 'bg-blue-600', 'text' => 'text-white'],
        'DEF' => ['bg' => 'bg-blue-600', 'text' => 'text-white'],

        // Midfielders
        'MCD' => ['bg' => 'bg-emerald-600', 'text' => 'text-white'],
        'MC' => ['bg' => 'bg-emerald-600', 'text' => 'text-white'],
        'MP' => ['bg' => 'bg-emerald-600', 'text' => 'text-white'],
        'MI' => ['bg' => 'bg-emerald-600', 'text' => 'text-white'],
        'MD' => ['bg' => 'bg-emerald-600', 'text' => 'text-white'],
        'MED' => ['bg' => 'bg-emerald-600', 'text' => 'text-white'],

        // Forwards
        'EI' => ['bg' => 'bg-red-600', 'text' => 'text-white'],
        'ED' => ['bg' => 'bg-red-600', 'text' => 'text-white'],
        'DC' => ['bg' => 'bg-red-600', 'text' => 'text-white'],
        'SD' => ['bg' => 'bg-red-600', 'text' => 'text-white'],
        'DEL' => ['bg' => 'bg-red-600', 'text' => 'text-white'],
    ];

    /**
     * Get Spanish abbreviation for a canonical position name.
     */
    public static function toAbbreviation(string $position): string
    {
        return self::$positionToAbbreviation[$position] ?? 'MC';
    }

    /**
     * Get Spanish display name for a canonical position name.
     */
    public static function toDisplayName(string $position): string
    {
        return self::$positionToDisplayName[$position] ?? $position;
    }

    /**
     * Get Spanish abbreviation for an internal slot code (GK, CB, LWB, etc.).
     */
    public static function slotToDisplayAbbreviation(string $slotCode): string
    {
        return self::$slotToDisplayAbbreviation[$slotCode] ?? $slotCode;
    }

    /**
     * Get Spanish display name for a scout search filter value (GK, CB, any_defender, etc.).
     */
    public static function filterToDisplayName(string $filterValue): string
    {
        $filterMap = [
            'GK' => 'Portero',
            'CB' => 'Central',
            'LB' => 'Lateral Izquierdo',
            'RB' => 'Lateral Derecho',
            'DM' => 'Mediocentro Defensivo',
            'CM' => 'Centrocampista',
            'AM' => 'Mediapunta',
            'LM' => 'Medio Izquierdo',
            'RM' => 'Medio Derecho',
            'LW' => 'Extremo Izquierdo',
            'RW' => 'Extremo Derecho',
            'CF' => 'Delantero Centro',
            'SS' => 'Segundo Delantero',
            'any_defender' => 'Cualquier Defensa',
            'any_midfielder' => 'Cualquier Centrocampista',
            'any_forward' => 'Cualquier Delantero',
        ];

        return $filterMap[$filterValue] ?? $filterValue;
    }

    /**
     * Get CSS color classes for a position abbreviation.
     *
     * @return array{bg: string, text: string}
     */
    public static function getColors(string $abbreviation): array
    {
        return self::$abbreviationColors[$abbreviation] ?? self::$abbreviationColors['MC'];
    }

    /**
     * Get abbreviation with color classes for a canonical position name.
     *
     * @return array{abbreviation: string, bg: string, text: string}
     */
    public static function getPositionDisplay(string $position): array
    {
        $abbreviation = self::toAbbreviation($position);
        $colors = self::getColors($abbreviation);

        return [
            'abbreviation' => $abbreviation,
            'bg' => $colors['bg'],
            'text' => $colors['text'],
        ];
    }
}
