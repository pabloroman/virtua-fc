<?php

namespace App\Support;

/**
 * Maps player positions to pitch slots and provides compatibility scoring.
 *
 * This is the single source of truth for position/slot compatibility in the game.
 * Used by: ShowLineup (passed to JavaScript for pitch visualization), FormationRecommender
 */
class PositionSlotMapper
{
    /**
     * Compatibility matrix: [slot_code => [position => score]]
     * Score: 100 = natural, 80 = very good, 60 = good, 40 = acceptable, 20 = poor, 0 = unsuitable
     */
    public const SLOT_COMPATIBILITY = [
        'GK' => [
            'Goalkeeper' => 100,
        ],
        'CB' => [
            'Centre-Back' => 100,
            'Defensive Midfield' => 60,
            'Left-Back' => 40,
            'Right-Back' => 40,
        ],
        'LB' => [
            'Left-Back' => 100,
            'Left Midfield' => 60,
            'Left Winger' => 40,
            'Centre-Back' => 40,
            'Right-Back' => 30,
        ],
        'RB' => [
            'Right-Back' => 100,
            'Right Midfield' => 60,
            'Right Winger' => 40,
            'Centre-Back' => 40,
            'Left-Back' => 30,
        ],
        'DM' => [
            'Defensive Midfield' => 100,
            'Central Midfield' => 70,
            'Centre-Back' => 50,
            'Attacking Midfield' => 30,
        ],
        'CM' => [
            'Central Midfield' => 100,
            'Defensive Midfield' => 80,
            'Attacking Midfield' => 80,
            'Left Midfield' => 50,
            'Right Midfield' => 50,
        ],
        'AM' => [
            'Attacking Midfield' => 100,
            'Central Midfield' => 70,
            'Second Striker' => 70,
            'Left Winger' => 50,
            'Right Winger' => 50,
            'Centre-Forward' => 40,
        ],
        'LM' => [
            'Left Midfield' => 100,
            'Left Winger' => 80,
            'Left-Back' => 50,
            'Central Midfield' => 40,
            'Attacking Midfield' => 40,
        ],
        'RM' => [
            'Right Midfield' => 100,
            'Right Winger' => 80,
            'Right-Back' => 50,
            'Central Midfield' => 40,
            'Attacking Midfield' => 40,
        ],
        'LW' => [
            'Left Winger' => 100,
            'Left Midfield' => 80,
            'Second Striker' => 50,
            'Right Winger' => 50,
            'Centre-Forward' => 40,
            'Attacking Midfield' => 40,
            'Left-Back' => 20,
        ],
        'RW' => [
            'Right Winger' => 100,
            'Right Midfield' => 80,
            'Second Striker' => 50,
            'Left Winger' => 50,
            'Centre-Forward' => 40,
            'Attacking Midfield' => 40,
            'Right-Back' => 20,
        ],
        'CF' => [
            'Centre-Forward' => 100,
            'Second Striker' => 100,
            'Left Winger' => 50,
            'Right Winger' => 50,
            'Attacking Midfield' => 40,
        ],
    ];

    /**
     * Get compatibility score for a player position in a specific slot.
     */
    public static function getCompatibilityScore(string $position, string $slotCode): int
    {
        return self::SLOT_COMPATIBILITY[$slotCode][$position] ?? 0;
    }

    /**
     * Get all positions that can play in a slot, sorted by compatibility.
     *
     * @return array<string, int> [position => score]
     */
    public static function getCompatiblePositions(string $slotCode): array
    {
        $compatible = self::SLOT_COMPATIBILITY[$slotCode] ?? [];
        arsort($compatible);
        return $compatible;
    }

    /**
     * Calculate effective rating for a player in a specific slot.
     * Applies compatibility penalty to overall score.
     */
    public static function getEffectiveRating(int $overallScore, string $position, string $slotCode): int
    {
        $compatibility = self::getCompatibilityScore($position, $slotCode);

        // Natural position = full rating
        // 0 compatibility = 50% penalty (half the rating)
        $penalty = (100 - $compatibility) / 200;
        $effective = $overallScore * (1 - $penalty);

        return (int) round($effective);
    }

    /**
     * Map slot codes to translation key prefixes.
     */
    private static array $slotToKey = [
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
     * Get localized abbreviation for a slot code (GK, CB, LB, etc.).
     */
    public static function slotToDisplayAbbreviation(string $slotCode): string
    {
        $key = self::$slotToKey[$slotCode] ?? null;

        return $key ? __("positions.{$key}_abbr") : $slotCode;
    }

    /**
     * Get slot code display name.
     */
    public static function getSlotDisplayName(string $slotCode): string
    {
        $key = self::$slotToKey[$slotCode] ?? null;

        return $key ? __("positions.{$key}") : $slotCode;
    }

    /**
     * Get the position group for a slot code.
     * Used to ensure players stay in their position group's area of the pitch.
     */
    public static function getSlotPositionGroup(string $slotCode): string
    {
        return match ($slotCode) {
            'GK' => 'Goalkeeper',
            'CB', 'LB', 'RB' => 'Defender',
            'DM', 'CM', 'AM', 'LM', 'RM' => 'Midfielder',
            'LW', 'RW', 'CF' => 'Forward',
            default => 'Midfielder',
        };
    }

    /**
     * Get all slot codes (12 slots, excluding SS which shares CF).
     *
     * @return string[]
     */
    public static function getAllSlots(): array
    {
        return array_keys(self::SLOT_COMPATIBILITY);
    }

    /**
     * Get the primary (natural) slot for a canonical position.
     *
     * Returns the slot where the position has a compatibility score of 100.
     * Second Striker maps to CF (both have score 100 in CF).
     */
    public static function getPositionPrimarySlot(string $position): ?string
    {
        foreach (self::SLOT_COMPATIBILITY as $slot => $positions) {
            if (($positions[$position] ?? 0) === 100) {
                return $slot;
            }
        }

        return null;
    }

    /**
     * Get the full position-to-primary-slot mapping for all 13 canonical positions.
     *
     * @return array<string, string>  [position_name => slot_code]
     */
    public static function getPositionToSlotMap(): array
    {
        $map = [];
        foreach (PositionMapper::getAllPositions() as $position) {
            $slot = self::getPositionPrimarySlot($position);
            if ($slot !== null) {
                $map[$position] = $slot;
            }
        }

        return $map;
    }
}
