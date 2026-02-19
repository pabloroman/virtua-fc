<?php

namespace App\Modules\Lineup\Enums;

enum Formation: string
{
    case F_4_4_2 = '4-4-2';
    case F_4_3_3 = '4-3-3';
    case F_4_2_3_1 = '4-2-3-1';
    case F_3_4_3 = '3-4-3';
    case F_3_5_2 = '3-5-2';
    case F_4_1_4_1 = '4-1-4-1';
    case F_5_3_2 = '5-3-2';
    case F_5_4_1 = '5-4-1';

    public function requirements(): array
    {
        return match ($this) {
            self::F_4_4_2 => ['Goalkeeper' => 1, 'Defender' => 4, 'Midfielder' => 4, 'Forward' => 2],
            self::F_4_3_3 => ['Goalkeeper' => 1, 'Defender' => 4, 'Midfielder' => 3, 'Forward' => 3],
            self::F_3_4_3 => ['Goalkeeper' => 1, 'Defender' => 3, 'Midfielder' => 4, 'Forward' => 3],
            self::F_4_2_3_1 => ['Goalkeeper' => 1, 'Defender' => 4, 'Midfielder' => 5, 'Forward' => 1],
            self::F_3_5_2 => ['Goalkeeper' => 1, 'Defender' => 3, 'Midfielder' => 5, 'Forward' => 2],
            self::F_4_1_4_1 => ['Goalkeeper' => 1, 'Defender' => 4, 'Midfielder' => 5, 'Forward' => 1],
            self::F_5_3_2 => ['Goalkeeper' => 1, 'Defender' => 5, 'Midfielder' => 3, 'Forward' => 2],
            self::F_5_4_1 => ['Goalkeeper' => 1, 'Defender' => 5, 'Midfielder' => 4, 'Forward' => 1],
        };
    }

    /**
     * Attacking modifier for expected goals (1.0 = neutral).
     */
    public function attackModifier(): float
    {
        return (float) config("match_simulation.formations.{$this->value}.attack", 1.00);
    }

    /**
     * Defensive modifier (reduces opponent's expected goals).
     */
    public function defenseModifier(): float
    {
        return (float) config("match_simulation.formations.{$this->value}.defense", 1.00);
    }

    public function label(): string
    {
        return $this->value;
    }

    public function tooltip(): string
    {
        $attack = $this->attackModifier();
        $defense = $this->defenseModifier();

        $attackPct = self::formatModifierPct($attack);
        $defensePct = self::formatModifierPct($defense);

        $key = match ($this) {
            self::F_4_4_2 => 'game.formation_tip_442',
            self::F_4_3_3 => 'game.formation_tip_433',
            self::F_4_2_3_1 => 'game.formation_tip_4231',
            self::F_3_4_3 => 'game.formation_tip_343',
            self::F_3_5_2 => 'game.formation_tip_352',
            self::F_4_1_4_1 => 'game.formation_tip_4141',
            self::F_5_3_2 => 'game.formation_tip_532',
            self::F_5_4_1 => 'game.formation_tip_541',
        };

        return __($key, ['attack' => $attackPct, 'defense' => $defensePct]);
    }

    private static function formatModifierPct(float $modifier): string
    {
        $pct = (int) round(($modifier - 1.0) * 100);

        return ($pct >= 0 ? '+' : '').$pct.'%';
    }

