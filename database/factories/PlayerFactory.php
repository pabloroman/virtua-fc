<?php

namespace Database\Factories;

use App\Models\Player;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PlayerFactory extends Factory
{
    protected $model = Player::class;

    public function definition(): array
    {
        return [
            'id' => Str::uuid()->toString(),
            'name' => $this->faker->name(),
            'date_of_birth' => $this->faker->dateTimeBetween('-35 years', '-18 years'),
            'nationality' => ['ESP'],
            'technical_ability' => $this->faker->numberBetween(40, 90),
            'physical_ability' => $this->faker->numberBetween(40, 90),
        ];
    }

    public function age(int $age): static
    {
        return $this->state(fn (array $attributes) => [
            'date_of_birth' => now()->subYears($age)->subMonths(6),
        ]);
    }
}
