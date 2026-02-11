<?php

namespace App\Support;

class PositionMapper
{
    private static array $positionToAbbreviation = [
        'Goalkeeper' => 'GK',
        'Centre-Back' => 'CB',
        'Left-Back' => 'LB',
        'Right-Back' => 'RB',
        'Defensive Midfield' => 'DM',
        'Central Midfield' => 'CM',
        'Attacking Midfield' => 'AM',
        'Left Midfield' => 'LM',
        'Right Midfield' => 'RM',
        'Left Winger' => 'LW',
        'Right Winger' => 'RW',
        'Centre-Forward' => 'CF',
        'Second Striker' => 'SS',
    ];

    private static array $abbreviationColors = [
        'GK' => ['bg' => 'bg-amber-500', 'text' => 'text-white'],

        // Defenders
        'CB' => ['bg' => 'bg-blue-600', 'text' => 'text-white'],
        'LB' => ['bg' => 'bg-blue-600', 'text' => 'text-white'],
        'RB' => ['bg' => 'bg-blue-600', 'text' => 'text-white'],
        'DF' => ['bg' => 'bg-blue-600', 'text' => 'text-white'],

        // Midfielders
        'DM' => ['bg' => 'bg-emerald-600', 'text' => 'text-white'],
        'CM' => ['bg' => 'bg-emerald-600', 'text' => 'text-white'],
        'AM' => ['bg' => 'bg-emerald-600', 'text' => 'text-white'],
        'LM' => ['bg' => 'bg-emerald-600', 'text' => 'text-white'],
        'RM' => ['bg' => 'bg-emerald-600', 'text' => 'text-white'],
        'MF' => ['bg' => 'bg-emerald-600', 'text' => 'text-white'],

        // Forwards
        'LW' => ['bg' => 'bg-red-600', 'text' => 'text-white'],
        'RW' => ['bg' => 'bg-red-600', 'text' => 'text-white'],
        'CF' => ['bg' => 'bg-red-600', 'text' => 'text-white'],
        'SS' => ['bg' => 'bg-red-600', 'text' => 'text-white'],
        'FW' => ['bg' => 'bg-red-600', 'text' => 'text-white'],
    ];

    /**
     * Get 2-letter abbreviation for a position.
     */
    public static function toAbbreviation(string $position): string
    {
        return self::$positionToAbbreviation[$position] ?? 'MF';
    }

    /**
     * Get CSS color classes for a position abbreviation.
     *
     * @return array{bg: string, text: string}
     */
    public static function getColors(string $abbreviation): array
    {
        return self::$abbreviationColors[$abbreviation] ?? self::$abbreviationColors['MF'];
    }

    /**
     * Get abbreviation with color classes.
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
