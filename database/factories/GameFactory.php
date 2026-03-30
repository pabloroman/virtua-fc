<?php

namespace Database\Factories;

use App\Models\Competition;
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
            'competition_id' => Competition::factory()->league(),
            'season' => '2025',
            'player_name' => $this->faker->name(),
            'current_date' => '2024-08-15',
        ];
    }

    public function forTeam(Team $team): static
    {
        return $this->state(fn (array $attributes) => [
            'team_id' => $team->id,
        ]);
    }

    public function inCompetition(string $competitionId): static
    {
        return $this->state(fn (array $attributes) => [
            'competition_id' => $competitionId,
        ]);
    }

    public function atDate(string $date): static
    {
        return $this->state(fn (array $attributes) => [
            'current_date' => $date,
        ]);
    }

}
