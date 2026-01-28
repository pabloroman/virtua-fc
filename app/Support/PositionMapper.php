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
        'GK' => ['bg' => 'bg-amber-100', 'text' => 'text-amber-700'],

        // Defenders
        'CB' => ['bg' => 'bg-blue-100', 'text' => 'text-blue-700'],
        'LB' => ['bg' => 'bg-blue-100', 'text' => 'text-blue-700'],
        'RB' => ['bg' => 'bg-blue-100', 'text' => 'text-blue-700'],
        'DF' => ['bg' => 'bg-blue-100', 'text' => 'text-blue-700'],

        // Midfielders
        'DM' => ['bg' => 'bg-green-100', 'text' => 'text-green-700'],
        'CM' => ['bg' => 'bg-green-100', 'text' => 'text-green-700'],
        'AM' => ['bg' => 'bg-green-100', 'text' => 'text-green-700'],
        'LM' => ['bg' => 'bg-green-100', 'text' => 'text-green-700'],
        'RM' => ['bg' => 'bg-green-100', 'text' => 'text-green-700'],
        'MF' => ['bg' => 'bg-green-100', 'text' => 'text-green-700'],

        // Forwards
        'LW' => ['bg' => 'bg-red-100', 'text' => 'text-red-700'],
        'RW' => ['bg' => 'bg-red-100', 'text' => 'text-red-700'],
        'CF' => ['bg' => 'bg-red-100', 'text' => 'text-red-700'],
        'SS' => ['bg' => 'bg-red-100', 'text' => 'text-red-700'],
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
