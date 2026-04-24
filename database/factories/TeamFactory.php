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
        $name = $this->faker->unique()->city() . ' FC';
        $transfermarktId = $this->faker->unique()->numberBetween(1000, 99999);

        return [
            'id' => Str::uuid()->toString(),
            'transfermarkt_id' => $transfermarktId,
            'name' => $name,
            // Append the unique transfermarkt_id so slugs never collide even
            // when faker's city pool produces near-duplicates (e.g. "St. Louis"
            // vs "St Louis" both slug to "st-louis") across large test setups.
            'slug' => Str::slug($name) . '-' . $transfermarktId,
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
