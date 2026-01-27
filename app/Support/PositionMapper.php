<?php

namespace App\Support;

class PositionMapper
{
    private static array $positionToAbbreviation = [
        'Goalkeeper' => 'GK',
        'Centre-Back' => 'DF',
        'Left-Back' => 'DF',
        'Right-Back' => 'DF',
        'Defensive Midfield' => 'MF',
        'Central Midfield' => 'MF',
        'Attacking Midfield' => 'MF',
        'Left Midfield' => 'MF',
        'Right Midfield' => 'MF',
        'Left Winger' => 'FW',
        'Right Winger' => 'FW',
        'Centre-Forward' => 'FW',
        'Second Striker' => 'FW',
    ];

    private static array $abbreviationColors = [
        'GK' => ['bg' => 'bg-amber-100', 'text' => 'text-amber-700'],
        'DF' => ['bg' => 'bg-blue-100', 'text' => 'text-blue-700'],
        'MF' => ['bg' => 'bg-green-100', 'text' => 'text-green-700'],
        'FW' => ['bg' => 'bg-red-100', 'text' => 'text-red-700'],
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
