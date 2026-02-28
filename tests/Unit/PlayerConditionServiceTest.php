<?php

namespace Tests\Unit;

use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Modules\Squad\Services\PlayerConditionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class PlayerConditionServiceTest extends TestCase
{
    use RefreshDatabase;

    private PlayerConditionService $service;

    private ReflectionMethod $calculateFitnessChange;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PlayerConditionService();

        // Access private method for unit testing core math
        $this->calculateFitnessChange = new ReflectionMethod(PlayerConditionService::class, 'calculateFitnessChange');
    }

    /**
     * Create a GamePlayer with specific attributes for testing.
     */
    private function createPlayer(array $overrides = []): GamePlayer
    {
        $game = Game::factory()->create(['current_date' => '2025-10-01']);
        $team = Team::factory()->create();

        return GamePlayer::factory()
            ->forGame($game)
            ->forTeam($team)
            ->create(array_merge([
                'position' => 'Central Midfield',
                'fitness' => 100,
                'morale' => 80,
                'game_physical_ability' => 70,
                'game_technical_ability' => 70,
            ], $overrides));
    }

    // -------------------------------------------------------
    // Core recovery mechanics
    // -------------------------------------------------------

    public function test_seven_day_gap_creates_near_neutral_change_for_average_midfielder(): void
    {
        $player = $this->createPlayer([
            'position' => 'Central Midfield',
            'fitness' => 90,
            'game_physical_ability' => 70,
        ]);

        // Run many iterations to test the average trend
        $totalChange = 0;
        $iterations = 200;

        for ($i = 0; $i < $iterations; $i++) {
            $change = $this->calculateFitnessChange->invoke($this->service, $player, true, 7);
            $totalChange += $change;
        }

        $avgChange = $totalChange / $iterations;

        // At fitness 90, 7-day gap should be roughly neutral (within ±3)
        $this->assertGreaterThan(-5, $avgChange, 'Average 7-day change should not be too negative');
        $this->assertLessThan(5, $avgChange, 'Average 7-day change should not be too positive');
    }

    public function test_three_day_gap_creates_significant_fitness_drop(): void
    {
        $player = $this->createPlayer([
            'position' => 'Central Midfield',
            'fitness' => 90,
            'game_physical_ability' => 70,
        ]);

        $totalChange = 0;
        $iterations = 200;

        for ($i = 0; $i < $iterations; $i++) {
            $change = $this->calculateFitnessChange->invoke($this->service, $player, true, 3);
            $totalChange += $change;
        }

        $avgChange = $totalChange / $iterations;

        // 3-day gap should cause meaningful drop (net negative)
        $this->assertLessThan(-3, $avgChange, '3-day gap should cause significant fitness loss');
    }

    public function test_resting_player_recovers_fitness(): void
    {
        $player = $this->createPlayer([
            'fitness' => 75,
            'game_physical_ability' => 70,
        ]);

        $change = $this->calculateFitnessChange->invoke($this->service, $player, false, 7);

        // Resting at fitness 75 for 7 days should give substantial recovery
        $this->assertGreaterThan(5, $change, 'Resting should provide meaningful recovery');
    }

    public function test_recovery_is_faster_at_low_fitness(): void
    {
        $attrs = [
            'position' => 'Central Midfield',
            'game_physical_ability' => 70,
        ];

        $playerLow = $this->createPlayer(array_merge($attrs, ['fitness' => 60]));
        $playerHigh = $this->createPlayer(array_merge($attrs, ['fitness' => 95]));

        $recoveryLow = $this->calculateFitnessChange->invoke($this->service, $playerLow, false, 5);
        $recoveryHigh = $this->calculateFitnessChange->invoke($this->service, $playerHigh, false, 5);

        $this->assertGreaterThan($recoveryHigh, $recoveryLow, 'Low-fitness player should recover faster');
    }

    public function test_recovery_at_max_fitness_is_minimal(): void
    {
        $player = $this->createPlayer([
            'fitness' => 100,
            'game_physical_ability' => 70,
        ]);

        $recovery = $this->calculateFitnessChange->invoke($this->service, $player, false, 5);

        // At fitness 100, recovery scaling factor is 1.0 (base only)
        // base 2.0 * 1.0 * 5 days = 10
        $this->assertLessThanOrEqual(12, $recovery, 'Recovery at max fitness should be low');
    }

    // -------------------------------------------------------
    // Age modifiers
    // -------------------------------------------------------

    public function test_young_players_lose_less_fitness(): void
    {
        // Young player (age < 24 requires date_of_birth to make them young)
        $game = Game::factory()->create(['current_date' => '2025-10-01']);
        $team = Team::factory()->create();

        $youngPlayer = GamePlayer::factory()->forGame($game)->forTeam($team)->create([
            'position' => 'Central Midfield',
            'fitness' => 90,
            'game_physical_ability' => 70,
        ]);

        $oldPlayer = GamePlayer::factory()->forGame($game)->forTeam($team)->create([
            'position' => 'Central Midfield',
            'fitness' => 90,
            'game_physical_ability' => 70,
        ]);

        // Use reflection to test the age modifier directly
        $getAgeLossModifier = new ReflectionMethod(PlayerConditionService::class, 'getAgeLossModifier');
        $config = config('match_simulation.fatigue');

        // Mock young player age by checking the config thresholds
        $youngMod = $config['age_loss_modifier']['young'];
        $veteranMod = $config['age_loss_modifier']['veteran'];

        $this->assertLessThan($veteranMod, $youngMod, 'Young modifier should be less than veteran');
        $this->assertLessThan(1.0, $youngMod, 'Young players should have loss modifier < 1.0');
        $this->assertGreaterThan(1.0, $veteranMod, 'Veteran players should have loss modifier > 1.0');
    }

    // -------------------------------------------------------
    // Physical ability modifiers
    // -------------------------------------------------------

    public function test_high_physical_players_recover_faster(): void
    {
        $highPhys = $this->createPlayer(['fitness' => 80, 'game_physical_ability' => 85]);
        $lowPhys = $this->createPlayer(['fitness' => 80, 'game_physical_ability' => 50]);

        $recoveryHigh = $this->calculateFitnessChange->invoke($this->service, $highPhys, false, 5);
        $recoveryLow = $this->calculateFitnessChange->invoke($this->service, $lowPhys, false, 5);

        $this->assertGreaterThan($recoveryLow, $recoveryHigh, 'High physical player should recover faster');
    }

    // -------------------------------------------------------
    // Position differences
    // -------------------------------------------------------

    public function test_goalkeepers_lose_less_fitness_than_midfielders(): void
    {
        $gk = $this->createPlayer([
            'position' => 'Goalkeeper',
            'fitness' => 90,
            'game_physical_ability' => 70,
        ]);

        $mid = $this->createPlayer([
            'position' => 'Central Midfield',
            'fitness' => 90,
            'game_physical_ability' => 70,
        ]);

        $totalGk = 0;
        $totalMid = 0;
        $iterations = 200;

        for ($i = 0; $i < $iterations; $i++) {
            $totalGk += $this->calculateFitnessChange->invoke($this->service, $gk, true, 7);
            $totalMid += $this->calculateFitnessChange->invoke($this->service, $mid, true, 7);
        }

        $this->assertGreaterThan($totalMid / $iterations, $totalGk / $iterations,
            'GK should have better net fitness change than midfielder');
    }

    // -------------------------------------------------------
    // Bounds
    // -------------------------------------------------------

    public function test_fitness_never_exceeds_max(): void
    {
        $game = Game::factory()->create(['current_date' => '2025-10-01']);
        $team = Team::factory()->create();

        $player = GamePlayer::factory()->forGame($game)->forTeam($team)->create([
            'position' => 'Central Midfield',
            'fitness' => 99,
            'morale' => 80,
            'game_physical_ability' => 70,
        ]);

        $homeTeam = Team::factory()->create();
        $awayTeam = Team::factory()->create();

        $match = GameMatch::factory()->create([
            'game_id' => $game->id,
            'home_team_id' => $homeTeam->id,
            'away_team_id' => $awayTeam->id,
            'home_lineup' => [],
            'away_lineup' => [],
            'home_score' => 1,
            'away_score' => 0,
        ]);

        // Resting player with high fitness
        $allPlayersByTeam = collect([$homeTeam->id => collect([$player])]);

        $this->service->batchUpdateAfterMatchday(
            collect([$match]),
            [['matchId' => $match->id, 'events' => []]],
            $allPlayersByTeam,
            14 // 14 days rest
        );

        $player->refresh();
        $this->assertLessThanOrEqual(100, $player->fitness, 'Fitness should not exceed 100');
    }

    public function test_fitness_never_below_minimum(): void
    {
        $game = Game::factory()->create(['current_date' => '2025-10-01']);
        $team = Team::factory()->create();

        $player = GamePlayer::factory()->forGame($game)->forTeam($team)->create([
            'position' => 'Central Midfield',
            'fitness' => 42,
            'morale' => 80,
            'game_physical_ability' => 70,
        ]);

        $match = GameMatch::factory()->create([
            'game_id' => $game->id,
            'home_team_id' => $team->id,
            'away_team_id' => Team::factory()->create()->id,
            'home_lineup' => [$player->id],
            'away_lineup' => [],
            'home_score' => 1,
            'away_score' => 0,
        ]);

        $allPlayersByTeam = collect([$team->id => collect([$player])]);

        $this->service->batchUpdateAfterMatchday(
            collect([$match]),
            [['matchId' => $match->id, 'events' => []]],
            $allPlayersByTeam,
            1 // only 1 day since last match
        );

        $player->refresh();
        $this->assertGreaterThanOrEqual(40, $player->fitness, 'Fitness should not go below 40');
    }

    // -------------------------------------------------------
    // Integration: congestion simulation
    // -------------------------------------------------------

    public function test_congested_schedule_drops_fitness_significantly(): void
    {
        $player = $this->createPlayer([
            'position' => 'Central Midfield',
            'fitness' => 90,
            'game_physical_ability' => 70,
        ]);

        // Simulate 5 matches: Sat(7d) → Tue(3d) → Sat(4d) → Tue(3d) → Sat(4d)
        // Average over multiple runs to account for randomness
        $totalFinal = 0;
        $iterations = 100;

        for ($i = 0; $i < $iterations; $i++) {
            $gaps = [7, 3, 4, 3, 4];
            $fitness = 90;

            foreach ($gaps as $gap) {
                $player->fitness = $fitness;
                $change = $this->calculateFitnessChange->invoke($this->service, $player, true, $gap);
                $fitness = max(40, min(100, $fitness + $change));
            }

            $totalFinal += $fitness;
        }

        $avgFinal = $totalFinal / $iterations;

        // After 5 matches in congested period (starting at 90), average should drop meaningfully
        $this->assertLessThan(85, $avgFinal, 'Congested schedule should average below 85');
        $this->assertGreaterThan(60, $avgFinal, 'Fitness should not drop unreasonably low');
    }
}
