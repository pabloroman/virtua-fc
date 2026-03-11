<?php

namespace Tests\Unit;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Modules\Player\Services\PlayerTierService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerTierServiceTest extends TestCase
{
    use RefreshDatabase;

    private PlayerTierService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PlayerTierService();
    }

    // -------------------------------------------------------
    // tierFromMarketValue — pure computation
    // -------------------------------------------------------

    public function test_tier_5_for_world_class_market_values(): void
    {
        $this->assertEquals(5, PlayerTierService::tierFromMarketValue(50_000_000_00));  // €50M exactly
        $this->assertEquals(5, PlayerTierService::tierFromMarketValue(200_000_000_00)); // €200M
        $this->assertEquals(5, PlayerTierService::tierFromMarketValue(75_000_000_00));  // €75M
    }

    public function test_tier_4_for_excellent_market_values(): void
    {
        $this->assertEquals(4, PlayerTierService::tierFromMarketValue(49_999_999_99)); // Just below €50M
        $this->assertEquals(4, PlayerTierService::tierFromMarketValue(20_000_000_00)); // €20M exactly
        $this->assertEquals(4, PlayerTierService::tierFromMarketValue(35_000_000_00)); // €35M
    }

    public function test_tier_3_for_good_market_values(): void
    {
        $this->assertEquals(3, PlayerTierService::tierFromMarketValue(19_999_999_99)); // Just below €20M
        $this->assertEquals(3, PlayerTierService::tierFromMarketValue(5_000_000_00));  // €5M exactly
        $this->assertEquals(3, PlayerTierService::tierFromMarketValue(10_000_000_00)); // €10M
    }

    public function test_tier_2_for_average_market_values(): void
    {
        $this->assertEquals(2, PlayerTierService::tierFromMarketValue(4_999_999_99)); // Just below €5M
        $this->assertEquals(2, PlayerTierService::tierFromMarketValue(1_000_000_00)); // €1M exactly
        $this->assertEquals(2, PlayerTierService::tierFromMarketValue(2_500_000_00)); // €2.5M
    }

    public function test_tier_1_for_developing_market_values(): void
    {
        $this->assertEquals(1, PlayerTierService::tierFromMarketValue(999_999_99));  // Just below €1M
        $this->assertEquals(1, PlayerTierService::tierFromMarketValue(0));           // Free / tournament
        $this->assertEquals(1, PlayerTierService::tierFromMarketValue(500_000_00));  // €500K
        $this->assertEquals(1, PlayerTierService::tierFromMarketValue(100_00));      // €100
    }

    // -------------------------------------------------------
    // recomputeTiers — batch SQL
    // -------------------------------------------------------

    public function test_recompute_tiers_updates_players_correctly(): void
    {
        $game = Game::factory()->create();
        $team = Team::factory()->create();

        $worldClass = GamePlayer::factory()->forGame($game)->forTeam($team)->create([
            'market_value_cents' => 80_000_000_00, // €80M → tier 5
            'tier' => 1, // Wrong tier intentionally
        ]);

        $average = GamePlayer::factory()->forGame($game)->forTeam($team)->create([
            'market_value_cents' => 2_000_000_00, // €2M → tier 2
            'tier' => 5, // Wrong tier intentionally
        ]);

        $this->service->recomputeTiers([$worldClass->id, $average->id]);

        $this->assertEquals(5, $worldClass->fresh()->tier);
        $this->assertEquals(2, $average->fresh()->tier);
    }

    public function test_recompute_tiers_only_affects_specified_ids(): void
    {
        $game = Game::factory()->create();
        $team = Team::factory()->create();

        $target = GamePlayer::factory()->forGame($game)->forTeam($team)->create([
            'market_value_cents' => 60_000_000_00, // €60M → tier 5
            'tier' => 1,
        ]);

        $untouched = GamePlayer::factory()->forGame($game)->forTeam($team)->create([
            'market_value_cents' => 60_000_000_00, // €60M → should be tier 5 but stays 1
            'tier' => 1,
        ]);

        $this->service->recomputeTiers([$target->id]);

        $this->assertEquals(5, $target->fresh()->tier);
        $this->assertEquals(1, $untouched->fresh()->tier); // Not updated
    }

    public function test_recompute_tiers_handles_empty_array(): void
    {
        // Should not throw or execute any queries
        $this->service->recomputeTiers([]);
        $this->assertTrue(true);
    }

    // -------------------------------------------------------
    // recomputeAllTiersForGame — game-scoped
    // -------------------------------------------------------

    public function test_recompute_all_tiers_for_game_updates_all_players_in_game(): void
    {
        $game1 = Game::factory()->create();
        $game2 = Game::factory()->create();
        $team = Team::factory()->create();

        $player1 = GamePlayer::factory()->forGame($game1)->forTeam($team)->create([
            'market_value_cents' => 30_000_000_00, // €30M → tier 4
            'tier' => 1,
        ]);

        $player2 = GamePlayer::factory()->forGame($game1)->forTeam($team)->create([
            'market_value_cents' => 500_000_00, // €500K → tier 1
            'tier' => 5,
        ]);

        $otherGamePlayer = GamePlayer::factory()->forGame($game2)->forTeam($team)->create([
            'market_value_cents' => 30_000_000_00, // €30M → should be tier 4 but stays 1
            'tier' => 1,
        ]);

        $this->service->recomputeAllTiersForGame($game1->id);

        $this->assertEquals(4, $player1->fresh()->tier);
        $this->assertEquals(1, $player2->fresh()->tier);
        $this->assertEquals(1, $otherGamePlayer->fresh()->tier); // Other game untouched
    }

    // -------------------------------------------------------
    // Factory produces correct tier
    // -------------------------------------------------------

    public function test_factory_produces_tier_consistent_with_market_value(): void
    {
        $game = Game::factory()->create();
        $team = Team::factory()->create();

        $player = GamePlayer::factory()->forGame($game)->forTeam($team)->create();

        $expectedTier = PlayerTierService::tierFromMarketValue($player->market_value_cents);
        $this->assertEquals($expectedTier, $player->tier);
    }
}
