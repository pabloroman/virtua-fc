<?php

namespace App\Support;

class PositionSlotMapper
{
    /**
     * All available pitch slot codes used in Formation::pitchSlots().
     */
    public const SLOTS = [
        'GK',   // Goalkeeper
        'LB',   // Left Back
        'CB',   // Centre Back
        'RB',   // Right Back
        'LWB',  // Left Wing Back
        'RWB',  // Right Wing Back
        'DM',   // Defensive Midfield
        'CM',   // Central Midfield
        'AM',   // Attacking Midfield
        'LM',   // Left Midfield
        'RM',   // Right Midfield
        'LW',   // Left Winger
        'RW',   // Right Winger
        'ST',   // Striker
    ];

    /**
     * All player positions in the database.
     */
    public const POSITIONS = [
        'Goalkeeper',
        'Centre-Back',
        'Left-Back',
        'Right-Back',
        'Defensive Midfield',
        'Central Midfield',
        'Attacking Midfield',
        'Left Midfield',
        'Right Midfield',
        'Left Winger',
        'Right Winger',
        'Centre-Forward',
        'Second Striker',
    ];

    /**
     * Map player position to their natural slot code.
     */
    public const POSITION_TO_NATURAL_SLOT = [
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
        'Centre-Forward' => 'ST',
        'Second Striker' => 'ST',
    ];

    /**
     * Compatibility matrix: [slot_code => [position => score]]
     * Score: 100 = natural, 80 = very good, 60 = good, 40 = acceptable, 20 = poor, 0 = unsuitable
     */
    public const SLOT_COMPATIBILITY = [
        'GK' => [
            'Goalkeeper' => 100,
            // No other position can play GK
        ],
        'CB' => [
            'Centre-Back' => 100,
            'Defensive Midfield' => 60,  // DMs can drop back
            'Left-Back' => 40,           // Fullbacks can play centrally in emergency
            'Right-Back' => 40,
        ],
        'LB' => [
            'Left-Back' => 100,
            'Left Midfield' => 60,       // LMs can drop back
            'Left Winger' => 40,         // Wingers can cover
            'Centre-Back' => 40,         // CBs can slide over
            'Right-Back' => 30,          // Wrong side but same role
        ],
        'RB' => [
            'Right-Back' => 100,
            'Right Midfield' => 60,
            'Right Winger' => 40,
            'Centre-Back' => 40,
            'Left-Back' => 30,
        ],
        'LWB' => [
            'Left-Back' => 100,          // Natural wing-back candidates
            'Left Midfield' => 80,       // LMs are very suited
            'Left Winger' => 60,         // Wingers can adapt
            'Right-Back' => 20,          // Wrong side
        ],
        'RWB' => [
            'Right-Back' => 100,
            'Right Midfield' => 80,
            'Right Winger' => 60,
            'Left-Back' => 20,
        ],
        'DM' => [
            'Defensive Midfield' => 100,
            'Central Midfield' => 70,    // CMs can sit deeper
            'Centre-Back' => 50,         // CBs can step up
            'Attacking Midfield' => 30,  // AMs lack defensive instinct
        ],
        'CM' => [
            'Central Midfield' => 100,
            'Defensive Midfield' => 80,  // DMs can play box-to-box
            'Attacking Midfield' => 80,  // AMs can drop deeper
            'Left Midfield' => 50,       // Wide mids can play centrally
            'Right Midfield' => 50,
        ],
        'AM' => [
            'Attacking Midfield' => 100,
            'Central Midfield' => 70,    // CMs can push forward
            'Second Striker' => 70,      // SS can drop deeper
            'Left Winger' => 50,         // Wingers can play centrally
            'Right Winger' => 50,
            'Centre-Forward' => 40,      // Strikers lack playmaking
        ],
        'LM' => [
            'Left Midfield' => 100,
            'Left Winger' => 80,         // Wingers are very similar
            'Left-Back' => 50,           // LBs can push forward
            'Central Midfield' => 40,    // CMs can play wide
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
            'Second Striker' => 50,      // SS can drift wide
            'Centre-Forward' => 40,      // Strikers can play wide
            'Attacking Midfield' => 40,
            'Left-Back' => 20,           // Emergency only
        ],
        'RW' => [
            'Right Winger' => 100,
            'Right Midfield' => 80,
            'Second Striker' => 50,
            'Centre-Forward' => 40,
            'Attacking Midfield' => 40,
            'Right-Back' => 20,
        ],
        'ST' => [
            'Centre-Forward' => 100,
            'Second Striker' => 100,     // Both are natural strikers
            'Left Winger' => 50,         // Wingers can lead the line
            'Right Winger' => 50,
            'Attacking Midfield' => 40,  // False 9 potential
        ],
    ];

