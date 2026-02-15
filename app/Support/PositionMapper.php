<?php

namespace App\Support;

class PositionMapper
{
    /**
     * Map canonical (English) position names to translation key prefixes.
     */
    private static array $positionToKey = [
        'Goalkeeper' => 'goalkeeper',
        'Centre-Back' => 'centre_back',
        'Left-Back' => 'left_back',
        'Right-Back' => 'right_back',
        'Defensive Midfield' => 'defensive_midfield',
        'Central Midfield' => 'central_midfield',
        'Attacking Midfield' => 'attacking_midfield',
        'Left Midfield' => 'left_midfield',
        'Right Midfield' => 'right_midfield',
        'Left Winger' => 'left_winger',
        'Right Winger' => 'right_winger',
        'Centre-Forward' => 'centre_forward',
        'Second Striker' => 'second_striker',
    ];

    /**
     * Map internal slot codes to translation key prefixes.
     */
    private static array $slotToKey = [
        'GK' => 'goalkeeper',
        'CB' => 'centre_back',
        'LB' => 'left_back',
        'RB' => 'right_back',
        'LWB' => 'left_wing_back',
        'RWB' => 'right_wing_back',
        'DM' => 'defensive_midfield',
        'CM' => 'central_midfield',
        'AM' => 'attacking_midfield',
        'LM' => 'left_midfield',
        'RM' => 'right_midfield',
        'LW' => 'left_winger',
        'RW' => 'right_winger',
        'CF' => 'centre_forward',
        'SS' => 'second_striker',
    ];

    /**
     * Map canonical position names to position groups.
     */
    private static array $positionToGroup = [
        'Goalkeeper' => 'Goalkeeper',
        'Centre-Back' => 'Defender',
        'Left-Back' => 'Defender',
        'Right-Back' => 'Defender',
        'Defensive Midfield' => 'Midfielder',
        'Central Midfield' => 'Midfielder',
        'Attacking Midfield' => 'Midfielder',
        'Left Midfield' => 'Midfielder',
        'Right Midfield' => 'Midfielder',
        'Left Winger' => 'Forward',
        'Right Winger' => 'Forward',
        'Centre-Forward' => 'Forward',
        'Second Striker' => 'Forward',
    ];

    /**
     * CSS color classes keyed by position group (structural, not localized).
     */
    private static array $groupColors = [
        'Goalkeeper' => ['bg' => 'bg-amber-500', 'text' => 'text-white'],
        'Defender' => ['bg' => 'bg-blue-600', 'text' => 'text-white'],
        'Midfielder' => ['bg' => 'bg-emerald-600', 'text' => 'text-white'],
        'Forward' => ['bg' => 'bg-red-600', 'text' => 'text-white'],
    ];

    /**
     * Map scout filter values to translation key prefixes.
     */
    private static array $filterToKey = [
        'GK' => 'goalkeeper',
        'CB' => 'centre_back',
        'LB' => 'left_back',
        'RB' => 'right_back',
        'DM' => 'defensive_midfield',
        'CM' => 'central_midfield',
        'AM' => 'attacking_midfield',
        'LM' => 'left_midfield',
        'RM' => 'right_midfield',
        'LW' => 'left_winger',
        'RW' => 'right_winger',
        'CF' => 'centre_forward',
        'SS' => 'second_striker',
    ];

    /**
     * Get localized abbreviation for a canonical position name.
     */
    public static function toAbbreviation(string $position): string
    {
        $key = self::$positionToKey[$position] ?? null;

        return $key ? __("positions.{$key}_abbr") : __('positions.central_midfield_abbr');
    }

    /**
     * Get localized display name for a canonical position name.
     */
    public static function toDisplayName(string $position): string
    {
        $key = self::$positionToKey[$position] ?? null;

        return $key ? __("positions.{$key}") : $position;
    }

    /**
     * Get localized abbreviation for an internal slot code (GK, CB, LWB, etc.).
     */
    public static function slotToDisplayAbbreviation(string $slotCode): string
    {
        $key = self::$slotToKey[$slotCode] ?? null;

        return $key ? __("positions.{$key}_abbr") : $slotCode;
    }

    /**
     * Get localized display name for a scout search filter value (GK, CB, any_defender, etc.).
     */
    public static function filterToDisplayName(string $filterValue): string
    {
        // Group filters have their own translation keys
        if (in_array($filterValue, ['any_defender', 'any_midfielder', 'any_forward'])) {
            return __("positions.{$filterValue}");
        }

        $key = self::$filterToKey[$filterValue] ?? null;

        return $key ? __("positions.{$key}") : $filterValue;
    }

    /**
     * Get CSS color classes for a position group.
     *
     * @return array{bg: string, text: string}
     */
    public static function getColorsForGroup(string $group): array
    {
        return self::$groupColors[$group] ?? self::$groupColors['Midfielder'];
    }

    /**
     * Get CSS color classes for a canonical position name.
     *
     * @return array{bg: string, text: string}
     */
    public static function getColors(string $position): array
    {
        $group = self::$positionToGroup[$position] ?? 'Midfielder';

        return self::getColorsForGroup($group);
    }

    /**
     * Get abbreviation with color classes for a canonical position name.
     *
     * @return array{abbreviation: string, bg: string, text: string}
     */
    public static function getPositionDisplay(string $position): array
    {
        $abbreviation = self::toAbbreviation($position);
        $group = self::$positionToGroup[$position] ?? 'Midfielder';
        $colors = self::getColorsForGroup($group);

        return [
            'abbreviation' => $abbreviation,
            'bg' => $colors['bg'],
            'text' => $colors['text'],
        ];
    }

    /**
     * Get the position group for a canonical position name.
     */
    public static function getPositionGroup(string $position): string
    {
        return self::$positionToGroup[$position] ?? 'Midfielder';
    }

    /**
     * Get localized group abbreviation for a position group name.
     */
    public static function getGroupAbbreviation(string $group): string
    {
        $key = match ($group) {
            'Goalkeeper' => 'group_goalkeeper_abbr',
            'Defender' => 'group_defender_abbr',
            'Midfielder' => 'group_midfielder_abbr',
            'Forward' => 'group_forward_abbr',
            default => 'group_midfielder_abbr',
        };

        return __("positions.{$key}");
    }
}
