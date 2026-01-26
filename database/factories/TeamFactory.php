<?php

namespace Database\Factories;

use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TeamFactory extends Factory
{
    protected $model = Team::class;

    public function definition(): array
    {
        return [
            'id' => Str::uuid()->toString(),
            'transfermarkt_id' => $this->faker->unique()->numberBetween(1000, 99999),
            'name' => $this->faker->city() . ' FC',
            'official_name' => $this->faker->company() . ' Football Club',
            'country' => 'ES',
            'image' => null,
            'stadium_name' => $this->faker->city() . ' Stadium',
            'stadium_seats' => $this->faker->numberBetween(10000, 80000),
        ];
    }

    public function withImage(): static
    {
        return $this->state(fn (array $attributes) => [
            'image' => 'https://tmssl.akamaized.net/images/wappen/big/' . $attributes['transfermarkt_id'] . '.png',
        ]);
    }
}
