<?php

namespace App\Game\Enums;

enum Formation: string
{
    case F_4_4_2 = '4-4-2';
    case F_4_3_3 = '4-3-3';
    case F_4_2_3_1 = '4-2-3-1';
    case F_3_5_2 = '3-5-2';
    case F_5_3_2 = '5-3-2';
    case F_5_4_1 = '5-4-1';

    public function requirements(): array
    {
        return match ($this) {
            self::F_4_4_2 => ['Goalkeeper' => 1, 'Defender' => 4, 'Midfielder' => 4, 'Forward' => 2],
            self::F_4_3_3 => ['Goalkeeper' => 1, 'Defender' => 4, 'Midfielder' => 3, 'Forward' => 3],
            self::F_4_2_3_1 => ['Goalkeeper' => 1, 'Defender' => 4, 'Midfielder' => 5, 'Forward' => 1],
            self::F_3_5_2 => ['Goalkeeper' => 1, 'Defender' => 3, 'Midfielder' => 5, 'Forward' => 2],
            self::F_5_3_2 => ['Goalkeeper' => 1, 'Defender' => 5, 'Midfielder' => 3, 'Forward' => 2],
            self::F_5_4_1 => ['Goalkeeper' => 1, 'Defender' => 5, 'Midfielder' => 4, 'Forward' => 1],
        };
    }

    /**
     * Attacking modifier for expected goals (1.0 = neutral).
     */
    public function attackModifier(): float
    {
        return match ($this) {
            self::F_4_3_3 => 1.10,   // +10% attack
            self::F_3_5_2 => 1.05,   // +5% attack
            self::F_4_4_2 => 1.00,   // neutral
            self::F_4_2_3_1 => 1.00, // neutral
            self::F_5_3_2 => 0.90,   // -10% attack
            self::F_5_4_1 => 0.85,   // -15% attack
        };
    }

    /**
     * Defensive modifier (reduces opponent's expected goals).
     */
    public function defenseModifier(): float
    {
        return match ($this) {
            self::F_5_4_1 => 0.85,   // -15% conceded
            self::F_5_3_2 => 0.90,   // -10% conceded
            self::F_4_2_3_1 => 0.95, // -5% conceded
            self::F_4_4_2 => 1.00,   // neutral
            self::F_3_5_2 => 1.05,   // +5% conceded
            self::F_4_3_3 => 1.10,   // +10% conceded
        };
    }

    public function label(): string
    {
        return $this->value;
    }
}
