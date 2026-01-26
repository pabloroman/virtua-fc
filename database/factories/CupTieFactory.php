<?php

namespace Database\Factories;

use App\Models\Competition;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

class CupTieFactory extends Factory
{
    protected $model = CupTie::class;

    public function definition(): array
    {
        return [
            'game_id' => Game::factory(),
            'competition_id' => Competition::factory()->knockoutCup(),
            'round_number' => 1,
            'home_team_id' => Team::factory(),
            'away_team_id' => Team::factory(),
            'first_leg_match_id' => null,
            'second_leg_match_id' => null,
            'winner_id' => null,
            'completed' => false,
            'resolution' => null,
        ];
    }

    public function forGame(Game $game): static
    {
        return $this->state(fn (array $attributes) => [
            'game_id' => $game->id,
        ]);
    }

    public function inRound(int $roundNumber): static
    {
        return $this->state(fn (array $attributes) => [
            'round_number' => $roundNumber,
        ]);
    }

    public function between(Team $homeTeam, Team $awayTeam): static
    {
        return $this->state(fn (array $attributes) => [
            'home_team_id' => $homeTeam->id,
            'away_team_id' => $awayTeam->id,
        ]);
    }

    public function completed(Team $winner, string $resolutionType = 'normal'): static
    {
        return $this->state(fn (array $attributes) => [
            'winner_id' => $winner->id,
            'completed' => true,
            'resolution' => ['type' => $resolutionType],
        ]);
    }
}
