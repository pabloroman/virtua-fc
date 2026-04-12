<?php

namespace Database\Factories;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\GamePlayerMatchState;
use App\Models\Player;
use App\Models\Team;
use App\Modules\Player\Services\PlayerTierService;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class GamePlayerFactory extends Factory
{
    protected $model = GamePlayer::class;

    /**
     * Match-state overrides stashed by newModel() so configure() can apply
     * them after the GamePlayer row is persisted. Keyed by spl_object_id of
     * the GamePlayer instance — unique per call even when createMany() is
     * looping. Static so the value survives the newModel → afterCreating
     * trip across stateful and unstateful Eloquent factory wrappers.
     *
     * @var array<int, array<string, mixed>>
     */
    protected static array $pendingOverrides = [];

    public function definition(): array
    {
        $marketValueCents = $this->faker->numberBetween(100_000_00, 50_000_000_00);

        return [
            'id' => Str::uuid()->toString(),
            'game_id' => Game::factory(),
            'player_id' => Player::factory(),
            'team_id' => Team::factory(),
            'position' => 'Central Midfield',
            'secondary_positions' => [],
            'market_value_cents' => $marketValueCents,
            'contract_until' => now()->addYears(2),
            'annual_wage' => $this->faker->numberBetween(10_000_00, 1_000_000_00),
            'durability' => $this->faker->numberBetween(50, 95),
            'game_technical_ability' => $this->faker->numberBetween(40, 90),
            'game_physical_ability' => $this->faker->numberBetween(40, 90),
            'potential' => $this->faker->numberBetween(60, 95),
            'potential_low' => $this->faker->numberBetween(55, 85),
            'potential_high' => $this->faker->numberBetween(70, 99),
            'tier' => PlayerTierService::tierFromMarketValue($marketValueCents),
        ];
    }

    /**
     * Tests still pass match-state attributes via `->create(['fitness' =>
     * 50, 'goals' => 5])`. Strip them out of the GamePlayer attribute set
     * and stash them so afterCreating() can apply them to the satellite row.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function newModel(array $attributes = []): GamePlayer
    {
        $matchStateColumns = array_keys(GamePlayerMatchState::DEFAULTS);
        $overrides = array_intersect_key($attributes, array_flip($matchStateColumns));

        if (! empty($overrides)) {
            $attributes = array_diff_key($attributes, $overrides);
        }

        /** @var GamePlayer $model */
        $model = parent::newModel($attributes);

        if (! empty($overrides)) {
            self::$pendingOverrides[spl_object_id($model)] = $overrides;
        }

        return $model;
    }

    public function configure(): static
    {
        return $this->afterCreating(function (GamePlayer $player) {
            $oid = spl_object_id($player);
            $overrides = self::$pendingOverrides[$oid] ?? [];
            unset(self::$pendingOverrides[$oid]);

            // Every test-created GamePlayer gets a satellite row by default
            // — tests historically assumed game_players carried fitness etc.,
            // and the cheapest fix is to mirror that assumption.
            GamePlayerMatchState::create(array_merge(
                ['game_player_id' => $player->id],
                GamePlayerMatchState::DEFAULTS,
                [
                    'fitness' => $this->faker->numberBetween(70, 100),
                    'morale' => $this->faker->numberBetween(60, 90),
                ],
                $overrides,
            ));

            $player->unsetRelation('matchState');
        });
    }

    /**
     * Create a "pool" player — no satellite row, mirroring foreign-league
     * players that exist purely for the transfer market.
     */
    public function pool(): static
    {
        return $this->afterCreating(function (GamePlayer $player) {
            GamePlayerMatchState::where('game_player_id', $player->id)->delete();
            $player->unsetRelation('matchState');
        });
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
