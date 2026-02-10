<?php

namespace Database\Factories;

use App\Models\Competition;
use Illuminate\Database\Eloquent\Factories\Factory;

class CompetitionFactory extends Factory
{
    protected $model = Competition::class;

    public function definition(): array
    {
        return [
            'id' => strtoupper($this->faker->unique()->lexify('???')),
            'name' => $this->faker->words(2, true) . ' League',
            'country' => 'ES',
            'tier' => 1,
            'type' => 'league',
            'role' => Competition::ROLE_PRIMARY,
            'handler_type' => 'league',
            'season' => '2024',
        ];
    }

    public function league(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'league',
            'role' => Competition::ROLE_PRIMARY,
            'handler_type' => 'league',
        ]);
    }

    public function knockoutCup(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'cup',
            'role' => Competition::ROLE_DOMESTIC_CUP,
            'handler_type' => 'knockout_cup',
        ]);
    }

    public function groupStageCup(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'cup',
            'role' => Competition::ROLE_EUROPEAN,
            'handler_type' => 'group_stage_cup',
        ]);
    }
}
