<?php

namespace Database\Factories;

use App\Models\Game;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class GameFactory extends Factory
{
    protected $model = Game::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'team_id' => Team::factory(),
            'season' => '2024',
            'player_name' => $this->faker->name(),
            'current_date' => '2024-08-15',
            'current_matchday' => 1,
            'cup_round' => 0,
            'cup_eliminated' => false,
        ];
    }

    public function forTeam(Team $team): static
    {
        return $this->state(fn (array $attributes) => [
            'team_id' => $team->id,
        ]);
    }

    public function atMatchday(int $matchday): static
    {
        return $this->state(fn (array $attributes) => [
            'current_matchday' => $matchday,
        ]);
    }

    public function atDate(string $date): static
    {
        return $this->state(fn (array $attributes) => [
            'current_date' => $date,
        ]);
    }

    public function inCupRound(int $round): static
    {
        return $this->state(fn (array $attributes) => [
            'cup_round' => $round,
        ]);
    }

    public function eliminatedFromCup(): static
    {
        return $this->state(fn (array $attributes) => [
            'cup_eliminated' => true,
        ]);
    }
}
