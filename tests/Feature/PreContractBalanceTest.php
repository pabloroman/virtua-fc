<?php

namespace Tests\Feature;

use App\Models\ClubProfile;
use App\Models\Competition;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Player;
use App\Models\Team;
use App\Models\User;
use App\Modules\Transfer\Services\ScoutingService;
use App\Modules\Transfer\Services\TransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PreContractBalanceTest extends TestCase
{
    use RefreshDatabase;

    private ScoutingService $scoutingService;
    private TransferService $transferService;
    private Competition $competition;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scoutingService = app(ScoutingService::class);
        $this->transferService = app(TransferService::class);

        $this->competition = Competition::factory()->league()->create([
            'id' => 'ESP1',
            'name' => 'LaLiga',
        ]);
    }

    // =========================================
    // REPUTATION MODIFIER TESTS
    // =========================================

    public function test_reputation_modifier_returns_1_for_equal_reputation(): void
    {
        [$game, $player] = $this->createGameAndPlayer(
            offeringReputation: ClubProfile::REPUTATION_ESTABLISHED,
            sourceReputation: ClubProfile::REPUTATION_ESTABLISHED,
        );

        $modifier = $this->scoutingService->calculateReputationModifier($game->team, $player);

        $this->assertEquals(1.0, $modifier);
    }

    public function test_reputation_modifier_returns_1_when_moving_up(): void
    {
        [$game, $player] = $this->createGameAndPlayer(
            offeringReputation: ClubProfile::REPUTATION_ELITE,
            sourceReputation: ClubProfile::REPUTATION_MODEST,
        );

        $modifier = $this->scoutingService->calculateReputationModifier($game->team, $player);

        $this->assertEquals(1.0, $modifier);
    }

    public function test_reputation_modifier_returns_correct_values_per_gap(): void
    {
        $expectedModifiers = [
            1 => 0.75,
            2 => 0.45,
            3 => 0.20,
            4 => 0.08,
        ];

        // Reputation tiers ordered: local(0), modest(1), established(2), continental(3), elite(4)
        $tiers = [
            ClubProfile::REPUTATION_LOCAL,
            ClubProfile::REPUTATION_MODEST,
            ClubProfile::REPUTATION_ESTABLISHED,
            ClubProfile::REPUTATION_CONTINENTAL,
            ClubProfile::REPUTATION_ELITE,
        ];

        foreach ($expectedModifiers as $gap => $expectedModifier) {
            // Use elite(4) as source, and offering = 4 - gap
            $offeringIndex = 4 - $gap;
            if ($offeringIndex < 0) {
                continue;
            }

            [$game, $player] = $this->createGameAndPlayer(
                offeringReputation: $tiers[$offeringIndex],
                sourceReputation: ClubProfile::REPUTATION_ELITE,
            );

            $modifier = $this->scoutingService->calculateReputationModifier($game->team, $player);

            $this->assertEquals(
                $expectedModifier,
                $modifier,
                "Gap {$gap}: expected {$expectedModifier}, got {$modifier}"
            );
        }
    }

    public function test_reputation_modifier_returns_1_for_free_agents(): void
    {
        $user = User::factory()->create();
        $userTeam = Team::factory()->create();
        ClubProfile::create(['team_id' => $userTeam->id, 'reputation_level' => ClubProfile::REPUTATION_MODEST]);

        $game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $userTeam->id,
            'competition_id' => $this->competition->id,
        ]);

        $player = GamePlayer::factory()->create([
            'game_id' => $game->id,
            'team_id' => null, // Free agent
        ]);

        $modifier = $this->scoutingService->calculateReputationModifier($game->team, $player);

        $this->assertEquals(1.0, $modifier);
    }

    // =========================================
    // FREE AGENT WAGE PREMIUM TESTS
    // =========================================

    public function test_pre_contract_wage_demand_applies_premium_for_high_value_player(): void
    {
        $user = User::factory()->create();
        $sourceTeam = Team::factory()->create();
        $userTeam = Team::factory()->create();

        $game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $userTeam->id,
            'competition_id' => $this->competition->id,
        ]);

        // €50M player → 1.45x premium
        $player = GamePlayer::factory()->create([
            'game_id' => $game->id,
            'team_id' => $sourceTeam->id,
            'market_value_cents' => 5_000_000_000,
        ]);

        // Run multiple times to account for wage variance and check average ratio
        $ratios = [];
        for ($i = 0; $i < 20; $i++) {
            $baseWage = $this->scoutingService->calculateWageDemand($player);
            $premiumWage = $this->scoutingService->calculatePreContractWageDemand($player);
            if ($baseWage > 0) {
                $ratios[] = $premiumWage / $baseWage;
            }
        }

        $avgRatio = array_sum($ratios) / count($ratios);

        // Average ratio should be close to 1.45 (±0.15 for rounding + variance)
        $this->assertGreaterThan(1.20, $avgRatio, "Premium ratio should be significantly above 1.0, got {$avgRatio}");
        $this->assertLessThan(1.70, $avgRatio, "Premium ratio should not exceed 1.70, got {$avgRatio}");
    }

    public function test_pre_contract_wage_demand_applies_premium_for_low_value_player(): void
    {
        $user = User::factory()->create();
        $sourceTeam = Team::factory()->create();
        $userTeam = Team::factory()->create();

        $game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $userTeam->id,
            'competition_id' => $this->competition->id,
        ]);

        // €1M player → 1.20x premium
        $player = GamePlayer::factory()->create([
            'game_id' => $game->id,
            'team_id' => $sourceTeam->id,
            'market_value_cents' => 100_000_000,
        ]);

        $baseWage = $this->scoutingService->calculateWageDemand($player);
        $premiumWage = $this->scoutingService->calculatePreContractWageDemand($player);

        $this->assertGreaterThanOrEqual($baseWage, $premiumWage);
    }

    // =========================================
    // PRE-CONTRACT OFFER EVALUATION TESTS
    // =========================================

    public function test_evaluate_pre_contract_offer_with_large_reputation_gap_mostly_rejects(): void
    {
        [$game, $player] = $this->createGameAndPlayer(
            offeringReputation: ClubProfile::REPUTATION_MODEST,
            sourceReputation: ClubProfile::REPUTATION_ELITE,
            marketValueCents: 5_000_000_000,
        );

        $premiumWage = $this->scoutingService->calculatePreContractWageDemand($player);

        // Run 100 evaluations — with gap 4 (elite → modest), modifier is 0.08
        // 85% × 0.08 = 6.8% → should rarely accept
        $acceptedCount = 0;
        for ($i = 0; $i < 100; $i++) {
            $result = $this->scoutingService->evaluatePreContractOffer($player, $premiumWage, $game->team);
            if ($result['accepted']) {
                $acceptedCount++;
            }
        }

        // With 6.8% chance, expect roughly 0-15 accepts out of 100
        $this->assertLessThan(25, $acceptedCount, "Expected mostly rejections for large reputation gap, got {$acceptedCount}/100 accepts");
    }

    public function test_evaluate_pre_contract_offer_with_no_reputation_gap_mostly_accepts(): void
    {
        [$game, $player] = $this->createGameAndPlayer(
            offeringReputation: ClubProfile::REPUTATION_CONTINENTAL,
            sourceReputation: ClubProfile::REPUTATION_CONTINENTAL,
            marketValueCents: 1_000_000_000,
        );

        // Offer well above any possible demand to guarantee hitting the 85% base chance
        // (wage demand has ±10% internal variance, so a single calculation may not cover subsequent rolls)
        $generousOffer = (int) ($this->scoutingService->calculatePreContractWageDemand($player) * 1.5);

        // With no gap, modifier is 1.0 → 85% chance
        $acceptedCount = 0;
        for ($i = 0; $i < 100; $i++) {
            $result = $this->scoutingService->evaluatePreContractOffer($player, $generousOffer, $game->team);
            if ($result['accepted']) {
                $acceptedCount++;
            }
        }

        $this->assertGreaterThan(50, $acceptedCount, "Expected mostly accepts with same reputation, got {$acceptedCount}/100 accepts");
    }

    public function test_evaluate_pre_contract_offer_rejects_below_85_percent_of_premium_demand(): void
    {
        [$game, $player] = $this->createGameAndPlayer(
            offeringReputation: ClubProfile::REPUTATION_CONTINENTAL,
            sourceReputation: ClubProfile::REPUTATION_CONTINENTAL,
            marketValueCents: 1_000_000_000,
        );

        $premiumWage = $this->scoutingService->calculatePreContractWageDemand($player);
        $lowOffer = (int) ($premiumWage * 0.50); // Way below 85% threshold (even with ±10% wage variance between calls)

        $result = $this->scoutingService->evaluatePreContractOffer($player, $lowOffer, $game->team);
        $this->assertFalse($result['accepted']);
    }

    // =========================================
    // BID EVALUATION TESTS (PRICE ONLY — REPUTATION GATE MOVED TO PERSONAL TERMS)
    // =========================================

    public function test_evaluate_bid_with_large_reputation_gap_evaluates_on_price(): void
    {
        [$game, $player] = $this->createGameAndPlayer(
            offeringReputation: ClubProfile::REPUTATION_LOCAL,
            sourceReputation: ClubProfile::REPUTATION_ELITE,
            marketValueCents: 5_000_000_000,
        );

        // Low bid — should be rejected on price, not reputation
        $askingPrice = $this->scoutingService->calculateAskingPrice($player);
        $bidAmount = (int) ($askingPrice * 0.5);

        $result = $this->scoutingService->evaluateBid($player, $bidAmount, $game);
        $this->assertEquals('rejected', $result['result']);
        $this->assertArrayNotHasKey('reason', $result, 'Club bid evaluation should not have a reputation reason');
    }

    public function test_evaluate_bid_above_asking_price_always_accepts(): void
    {
        [$game, $player] = $this->createGameAndPlayer(
            offeringReputation: ClubProfile::REPUTATION_LOCAL,
            sourceReputation: ClubProfile::REPUTATION_ELITE,
            marketValueCents: 5_000_000_000,
        );

        // Offer above asking price — reputation gate should NOT apply
        $askingPrice = $this->scoutingService->calculateAskingPrice($player);
        $bidAmount = (int) ($askingPrice * 1.5);

        for ($i = 0; $i < 50; $i++) {
            $result = $this->scoutingService->evaluateBid($player, $bidAmount, $game);
            $this->assertEquals('accepted', $result['result'], 'Bid above asking price should always be accepted regardless of reputation gap');
        }
    }

    public function test_evaluate_bid_with_no_reputation_gap_proceeds_normally(): void
    {
        [$game, $player] = $this->createGameAndPlayer(
            offeringReputation: ClubProfile::REPUTATION_ESTABLISHED,
            sourceReputation: ClubProfile::REPUTATION_ESTABLISHED,
            marketValueCents: 500_000_000,
        );

        // Offer well above asking price
        $bidAmount = $player->market_value_cents * 3;

        $acceptedCount = 0;
        for ($i = 0; $i < 50; $i++) {
            $result = $this->scoutingService->evaluateBid($player, $bidAmount, $game);
            if ($result['result'] === 'accepted') {
                $acceptedCount++;
            }
        }

        // Same reputation → no gate → should always accept with high bid
        $this->assertEquals(50, $acceptedCount, "Expected all bids accepted with same reputation, got {$acceptedCount}/50");
    }

    // =========================================
    // AI OFFER CHANCE SCALING TESTS
    // =========================================

    public function test_pre_contract_offer_chance_scales_with_market_value(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'competition_id' => $this->competition->id,
        ]);

        // €50M+ player → 35% chance
        $highValue = GamePlayer::factory()->create([
            'game_id' => $game->id,
            'team_id' => $team->id,
            'market_value_cents' => 6_000_000_000,
        ]);
        $this->assertEquals(0.35, $this->transferService->getPreContractOfferChance($highValue));

        // €20M player → 25% chance
        $midValue = GamePlayer::factory()->create([
            'game_id' => $game->id,
            'team_id' => $team->id,
            'market_value_cents' => 2_000_000_000,
        ]);
        $this->assertEquals(0.25, $this->transferService->getPreContractOfferChance($midValue));

        // €10M player → 20% chance
        $midLow = GamePlayer::factory()->create([
            'game_id' => $game->id,
            'team_id' => $team->id,
            'market_value_cents' => 1_000_000_000,
        ]);
        $this->assertEquals(0.20, $this->transferService->getPreContractOfferChance($midLow));

        // €5M player → 15% chance
        $low = GamePlayer::factory()->create([
            'game_id' => $game->id,
            'team_id' => $team->id,
            'market_value_cents' => 500_000_000,
        ]);
        $this->assertEquals(0.15, $this->transferService->getPreContractOfferChance($low));

        // €2M player → 10% chance
        $veryLow = GamePlayer::factory()->create([
            'game_id' => $game->id,
            'team_id' => $team->id,
            'market_value_cents' => 200_000_000,
        ]);
        $this->assertEquals(0.10, $this->transferService->getPreContractOfferChance($veryLow));
    }

    // =========================================
    // REPUTATION INDEX TESTS
    // =========================================

    public function test_reputation_index_returns_correct_values(): void
    {
        $this->assertEquals(0, ClubProfile::getReputationTierIndex(ClubProfile::REPUTATION_LOCAL));
        $this->assertEquals(1, ClubProfile::getReputationTierIndex(ClubProfile::REPUTATION_MODEST));
        $this->assertEquals(2, ClubProfile::getReputationTierIndex(ClubProfile::REPUTATION_ESTABLISHED));
        $this->assertEquals(3, ClubProfile::getReputationTierIndex(ClubProfile::REPUTATION_CONTINENTAL));
        $this->assertEquals(4, ClubProfile::getReputationTierIndex(ClubProfile::REPUTATION_ELITE));
    }

    public function test_reputation_index_defaults_to_0_for_unknown(): void
    {
        $this->assertEquals(0, ClubProfile::getReputationTierIndex('unknown_reputation'));
    }

    // =========================================
    // SCOUTING DETAIL RETURNS PRE-CONTRACT WAGE
    // =========================================

    public function test_scouting_detail_includes_pre_contract_wage_for_expiring_players(): void
    {
        $user = User::factory()->create();
        $sourceTeam = Team::factory()->create();
        $userTeam = Team::factory()->create();

        $game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $userTeam->id,
            'competition_id' => $this->competition->id,
            'current_date' => '2025-01-15',
            'season' => '2024',
        ]);

        // Create GameInvestment and GameFinances for the game
        \App\Models\GameInvestment::create([
            'game_id' => $game->id,
            'season' => '2024',
            'transfer_budget' => 50_000_000_00,
            'scouting_tier' => 2,
        ]);

        \App\Models\GameFinances::create([
            'game_id' => $game->id,
            'season' => '2024',
            'projected_revenue' => 100_000_000_00,
            'projected_wages' => 50_000_000_00,
        ]);

        // Player with expiring contract (ends June 2025, season is 2024)
        $player = GamePlayer::factory()->create([
            'game_id' => $game->id,
            'team_id' => $sourceTeam->id,
            'market_value_cents' => 2_000_000_000, // €20M
            'contract_until' => '2025-06-30',
        ]);

        $detail = $this->scoutingService->getPlayerScoutingDetail($player, $game);

        $this->assertArrayHasKey('pre_contract_wage_demand', $detail);
        $this->assertNotNull($detail['pre_contract_wage_demand']);
        $this->assertGreaterThan($detail['wage_demand'], $detail['pre_contract_wage_demand']);
    }

    public function test_scouting_detail_returns_null_pre_contract_wage_for_non_expiring_players(): void
    {
        $user = User::factory()->create();
        $sourceTeam = Team::factory()->create();
        $userTeam = Team::factory()->create();

        $game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $userTeam->id,
            'competition_id' => $this->competition->id,
            'current_date' => '2025-01-15',
            'season' => '2024',
        ]);

        \App\Models\GameInvestment::create([
            'game_id' => $game->id,
            'season' => '2024',
            'transfer_budget' => 50_000_000_00,
            'scouting_tier' => 2,
        ]);

        \App\Models\GameFinances::create([
            'game_id' => $game->id,
            'season' => '2024',
            'projected_revenue' => 100_000_000_00,
            'projected_wages' => 50_000_000_00,
        ]);

        // Player with long contract
        $player = GamePlayer::factory()->create([
            'game_id' => $game->id,
            'team_id' => $sourceTeam->id,
            'market_value_cents' => 2_000_000_000,
            'contract_until' => '2027-06-30',
        ]);

        $detail = $this->scoutingService->getPlayerScoutingDetail($player, $game);

        $this->assertArrayHasKey('pre_contract_wage_demand', $detail);
        $this->assertNull($detail['pre_contract_wage_demand']);
    }

    // =========================================
    // HELPERS
    // =========================================

    /**
     * Create a game and player with specific reputation levels for testing.
     */
    private function createGameAndPlayer(
        string $offeringReputation,
        string $sourceReputation,
        int $marketValueCents = 1_000_000_000,
    ): array {
        $user = User::factory()->create();
        $userTeam = Team::factory()->create();
        $sourceTeam = Team::factory()->create();

        ClubProfile::create(['team_id' => $userTeam->id, 'reputation_level' => $offeringReputation]);
        ClubProfile::create(['team_id' => $sourceTeam->id, 'reputation_level' => $sourceReputation]);

        $userTeam->competitions()->attach($this->competition->id, ['season' => '2024']);
        $sourceTeam->competitions()->attach($this->competition->id, ['season' => '2024']);

        $game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $userTeam->id,
            'competition_id' => $this->competition->id,
        ]);

        $player = GamePlayer::factory()->create([
            'game_id' => $game->id,
            'team_id' => $sourceTeam->id,
            'market_value_cents' => $marketValueCents,
            'contract_until' => '2025-06-30',
        ]);

        // Load the team relationship with clubProfile
        $player->load('team.clubProfile');
        $game->load('team.clubProfile');

        return [$game, $player];
    }
}
