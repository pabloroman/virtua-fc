<?php

namespace Tests\Unit;

use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\Player;
use App\Models\Team;
use App\Modules\Player\Services\PlayerConditionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class PlayerConditionServiceTest extends TestCase
{
    use RefreshDatabase;

    private PlayerConditionService $service;

    private ReflectionMethod $calculateFitnessChange;

    private Carbon $currentDate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PlayerConditionService();
        $this->currentDate = Carbon::parse('2025-10-01');

        // Access private method for unit testing core math
        $this->calculateFitnessChange = new ReflectionMethod(PlayerConditionService::class, 'calculateFitnessChange');
    }

    /**
     * Create a GamePlayer with specific attributes for testing.
     */
    private function createPlayer(array $overrides = [], array $playerOverrides = []): GamePlayer
    {
        $game = Game::factory()->create(['current_date' => '2025-10-01']);
        $team = Team::factory()->create();

        $playerFactory = Player::factory();
        if (! empty($playerOverrides)) {
            $playerFactory = $playerFactory->state($playerOverrides);
        }

        return GamePlayer::factory()
            ->forGame($game)
            ->forTeam($team)
            ->create(array_merge([
                'player_id' => $playerFactory,
                'position' => 'Central Midfield',
                'fitness' => 100,
                'morale' => 80,
                'game_physical_ability' => 70,
                'game_technical_ability' => 70,
            ], $overrides));
    }

    // -------------------------------------------------------
    // Core recovery mechanics (unified energy model)
    // -------------------------------------------------------

    public function test_seven_day_gap_allows_full_recovery_from_match(): void
    {
        // Pin age to 27 (prime bracket, below the 28-year energy penalty threshold)
        // to eliminate variance from the factory's random date_of_birth.
        $player = $this->createPlayer([
            'position' => 'Central Midfield',
            'fitness' => 100,
            'game_physical_ability' => 70,
        ], [
            'date_of_birth' => Carbon::parse('2025-10-01')->subYears(27)->subMonths(6),
        ]);

        // With unified energy: match drains ~40% (100→60), recovery over 5 days (capped)
        // should bring back close to 100
        $change = $this->calculateFitnessChange->invoke($this->service, $player, true, 7, $this->currentDate);

        // At fitness 100, recovery should roughly offset the energy drain
        $this->assertGreaterThan(-10, $change, '7-day gap should nearly offset match drain');
    }

    public function test_three_day_gap_creates_significant_fitness_drop(): void
    {
        $player = $this->createPlayer([
            'position' => 'Central Midfield',
            'fitness' => 90,
            'game_physical_ability' => 70,
        ]);

        $change = $this->calculateFitnessChange->invoke($this->service, $player, true, 3, $this->currentDate);

        // 3-day gap: less recovery, still loses ~36 energy from match
        // Recovery at fitness 90: ~5.0 * (1 + 2*0.1) = 6/day * 3 = 18
        // Loss: ~36. Net: ~-18. Should be negative.
        $this->assertLessThan(0, $change, '3-day gap should cause meaningful fitness loss');
    }

    public function test_resting_player_recovers_fitness(): void
    {
        $player = $this->createPlayer([
            'fitness' => 60,
            'game_physical_ability' => 70,
        ]);

        $change = $this->calculateFitnessChange->invoke($this->service, $player, false, 7, $this->currentDate);

        // Resting at fitness 60 for 5 days (capped): rate = 5 * (1 + 2*0.4) = 9/day * 5 = 45
        $this->assertGreaterThan(20, $change, 'Resting should provide substantial recovery');
    }

    public function test_recovery_is_faster_at_low_fitness(): void
    {
        $attrs = [
            'position' => 'Central Midfield',
            'game_physical_ability' => 70,
        ];

        $playerLow = $this->createPlayer(array_merge($attrs, ['fitness' => 50]));
        $playerHigh = $this->createPlayer(array_merge($attrs, ['fitness' => 95]));

        $recoveryLow = $this->calculateFitnessChange->invoke($this->service, $playerLow, false, 5, $this->currentDate);
        $recoveryHigh = $this->calculateFitnessChange->invoke($this->service, $playerHigh, false, 5, $this->currentDate);

        $this->assertGreaterThan($recoveryHigh, $recoveryLow, 'Low-fitness player should recover faster');
    }

    public function test_recovery_at_max_fitness_is_minimal(): void
    {
        $player = $this->createPlayer([
            'fitness' => 100,
            'game_physical_ability' => 70,
        ]);

        $recovery = $this->calculateFitnessChange->invoke($this->service, $player, false, 5, $this->currentDate);

        // At fitness 100, recovery scaling = 1.0 (base only): 5.0 * 1.0 * 5 = 25
        $this->assertLessThanOrEqual(30, $recovery, 'Recovery at max fitness should be moderate');
    }

    // -------------------------------------------------------
    // Energy drain replaces position-based loss
    // -------------------------------------------------------

    public function test_goalkeepers_lose_less_energy_than_midfielders(): void
    {
        $gk = $this->createPlayer([
            'position' => 'Goalkeeper',
            'fitness' => 100,
            'game_physical_ability' => 70,
        ]);

        $mid = $this->createPlayer([
            'position' => 'Central Midfield',
            'fitness' => 100,
            'game_physical_ability' => 70,
        ]);

        // GK drain multiplier = 0.5x, so GK loses much less energy
        $gkChange = $this->calculateFitnessChange->invoke($this->service, $gk, true, 7, $this->currentDate);
        $midChange = $this->calculateFitnessChange->invoke($this->service, $mid, true, 7, $this->currentDate);

        $this->assertGreaterThan($midChange, $gkChange,
            'GK should have better net fitness change than midfielder');
    }

    public function test_high_physical_players_drain_less(): void
    {
        $highPhys = $this->createPlayer(['fitness' => 100, 'game_physical_ability' => 90]);
        $lowPhys = $this->createPlayer(['fitness' => 100, 'game_physical_ability' => 40]);

        // Same recovery period, different drain rates
        $changeHigh = $this->calculateFitnessChange->invoke($this->service, $highPhys, true, 7, $this->currentDate);
        $changeLow = $this->calculateFitnessChange->invoke($this->service, $lowPhys, true, 7, $this->currentDate);

        $this->assertGreaterThan($changeLow, $changeHigh,
            'High physical player should lose less energy per match');
    }

    // -------------------------------------------------------
    // Age modifiers
    // -------------------------------------------------------

    public function test_young_players_lose_less_fitness(): void
    {
        $config = config('player.condition');

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
        $highPhys = $this->createPlayer(['fitness' => 60, 'game_physical_ability' => 85]);
        $lowPhys = $this->createPlayer(['fitness' => 60, 'game_physical_ability' => 50]);

        $recoveryHigh = $this->calculateFitnessChange->invoke($this->service, $highPhys, false, 5, $this->currentDate);
        $recoveryLow = $this->calculateFitnessChange->invoke($this->service, $lowPhys, false, 5, $this->currentDate);

        $this->assertGreaterThan($recoveryLow, $recoveryHigh, 'High physical player should recover faster');
    }

    // -------------------------------------------------------
    // Proportional drain (unified energy model)
    // -------------------------------------------------------

    public function test_lower_starting_fitness_means_less_absolute_drain(): void
    {
        $highFit = $this->createPlayer(['fitness' => 100, 'game_physical_ability' => 70]);
        $lowFit = $this->createPlayer(['fitness' => 60, 'game_physical_ability' => 70]);

        // Same recovery period (1 day = minimal recovery, isolates the drain)
        $changeHigh = $this->calculateFitnessChange->invoke($this->service, $highFit, true, 1, $this->currentDate);
        $changeLow = $this->calculateFitnessChange->invoke($this->service, $lowFit, true, 1, $this->currentDate);

        // Higher fitness → more absolute drain (proportional)
        // changeHigh should be more negative than changeLow
        $this->assertLessThan($changeLow, $changeHigh,
            'Player at 100 fitness should lose more absolute energy than player at 60');
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
            [$homeTeam->id => 14, $awayTeam->id => 14],
            $this->currentDate,
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

        $awayTeamId = $match->away_team_id;
        $this->service->batchUpdateAfterMatchday(
            collect([$match]),
            [['matchId' => $match->id, 'events' => []]],
            $allPlayersByTeam,
            [$team->id => 1, $awayTeamId => 1],
            $this->currentDate,
        );

        $player->refresh();
        $this->assertGreaterThanOrEqual(40, $player->fitness, 'Fitness should not go below 40');
    }

    // -------------------------------------------------------
    // Integration: congestion simulation
    // -------------------------------------------------------

    public function test_congested_schedule_drops_fitness_significantly(): void
    {
        // Pin age to 27 (prime bracket) to eliminate age-modifier variance
        $player = $this->createPlayer([
            'position' => 'Central Midfield',
            'fitness' => 100,
            'game_physical_ability' => 70,
        ], [
            'date_of_birth' => Carbon::parse('2025-10-01')->subYears(27)->subMonths(6),
        ]);

        // Simulate 5 matches: Sat(7d) → Tue(3d) → Sat(4d) → Tue(3d) → Sat(4d)
        $gaps = [7, 3, 4, 3, 4];
        $fitness = 100;

        foreach ($gaps as $gap) {
            $player->fitness = $fitness;
            $change = $this->calculateFitnessChange->invoke($this->service, $player, true, $gap, $this->currentDate);
            $fitness = max(40, min(100, $fitness + $change));
        }

        // After 5 congested matches starting at 100, fitness should drop meaningfully
        // With unified energy model: each match drains ~40% of starting energy,
        // recovery partially compensates. Expected equilibrium around 70-85.
        $this->assertLessThan(90, $fitness, 'Congested schedule should drop fitness significantly');
        $this->assertGreaterThan(50, $fitness, 'Fitness should not drop unreasonably low');
    }

    public function test_weekly_schedule_maintains_high_fitness(): void
    {
        $player = $this->createPlayer([
            'position' => 'Central Midfield',
            'fitness' => 100,
            'game_physical_ability' => 70,
        ], [
            'date_of_birth' => Carbon::parse('2025-10-01')->subYears(27)->subMonths(6),
        ]);

        // Simulate 5 weekly matches (7 days between each)
        $fitness = 100;

        for ($i = 0; $i < 5; $i++) {
            $player->fitness = $fitness;
            $change = $this->calculateFitnessChange->invoke($this->service, $player, true, 7, $this->currentDate);
            $fitness = max(40, min(100, $fitness + $change));
        }

        // Weekly matches should allow near-full recovery (stabilize 90-100)
        $this->assertGreaterThan(85, $fitness, 'Weekly schedule should maintain high fitness');
    }
}
