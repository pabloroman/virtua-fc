<?php

namespace Database\Factories;

use App\Models\Competition;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class GameMatchFactory extends Factory
{
    protected $model = GameMatch::class;

    public function definition(): array
    {
        return [
            'game_id' => Game::factory(),
            'competition_id' => Competition::factory(),
            'round_number' => 1,
            'round_name' => 'Matchday 1',
            'home_team_id' => Team::factory(),
            'away_team_id' => Team::factory(),
            'scheduled_date' => Carbon::parse('2024-08-15'),
            'home_score' => null,
            'away_score' => null,
            'played' => false,
            'cup_tie_id' => null,
        ];
    }

    public function forGame(Game $game): static
    {
        return $this->state(fn (array $attributes) => [
            'game_id' => $game->id,
        ]);
    }

    public function forCompetition(Competition $competition): static
    {
        return $this->state(fn (array $attributes) => [
            'competition_id' => $competition->id,
        ]);
    }

    public function inRound(int $roundNumber): static
    {
        return $this->state(fn (array $attributes) => [
            'round_number' => $roundNumber,
            'round_name' => "Matchday {$roundNumber}",
        ]);
    }

    public function scheduledOn(string|Carbon $date): static
    {
        return $this->state(fn (array $attributes) => [
            'scheduled_date' => $date instanceof Carbon ? $date : Carbon::parse($date),
        ]);
    }

    public function between(Team $homeTeam, Team $awayTeam): static
    {
        return $this->state(fn (array $attributes) => [
            'home_team_id' => $homeTeam->id,
            'away_team_id' => $awayTeam->id,
        ]);
    }

    public function played(int $homeScore = 0, int $awayScore = 0): static
    {
        return $this->state(fn (array $attributes) => [
            'home_score' => $homeScore,
            'away_score' => $awayScore,
            'played' => true,
        ]);
    }

    public function cupMatch(?CupTie $cupTie = null): static
    {
        return $this->state(fn (array $attributes) => [
            'cup_tie_id' => $cupTie?->id,
        ]);
    }

    public function withExtraTime(int $homeScoreEt = 0, int $awayScoreEt = 0): static
    {
        return $this->state(fn (array $attributes) => [
            'is_extra_time' => true,
            'home_score_et' => $homeScoreEt,
            'away_score_et' => $awayScoreEt,
        ]);
    }

    public function withPenalties(int $homePens = 0, int $awayPens = 0): static
    {
        return $this->state(fn (array $attributes) => [
            'home_score_penalties' => $homePens,
            'away_score_penalties' => $awayPens,
        ]);
    }
}