    /**
     * Get the natural slot code for a player position.
     */
    public static function getNaturalSlot(string $position): string
    {
        return self::POSITION_TO_NATURAL_SLOT[$position] ?? 'CM';
    }

    /**
     * Get compatibility score for a player position in a specific slot.
     */
    public static function getCompatibilityScore(string $position, string $slotCode): int
    {
        return self::SLOT_COMPATIBILITY[$slotCode][$position] ?? 0;
    }

    /**
     * Get compatibility display data for a player in a slot.
     *
     * @return array{score: int, label: string, color: string, bgColor: string}
     */
    public static function getCompatibilityDisplay(string $position, string $slotCode): array
    {
        $score = self::getCompatibilityScore($position, $slotCode);

        return match (true) {
            $score >= 100 => ['score' => $score, 'label' => 'Natural', 'color' => 'text-green-600', 'bgColor' => 'bg-green-100'],
            $score >= 80 => ['score' => $score, 'label' => 'Very Good', 'color' => 'text-emerald-600', 'bgColor' => 'bg-emerald-100'],
            $score >= 60 => ['score' => $score, 'label' => 'Good', 'color' => 'text-lime-600', 'bgColor' => 'bg-lime-100'],
            $score >= 40 => ['score' => $score, 'label' => 'Okay', 'color' => 'text-yellow-600', 'bgColor' => 'bg-yellow-100'],
            $score >= 20 => ['score' => $score, 'label' => 'Poor', 'color' => 'text-orange-500', 'bgColor' => 'bg-orange-100'],
            default => ['score' => $score, 'label' => 'Unsuitable', 'color' => 'text-red-600', 'bgColor' => 'bg-red-100'],
        };
    }

    /**
     * Check if a position can play in a slot at all.
     */
    public static function canPlayInSlot(string $position, string $slotCode): bool
    {
        return self::getCompatibilityScore($position, $slotCode) > 0;
    }

    /**
     * Check if a position is natural for a slot.
     */
    public static function isNaturalPosition(string $position, string $slotCode): bool
    {
        return self::getCompatibilityScore($position, $slotCode) >= 100;
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
     * Get all slots a position can play in, sorted by compatibility.
     *
     * @return array<string, int> [slot => score]
     */
    public static function getCompatibleSlots(string $position): array
    {
        $slots = [];
        foreach (self::SLOT_COMPATIBILITY as $slotCode => $positions) {
            if (isset($positions[$position])) {
                $slots[$slotCode] = $positions[$position];
            }
        }
        arsort($slots);
        return $slots;
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
        $penalty = (100 - $compatibility) / 200; // Max 50% penalty
        $effective = $overallScore * (1 - $penalty);

        return (int) round($effective);
    }

    /**
     * Get slot code display name.
     */
    public static function getSlotDisplayName(string $slotCode): string
    {
        return match ($slotCode) {
            'GK' => 'Goalkeeper',
            'CB' => 'Centre-Back',
            'LB' => 'Left-Back',
            'RB' => 'Right-Back',
            'LWB' => 'Left Wing-Back',
            'RWB' => 'Right Wing-Back',
            'DM' => 'Defensive Midfield',
            'CM' => 'Central Midfield',
            'AM' => 'Attacking Midfield',
            'LM' => 'Left Midfield',
            'RM' => 'Right Midfield',
            'LW' => 'Left Winger',
            'RW' => 'Right Winger',
            'ST' => 'Striker',
            default => $slotCode,
        };
    }

    /**
     * Get the position group for a slot code (for validation).
     */
    public static function getSlotPositionGroup(string $slotCode): string
    {
        return match ($slotCode) {
            'GK' => 'Goalkeeper',
            'CB', 'LB', 'RB', 'LWB', 'RWB' => 'Defender',
            'DM', 'CM', 'AM', 'LM', 'RM' => 'Midfielder',
            'LW', 'RW', 'ST' => 'Forward',
            default => 'Midfielder',
        };
    }
}
