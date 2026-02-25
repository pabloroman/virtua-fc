<?php

namespace Database\Factories;

use App\Models\Player;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PlayerFactory extends Factory
{
    protected $model = Player::class;

    public function definition(): array
    {
        return [
            'id' => Str::uuid()->toString(),
            'transfermarkt_id' => 'gen-' . Str::uuid()->toString(),
            'name' => $this->faker->name(),
            'date_of_birth' => $this->faker->dateTimeBetween('-35 years', '-18 years'),
            'nationality' => ['ESP'],
            'technical_ability' => $this->faker->numberBetween(40, 90),
            'physical_ability' => $this->faker->numberBetween(40, 90),
        ];
    }

    public function age(int $age, ?string $referenceDate = null): static
    {
        $reference = Carbon::parse($referenceDate ?? '2024-08-15');

        return $this->state(fn (array $attributes) => [
            'date_of_birth' => $reference->copy()->subYears($age)->subMonths(6),
        ]);
    }
}