    /**
     * Get pitch slot positions for visual formation display.
     * Returns array of slots with: id, role (position group), x (0-100), y (0-100), label
     * Y: 0 = goal line, 100 = opponent goal
     *
     * @return array<array{id: int, role: string, x: int, y: int, label: string}>
     */
    public function pitchSlots(): array
    {
        return match ($this) {
            self::F_4_4_2 => [
                ['id' => 0, 'role' => 'Goalkeeper', 'x' => 50, 'y' => 10, 'label' => 'GK'],
                ['id' => 1, 'role' => 'Defender', 'x' => 15, 'y' => 28, 'label' => 'LB'],
                ['id' => 2, 'role' => 'Defender', 'x' => 38, 'y' => 28, 'label' => 'CB'],
                ['id' => 3, 'role' => 'Defender', 'x' => 62, 'y' => 28, 'label' => 'CB'],
                ['id' => 4, 'role' => 'Defender', 'x' => 85, 'y' => 28, 'label' => 'RB'],
                ['id' => 5, 'role' => 'Midfielder', 'x' => 15, 'y' => 55, 'label' => 'LM'],
                ['id' => 6, 'role' => 'Midfielder', 'x' => 38, 'y' => 55, 'label' => 'CM'],
                ['id' => 7, 'role' => 'Midfielder', 'x' => 62, 'y' => 55, 'label' => 'CM'],
                ['id' => 8, 'role' => 'Midfielder', 'x' => 85, 'y' => 55, 'label' => 'RM'],
                ['id' => 9, 'role' => 'Forward', 'x' => 35, 'y' => 80, 'label' => 'CF'],
                ['id' => 10, 'role' => 'Forward', 'x' => 65, 'y' => 80, 'label' => 'CF'],
            ],
            self::F_4_3_3 => [
                ['id' => 0, 'role' => 'Goalkeeper', 'x' => 50, 'y' => 10, 'label' => 'GK'],
                ['id' => 1, 'role' => 'Defender', 'x' => 15, 'y' => 28, 'label' => 'LB'],
                ['id' => 2, 'role' => 'Defender', 'x' => 38, 'y' => 28, 'label' => 'CB'],
                ['id' => 3, 'role' => 'Defender', 'x' => 62, 'y' => 28, 'label' => 'CB'],
                ['id' => 4, 'role' => 'Defender', 'x' => 85, 'y' => 28, 'label' => 'RB'],
                ['id' => 5, 'role' => 'Midfielder', 'x' => 25, 'y' => 55, 'label' => 'CM'],
                ['id' => 6, 'role' => 'Midfielder', 'x' => 50, 'y' => 55, 'label' => 'CM'],
                ['id' => 7, 'role' => 'Midfielder', 'x' => 75, 'y' => 55, 'label' => 'CM'],
                ['id' => 8, 'role' => 'Forward', 'x' => 15, 'y' => 78, 'label' => 'LW'],
                ['id' => 9, 'role' => 'Forward', 'x' => 50, 'y' => 82, 'label' => 'CF'],
                ['id' => 10, 'role' => 'Forward', 'x' => 85, 'y' => 78, 'label' => 'RW'],
            ],
            self::F_4_2_3_1 => [
                ['id' => 0, 'role' => 'Goalkeeper', 'x' => 50, 'y' => 10, 'label' => 'GK'],
                ['id' => 1, 'role' => 'Defender', 'x' => 15, 'y' => 25, 'label' => 'LB'],
                ['id' => 2, 'role' => 'Defender', 'x' => 38, 'y' => 25, 'label' => 'CB'],
                ['id' => 3, 'role' => 'Defender', 'x' => 62, 'y' => 25, 'label' => 'CB'],
                ['id' => 4, 'role' => 'Defender', 'x' => 85, 'y' => 25, 'label' => 'RB'],
                ['id' => 5, 'role' => 'Midfielder', 'x' => 35, 'y' => 45, 'label' => 'DM'],
                ['id' => 6, 'role' => 'Midfielder', 'x' => 65, 'y' => 45, 'label' => 'DM'],
                ['id' => 7, 'role' => 'Midfielder', 'x' => 15, 'y' => 62, 'label' => 'LM'],
                ['id' => 8, 'role' => 'Midfielder', 'x' => 50, 'y' => 62, 'label' => 'AM'],
                ['id' => 9, 'role' => 'Midfielder', 'x' => 85, 'y' => 62, 'label' => 'RM'],
                ['id' => 10, 'role' => 'Forward', 'x' => 50, 'y' => 82, 'label' => 'CF'],
            ],
            self::F_3_4_3 => [
                ['id' => 0, 'role' => 'Goalkeeper', 'x' => 50, 'y' => 10, 'label' => 'GK'],
                ['id' => 1, 'role' => 'Defender', 'x' => 25, 'y' => 25, 'label' => 'CB'],
                ['id' => 2, 'role' => 'Defender', 'x' => 50, 'y' => 25, 'label' => 'CB'],
                ['id' => 3, 'role' => 'Defender', 'x' => 75, 'y' => 25, 'label' => 'CB'],
                ['id' => 4, 'role' => 'Midfielder', 'x' => 35, 'y' => 42, 'label' => 'DM'],
                ['id' => 5, 'role' => 'Midfielder', 'x' => 65, 'y' => 42, 'label' => 'DM'],
                ['id' => 6, 'role' => 'Midfielder', 'x' => 30, 'y' => 60, 'label' => 'CM'],
                ['id' => 7, 'role' => 'Midfielder', 'x' => 70, 'y' => 60, 'label' => 'CM'],
                ['id' => 8, 'role' => 'Forward', 'x' => 15, 'y' => 78, 'label' => 'LW'],
                ['id' => 9, 'role' => 'Forward', 'x' => 50, 'y' => 82, 'label' => 'CF'],
                ['id' => 10, 'role' => 'Forward', 'x' => 85, 'y' => 78, 'label' => 'RW'],
            ],
            self::F_3_5_2 => [
                ['id' => 0, 'role' => 'Goalkeeper', 'x' => 50, 'y' => 10, 'label' => 'GK'],
                ['id' => 1, 'role' => 'Defender', 'x' => 25, 'y' => 25, 'label' => 'CB'],
                ['id' => 2, 'role' => 'Defender', 'x' => 50, 'y' => 25, 'label' => 'CB'],
                ['id' => 3, 'role' => 'Defender', 'x' => 75, 'y' => 25, 'label' => 'CB'],
                ['id' => 4, 'role' => 'Midfielder', 'x' => 10, 'y' => 50, 'label' => 'LWB'],
                ['id' => 5, 'role' => 'Midfielder', 'x' => 35, 'y' => 45, 'label' => 'CM'],
                ['id' => 6, 'role' => 'Midfielder', 'x' => 50, 'y' => 55, 'label' => 'AM'],
                ['id' => 7, 'role' => 'Midfielder', 'x' => 65, 'y' => 45, 'label' => 'CM'],
                ['id' => 8, 'role' => 'Midfielder', 'x' => 90, 'y' => 50, 'label' => 'RWB'],
                ['id' => 9, 'role' => 'Forward', 'x' => 35, 'y' => 78, 'label' => 'CF'],
                ['id' => 10, 'role' => 'Forward', 'x' => 65, 'y' => 78, 'label' => 'CF'],
            ],
            self::F_4_1_4_1 => [
                ['id' => 0, 'role' => 'Goalkeeper', 'x' => 50, 'y' => 10, 'label' => 'GK'],
                ['id' => 1, 'role' => 'Defender', 'x' => 15, 'y' => 25, 'label' => 'LB'],
                ['id' => 2, 'role' => 'Defender', 'x' => 38, 'y' => 25, 'label' => 'CB'],
                ['id' => 3, 'role' => 'Defender', 'x' => 62, 'y' => 25, 'label' => 'CB'],
                ['id' => 4, 'role' => 'Defender', 'x' => 85, 'y' => 25, 'label' => 'RB'],
                ['id' => 5, 'role' => 'Midfielder', 'x' => 50, 'y' => 40, 'label' => 'DM'],
                ['id' => 6, 'role' => 'Midfielder', 'x' => 15, 'y' => 58, 'label' => 'LM'],
                ['id' => 7, 'role' => 'Midfielder', 'x' => 38, 'y' => 58, 'label' => 'CM'],
                ['id' => 8, 'role' => 'Midfielder', 'x' => 62, 'y' => 58, 'label' => 'CM'],
                ['id' => 9, 'role' => 'Midfielder', 'x' => 85, 'y' => 58, 'label' => 'RM'],
                ['id' => 10, 'role' => 'Forward', 'x' => 50, 'y' => 80, 'label' => 'CF'],
            ],
            self::F_5_3_2 => [
                ['id' => 0, 'role' => 'Goalkeeper', 'x' => 50, 'y' => 10, 'label' => 'GK'],
                ['id' => 1, 'role' => 'Defender', 'x' => 10, 'y' => 35, 'label' => 'LWB'],
                ['id' => 2, 'role' => 'Defender', 'x' => 30, 'y' => 25, 'label' => 'CB'],
                ['id' => 3, 'role' => 'Defender', 'x' => 50, 'y' => 25, 'label' => 'CB'],
                ['id' => 4, 'role' => 'Defender', 'x' => 70, 'y' => 25, 'label' => 'CB'],
                ['id' => 5, 'role' => 'Defender', 'x' => 90, 'y' => 35, 'label' => 'RWB'],
                ['id' => 6, 'role' => 'Midfielder', 'x' => 30, 'y' => 50, 'label' => 'CM'],
                ['id' => 7, 'role' => 'Midfielder', 'x' => 50, 'y' => 50, 'label' => 'CM'],
                ['id' => 8, 'role' => 'Midfielder', 'x' => 70, 'y' => 50, 'label' => 'CM'],
                ['id' => 9, 'role' => 'Forward', 'x' => 35, 'y' => 78, 'label' => 'CF'],
                ['id' => 10, 'role' => 'Forward', 'x' => 65, 'y' => 78, 'label' => 'CF'],
            ],
            self::F_5_4_1 => [
                ['id' => 0, 'role' => 'Goalkeeper', 'x' => 50, 'y' => 10, 'label' => 'GK'],
                ['id' => 1, 'role' => 'Defender', 'x' => 10, 'y' => 25, 'label' => 'LWB'],
                ['id' => 2, 'role' => 'Defender', 'x' => 30, 'y' => 25, 'label' => 'CB'],
                ['id' => 3, 'role' => 'Defender', 'x' => 50, 'y' => 25, 'label' => 'CB'],
                ['id' => 4, 'role' => 'Defender', 'x' => 70, 'y' => 25, 'label' => 'CB'],
                ['id' => 5, 'role' => 'Defender', 'x' => 90, 'y' => 25, 'label' => 'RWB'],
                ['id' => 6, 'role' => 'Midfielder', 'x' => 15, 'y' => 55, 'label' => 'LM'],
                ['id' => 7, 'role' => 'Midfielder', 'x' => 38, 'y' => 55, 'label' => 'CM'],
                ['id' => 8, 'role' => 'Midfielder', 'x' => 62, 'y' => 55, 'label' => 'CM'],
                ['id' => 9, 'role' => 'Midfielder', 'x' => 85, 'y' => 55, 'label' => 'RM'],
                ['id' => 10, 'role' => 'Forward', 'x' => 50, 'y' => 80, 'label' => 'CF'],
            ],
        };
    }
}
