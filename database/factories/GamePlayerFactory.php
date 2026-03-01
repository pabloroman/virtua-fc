<?php

namespace Database\Factories;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Player;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class GamePlayerFactory extends Factory
{
    protected $model = GamePlayer::class;

    public function definition(): array
    {
        return [
            'id' => Str::uuid()->toString(),
            'game_id' => Game::factory(),
            'player_id' => Player::factory(),
            'team_id' => Team::factory(),
            'position' => 'Central Midfield',
            'secondary_positions' => [],
            'market_value_cents' => $this->faker->numberBetween(100_000_00, 50_000_000_00),
            'contract_until' => now()->addYears(2),
            'annual_wage' => $this->faker->numberBetween(10_000_00, 1_000_000_00),
            'fitness' => $this->faker->numberBetween(70, 100),
            'morale' => $this->faker->numberBetween(60, 90),
            'durability' => $this->faker->numberBetween(50, 95),
            'game_technical_ability' => $this->faker->numberBetween(40, 90),
            'game_physical_ability' => $this->faker->numberBetween(40, 90),
            'potential' => $this->faker->numberBetween(60, 95),
            'potential_low' => $this->faker->numberBetween(55, 85),
            'potential_high' => $this->faker->numberBetween(70, 99),
            'season_appearances' => 0,
        ];
    }

    public function forGame(Game $game): static
    {
        return $this->state(fn (array $attributes) => [
            'game_id' => $game->id,
        ]);
    }

    public function forTeam(Team $team): static
    {
        return $this->state(fn (array $attributes) => [
            'team_id' => $team->id,
        ]);
    }

    public function goalkeeper(): static
    {
        return $this->state(fn (array $attributes) => [
            'position' => 'Goalkeeper',
        ]);
    }

    public function retiring(string $season): static
    {
        return $this->state(fn (array $attributes) => [
            'retiring_at_season' => $season,
        ]);
    }
}
