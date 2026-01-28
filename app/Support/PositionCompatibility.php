<?php

namespace App\Support;

class PositionCompatibility
{
    /**
     * Position compatibility matrix.
     * Key = natural position, Value = array of [target_position => compatibility_score]
     * Score: 100 = natural, 75 = good, 50 = okay, 25 = poor, 0 = unsuitable
     */
    private static array $compatibility = [
        'Goalkeeper' => [
            'Goalkeeper' => 100,
        ],
        'Centre-Back' => [
            'Goalkeeper' => 0,
            'Defender' => 100,
            'Midfielder' => 25,
            'Forward' => 0,
        ],
        'Left-Back' => [
            'Goalkeeper' => 0,
            'Defender' => 100,
            'Midfielder' => 50,
            'Forward' => 25,
        ],
        'Right-Back' => [
            'Goalkeeper' => 0,
            'Defender' => 100,
            'Midfielder' => 50,
            'Forward' => 25,
        ],
        'Defensive Midfield' => [
            'Goalkeeper' => 0,
            'Defender' => 75,
            'Midfielder' => 100,
            'Forward' => 25,
        ],
        'Central Midfield' => [
            'Goalkeeper' => 0,
            'Defender' => 50,
            'Midfielder' => 100,
            'Forward' => 50,
        ],
        'Attacking Midfield' => [
            'Goalkeeper' => 0,
            'Defender' => 25,
            'Midfielder' => 100,
            'Forward' => 75,
        ],
        'Left Midfield' => [
            'Goalkeeper' => 0,
            'Defender' => 50,
            'Midfielder' => 100,
            'Forward' => 75,
        ],
        'Right Midfield' => [
            'Goalkeeper' => 0,
            'Defender' => 50,
            'Midfielder' => 100,
            'Forward' => 75,
        ],
        'Left Winger' => [
            'Goalkeeper' => 0,
            'Defender' => 25,
            'Midfielder' => 75,
            'Forward' => 100,
        ],
        'Right Winger' => [
            'Goalkeeper' => 0,
            'Defender' => 25,
            'Midfielder' => 75,
            'Forward' => 100,
        ],
        'Centre-Forward' => [
            'Goalkeeper' => 0,
            'Defender' => 0,
            'Midfielder' => 25,
            'Forward' => 100,
        ],
        'Second Striker' => [
            'Goalkeeper' => 0,
            'Defender' => 0,
            'Midfielder' => 50,
            'Forward' => 100,
        ],
    ];

    /**
     * Get compatibility score for a player in a given role.
     *
     * @param string $naturalPosition Player's natural position
     * @param string $targetRole Target position group (Goalkeeper, Defender, Midfielder, Forward)
     * @return int Score 0-100
     */
    public static function getScore(string $naturalPosition, string $targetRole): int
    {
        return self::$compatibility[$naturalPosition][$targetRole] ?? 50;
    }

    /**
     * Get compatibility label and color for display.
     *
     * @return array{label: string, color: string, score: int}
     */
    public static function getDisplay(string $naturalPosition, string $targetRole): array
    {
        $score = self::getScore($naturalPosition, $targetRole);

        return match (true) {
            $score >= 100 => ['label' => 'Natural', 'color' => 'text-green-600', 'bgColor' => 'bg-green-100', 'score' => $score],
            $score >= 75 => ['label' => 'Good', 'color' => 'text-lime-600', 'bgColor' => 'bg-lime-100', 'score' => $score],
            $score >= 50 => ['label' => 'Okay', 'color' => 'text-yellow-600', 'bgColor' => 'bg-yellow-100', 'score' => $score],
            $score >= 25 => ['label' => 'Poor', 'color' => 'text-orange-600', 'bgColor' => 'bg-orange-100', 'score' => $score],
            default => ['label' => 'Unsuitable', 'color' => 'text-red-600', 'bgColor' => 'bg-red-100', 'score' => $score],
        };
    }

    /**
     * Map position group to natural position for comparison.
     */
    public static function positionToGroup(string $position): string
    {
        return match ($position) {
            'Goalkeeper' => 'Goalkeeper',
            'Centre-Back', 'Left-Back', 'Right-Back' => 'Defender',
            'Defensive Midfield', 'Central Midfield', 'Attacking Midfield',
            'Left Midfield', 'Right Midfield' => 'Midfielder',
            'Left Winger', 'Right Winger', 'Centre-Forward', 'Second Striker' => 'Forward',
            default => 'Midfielder',
        };
    }

    /**
     * Check if player is playing out of position.
     */
    public static function isOutOfPosition(string $naturalPosition, string $targetRole): bool
    {
        return self::getScore($naturalPosition, $targetRole) < 100;
    }
}
