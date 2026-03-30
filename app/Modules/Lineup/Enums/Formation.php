<?php

namespace App\Modules\Lineup\Enums;

enum Formation: string
{
    case F_4_3_3 = '4-3-3';
    case F_4_4_2 = '4-4-2';
    case F_4_2_3_1 = '4-2-3-1';
    case F_3_4_3 = '3-4-3';
    case F_3_5_2 = '3-5-2';
    case F_4_1_4_1 = '4-1-4-1';
    case F_5_3_2 = '5-3-2';
    case F_5_4_1 = '5-4-1';
    case F_4_1_2_3 = '4-1-2-3';
    case F_4_3_2_1 = '4-3-2-1';

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
            self::F_4_1_2_3 => ['Goalkeeper' => 1, 'Defender' => 4, 'Midfielder' => 3, 'Forward' => 3],
            self::F_4_3_2_1 => ['Goalkeeper' => 1, 'Defender' => 4, 'Midfielder' => 5, 'Forward' => 1],
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
            self::F_4_1_2_3 => 'game.formation_tip_4123',
            self::F_4_3_2_1 => 'game.formation_tip_4321',
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
     * Returns array of slots with: id, role (position group), col (0-8), row (0-13), label
     * Positions are defined as cells on a 9×14 grid (see PitchGrid).
     *
     * @return array<array{id: int, role: string, col: int, row: int, label: string}>
     */
    public function pitchSlots(): array
    {
        return match ($this) {
            self::F_4_4_2 => [
                ['id' => 0, 'role' => 'Goalkeeper', 'col' => 4, 'row' => 0, 'label' => 'GK'],
                ['id' => 1, 'role' => 'Defender', 'col' => 1, 'row' => 3, 'label' => 'LB'],
                ['id' => 2, 'role' => 'Defender', 'col' => 3, 'row' => 3, 'label' => 'CB'],
                ['id' => 3, 'role' => 'Defender', 'col' => 5, 'row' => 3, 'label' => 'CB'],
                ['id' => 4, 'role' => 'Defender', 'col' => 7, 'row' => 3, 'label' => 'RB'],
                ['id' => 5, 'role' => 'Midfielder', 'col' => 1, 'row' => 7, 'label' => 'LM'],
                ['id' => 6, 'role' => 'Midfielder', 'col' => 3, 'row' => 7, 'label' => 'CM'],
                ['id' => 7, 'role' => 'Midfielder', 'col' => 5, 'row' => 7, 'label' => 'CM'],
                ['id' => 8, 'role' => 'Midfielder', 'col' => 7, 'row' => 7, 'label' => 'RM'],
                ['id' => 9, 'role' => 'Forward', 'col' => 3, 'row' => 11, 'label' => 'CF'],
                ['id' => 10, 'role' => 'Forward', 'col' => 5, 'row' => 11, 'label' => 'CF'],
            ],
            self::F_4_3_3 => [
                ['id' => 0, 'role' => 'Goalkeeper', 'col' => 4, 'row' => 0, 'label' => 'GK'],
                ['id' => 1, 'role' => 'Defender', 'col' => 1, 'row' => 3, 'label' => 'LB'],
                ['id' => 2, 'role' => 'Defender', 'col' => 3, 'row' => 3, 'label' => 'CB'],
                ['id' => 3, 'role' => 'Defender', 'col' => 5, 'row' => 3, 'label' => 'CB'],
                ['id' => 4, 'role' => 'Defender', 'col' => 7, 'row' => 3, 'label' => 'RB'],
                ['id' => 5, 'role' => 'Midfielder', 'col' => 2, 'row' => 7, 'label' => 'CM'],
                ['id' => 6, 'role' => 'Midfielder', 'col' => 4, 'row' => 7, 'label' => 'CM'],
                ['id' => 7, 'role' => 'Midfielder', 'col' => 6, 'row' => 7, 'label' => 'CM'],
                ['id' => 8, 'role' => 'Forward', 'col' => 1, 'row' => 10, 'label' => 'LW'],
                ['id' => 9, 'role' => 'Forward', 'col' => 4, 'row' => 11, 'label' => 'CF'],
                ['id' => 10, 'role' => 'Forward', 'col' => 7, 'row' => 10, 'label' => 'RW'],
            ],
            self::F_4_2_3_1 => [
                ['id' => 0, 'role' => 'Goalkeeper', 'col' => 4, 'row' => 0, 'label' => 'GK'],
                ['id' => 1, 'role' => 'Defender', 'col' => 1, 'row' => 3, 'label' => 'LB'],
                ['id' => 2, 'role' => 'Defender', 'col' => 3, 'row' => 3, 'label' => 'CB'],
                ['id' => 3, 'role' => 'Defender', 'col' => 5, 'row' => 3, 'label' => 'CB'],
                ['id' => 4, 'role' => 'Defender', 'col' => 7, 'row' => 3, 'label' => 'RB'],
                ['id' => 5, 'role' => 'Midfielder', 'col' => 3, 'row' => 6, 'label' => 'DM'],
                ['id' => 6, 'role' => 'Midfielder', 'col' => 5, 'row' => 6, 'label' => 'DM'],
                ['id' => 7, 'role' => 'Midfielder', 'col' => 1, 'row' => 9, 'label' => 'LM'],
                ['id' => 8, 'role' => 'Midfielder', 'col' => 4, 'row' => 9, 'label' => 'AM'],
                ['id' => 9, 'role' => 'Midfielder', 'col' => 7, 'row' => 9, 'label' => 'RM'],
                ['id' => 10, 'role' => 'Forward', 'col' => 4, 'row' => 12, 'label' => 'CF'],
            ],
            self::F_3_4_3 => [
                ['id' => 0, 'role' => 'Goalkeeper', 'col' => 4, 'row' => 0, 'label' => 'GK'],
                ['id' => 1, 'role' => 'Defender', 'col' => 2, 'row' => 3, 'label' => 'CB'],
                ['id' => 2, 'role' => 'Defender', 'col' => 4, 'row' => 3, 'label' => 'CB'],
                ['id' => 3, 'role' => 'Defender', 'col' => 6, 'row' => 3, 'label' => 'CB'],
                ['id' => 4, 'role' => 'Midfielder', 'col' => 1, 'row' => 7, 'label' => 'CM'],
                ['id' => 5, 'role' => 'Midfielder', 'col' => 3, 'row' => 7, 'label' => 'CM'],
                ['id' => 6, 'role' => 'Midfielder', 'col' => 5, 'row' => 7, 'label' => 'CM'],
                ['id' => 7, 'role' => 'Midfielder', 'col' => 7, 'row' => 7, 'label' => 'CM'],
                ['id' => 8, 'role' => 'Forward', 'col' => 1, 'row' => 10, 'label' => 'LW'],
                ['id' => 9, 'role' => 'Forward', 'col' => 4, 'row' => 11, 'label' => 'CF'],
                ['id' => 10, 'role' => 'Forward', 'col' => 7, 'row' => 10, 'label' => 'RW'],
            ],
            self::F_3_5_2 => [
                ['id' => 0, 'role' => 'Goalkeeper', 'col' => 4, 'row' => 0, 'label' => 'GK'],
                ['id' => 1, 'role' => 'Defender', 'col' => 2, 'row' => 3, 'label' => 'CB'],
                ['id' => 2, 'role' => 'Defender', 'col' => 4, 'row' => 3, 'label' => 'CB'],
                ['id' => 3, 'role' => 'Defender', 'col' => 6, 'row' => 3, 'label' => 'CB'],
                ['id' => 4, 'role' => 'Midfielder', 'col' => 0, 'row' => 7, 'label' => 'LM'],
                ['id' => 5, 'role' => 'Midfielder', 'col' => 2, 'row' => 7, 'label' => 'CM'],
                ['id' => 6, 'role' => 'Midfielder', 'col' => 4, 'row' => 7, 'label' => 'CM'],
                ['id' => 7, 'role' => 'Midfielder', 'col' => 6, 'row' => 7, 'label' => 'CM'],
                ['id' => 8, 'role' => 'Midfielder', 'col' => 8, 'row' => 7, 'label' => 'RM'],
                ['id' => 9, 'role' => 'Forward', 'col' => 3, 'row' => 11, 'label' => 'CF'],
                ['id' => 10, 'role' => 'Forward', 'col' => 5, 'row' => 11, 'label' => 'CF'],
            ],
            self::F_4_1_4_1 => [
                ['id' => 0, 'role' => 'Goalkeeper', 'col' => 4, 'row' => 0, 'label' => 'GK'],
                ['id' => 1, 'role' => 'Defender', 'col' => 1, 'row' => 3, 'label' => 'LB'],
                ['id' => 2, 'role' => 'Defender', 'col' => 3, 'row' => 3, 'label' => 'CB'],
                ['id' => 3, 'role' => 'Defender', 'col' => 5, 'row' => 3, 'label' => 'CB'],
                ['id' => 4, 'role' => 'Defender', 'col' => 7, 'row' => 3, 'label' => 'RB'],
                ['id' => 5, 'role' => 'Midfielder', 'col' => 4, 'row' => 5, 'label' => 'DM'],
                ['id' => 6, 'role' => 'Midfielder', 'col' => 1, 'row' => 8, 'label' => 'LM'],
                ['id' => 7, 'role' => 'Midfielder', 'col' => 3, 'row' => 8, 'label' => 'CM'],
                ['id' => 8, 'role' => 'Midfielder', 'col' => 5, 'row' => 8, 'label' => 'CM'],
                ['id' => 9, 'role' => 'Midfielder', 'col' => 7, 'row' => 8, 'label' => 'RM'],
                ['id' => 10, 'role' => 'Forward', 'col' => 4, 'row' => 11, 'label' => 'CF'],
            ],
            self::F_5_3_2 => [
                ['id' => 0, 'role' => 'Goalkeeper', 'col' => 4, 'row' => 0, 'label' => 'GK'],
                ['id' => 1, 'role' => 'Defender', 'col' => 0, 'row' => 3, 'label' => 'LB'],
                ['id' => 2, 'role' => 'Defender', 'col' => 2, 'row' => 3, 'label' => 'CB'],
                ['id' => 3, 'role' => 'Defender', 'col' => 4, 'row' => 3, 'label' => 'CB'],
                ['id' => 4, 'role' => 'Defender', 'col' => 6, 'row' => 3, 'label' => 'CB'],
                ['id' => 5, 'role' => 'Defender', 'col' => 8, 'row' => 3, 'label' => 'RB'],
                ['id' => 6, 'role' => 'Midfielder', 'col' => 2, 'row' => 7, 'label' => 'CM'],
                ['id' => 7, 'role' => 'Midfielder', 'col' => 4, 'row' => 7, 'label' => 'CM'],
                ['id' => 8, 'role' => 'Midfielder', 'col' => 6, 'row' => 7, 'label' => 'CM'],
                ['id' => 9, 'role' => 'Forward', 'col' => 3, 'row' => 11, 'label' => 'CF'],
                ['id' => 10, 'role' => 'Forward', 'col' => 5, 'row' => 11, 'label' => 'CF'],
            ],
            self::F_5_4_1 => [
                ['id' => 0, 'role' => 'Goalkeeper', 'col' => 4, 'row' => 0, 'label' => 'GK'],
                ['id' => 1, 'role' => 'Defender', 'col' => 0, 'row' => 3, 'label' => 'LB'],
                ['id' => 2, 'role' => 'Defender', 'col' => 2, 'row' => 3, 'label' => 'CB'],
                ['id' => 3, 'role' => 'Defender', 'col' => 4, 'row' => 3, 'label' => 'CB'],
                ['id' => 4, 'role' => 'Defender', 'col' => 6, 'row' => 3, 'label' => 'CB'],
                ['id' => 5, 'role' => 'Defender', 'col' => 8, 'row' => 3, 'label' => 'RB'],
                ['id' => 6, 'role' => 'Midfielder', 'col' => 1, 'row' => 7, 'label' => 'LM'],
                ['id' => 7, 'role' => 'Midfielder', 'col' => 3, 'row' => 7, 'label' => 'CM'],
                ['id' => 8, 'role' => 'Midfielder', 'col' => 5, 'row' => 7, 'label' => 'CM'],
                ['id' => 9, 'role' => 'Midfielder', 'col' => 7, 'row' => 7, 'label' => 'RM'],
                ['id' => 10, 'role' => 'Forward', 'col' => 4, 'row' => 11, 'label' => 'CF'],
            ],
            self::F_4_1_2_3 => [
                ['id' => 0, 'role' => 'Goalkeeper', 'col' => 4, 'row' => 0, 'label' => 'GK'],
                ['id' => 1, 'role' => 'Defender', 'col' => 1, 'row' => 3, 'label' => 'LB'],
                ['id' => 2, 'role' => 'Defender', 'col' => 3, 'row' => 3, 'label' => 'CB'],
                ['id' => 3, 'role' => 'Defender', 'col' => 5, 'row' => 3, 'label' => 'CB'],
                ['id' => 4, 'role' => 'Defender', 'col' => 7, 'row' => 3, 'label' => 'RB'],
                ['id' => 5, 'role' => 'Midfielder', 'col' => 4, 'row' => 5, 'label' => 'DM'],
                ['id' => 6, 'role' => 'Midfielder', 'col' => 3, 'row' => 8, 'label' => 'CM'],
                ['id' => 7, 'role' => 'Midfielder', 'col' => 5, 'row' => 8, 'label' => 'CM'],
                ['id' => 8, 'role' => 'Forward', 'col' => 1, 'row' => 10, 'label' => 'LW'],
                ['id' => 9, 'role' => 'Forward', 'col' => 4, 'row' => 11, 'label' => 'CF'],
                ['id' => 10, 'role' => 'Forward', 'col' => 7, 'row' => 10, 'label' => 'RW'],
            ],
            self::F_4_3_2_1 => [
                ['id' => 0, 'role' => 'Goalkeeper', 'col' => 4, 'row' => 0, 'label' => 'GK'],
                ['id' => 1, 'role' => 'Defender', 'col' => 1, 'row' => 3, 'label' => 'LB'],
                ['id' => 2, 'role' => 'Defender', 'col' => 3, 'row' => 3, 'label' => 'CB'],
                ['id' => 3, 'role' => 'Defender', 'col' => 5, 'row' => 3, 'label' => 'CB'],
                ['id' => 4, 'role' => 'Defender', 'col' => 7, 'row' => 3, 'label' => 'RB'],
                ['id' => 5, 'role' => 'Midfielder', 'col' => 2, 'row' => 6, 'label' => 'CM'],
                ['id' => 6, 'role' => 'Midfielder', 'col' => 4, 'row' => 6, 'label' => 'CM'],
                ['id' => 7, 'role' => 'Midfielder', 'col' => 6, 'row' => 6, 'label' => 'CM'],
                ['id' => 8, 'role' => 'Midfielder', 'col' => 3, 'row' => 9, 'label' => 'AM'],
                ['id' => 9, 'role' => 'Midfielder', 'col' => 5, 'row' => 9, 'label' => 'AM'],
                ['id' => 10, 'role' => 'Forward', 'col' => 4, 'row' => 12, 'label' => 'CF'],
            ],
        };
    }
}
