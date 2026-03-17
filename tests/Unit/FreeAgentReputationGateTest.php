<?php

namespace Tests\Unit;

use App\Models\ClubProfile;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Models\TeamReputation;
use App\Modules\Player\Services\PlayerTierService;
use App\Modules\Transfer\Services\ContractService;
use App\Modules\Transfer\Services\ScoutingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FreeAgentReputationGateTest extends TestCase
{
    use RefreshDatabase;

    private ScoutingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ScoutingService::class);
    }

    private function createGameWithTeamReputation(string $reputationLevel): array
    {
        $game = Game::factory()->create();
        $team = Team::factory()->create();

        TeamReputation::create([
            'game_id' => $game->id,
            'team_id' => $team->id,
            'reputation_level' => $reputationLevel,
            'base_reputation_level' => $reputationLevel,
            'reputation_points' => TeamReputation::pointsForTier($reputationLevel),
        ]);

        $game->update(['team_id' => $team->id]);

        return [$game, $team];
    }

    private function createFreeAgent(Game $game, int $tier, int $marketValueCents): GamePlayer
    {
        return GamePlayer::factory()->forGame($game)->create([
            'team_id' => null,
            'tier' => $tier,
            'market_value_cents' => $marketValueCents,
        ]);
    }

    // -------------------------------------------------------
    // Tier 5 (World Class, €50M+) — requires continental+
    // -------------------------------------------------------

    public function test_tier5_free_agent_willing_to_join_elite_team(): void
    {
        [$game, $team] = $this->createGameWithTeamReputation(ClubProfile::REPUTATION_ELITE);
        $player = $this->createFreeAgent($game, 5, 80_000_000_00);

        $this->assertEquals('willing', $this->service->getFreeAgentWillingnessLevel($player, $game->id, $team->id));
        $this->assertTrue($this->service->canSignFreeAgent($player, $game->id, $team->id));
    }

    public function test_tier5_free_agent_willing_to_join_continental_team(): void
    {
        [$game, $team] = $this->createGameWithTeamReputation(ClubProfile::REPUTATION_CONTINENTAL);
        $player = $this->createFreeAgent($game, 5, 80_000_000_00);

        $this->assertEquals('willing', $this->service->getFreeAgentWillingnessLevel($player, $game->id, $team->id));
        $this->assertTrue($this->service->canSignFreeAgent($player, $game->id, $team->id));
    }

    public function test_tier5_free_agent_reluctant_with_established_team(): void
    {
        [$game, $team] = $this->createGameWithTeamReputation(ClubProfile::REPUTATION_ESTABLISHED);
        $player = $this->createFreeAgent($game, 5, 80_000_000_00);

        $this->assertEquals('reluctant', $this->service->getFreeAgentWillingnessLevel($player, $game->id, $team->id));
        $this->assertFalse($this->service->canSignFreeAgent($player, $game->id, $team->id));
    }

    public function test_tier5_free_agent_unwilling_with_local_team(): void
    {
        [$game, $team] = $this->createGameWithTeamReputation(ClubProfile::REPUTATION_LOCAL);
        $player = $this->createFreeAgent($game, 5, 80_000_000_00);

        $this->assertEquals('unwilling', $this->service->getFreeAgentWillingnessLevel($player, $game->id, $team->id));
        $this->assertFalse($this->service->canSignFreeAgent($player, $game->id, $team->id));
    }

    // -------------------------------------------------------
    // Tier 4 (Excellent, €20M+) — requires established+
    // -------------------------------------------------------

    public function test_tier4_free_agent_willing_to_join_established_team(): void
    {
        [$game, $team] = $this->createGameWithTeamReputation(ClubProfile::REPUTATION_ESTABLISHED);
        $player = $this->createFreeAgent($game, 4, 30_000_000_00);

        $this->assertEquals('willing', $this->service->getFreeAgentWillingnessLevel($player, $game->id, $team->id));
        $this->assertTrue($this->service->canSignFreeAgent($player, $game->id, $team->id));
    }

    public function test_tier4_free_agent_reluctant_with_modest_team(): void
    {
        [$game, $team] = $this->createGameWithTeamReputation(ClubProfile::REPUTATION_MODEST);
        $player = $this->createFreeAgent($game, 4, 30_000_000_00);

        $this->assertEquals('reluctant', $this->service->getFreeAgentWillingnessLevel($player, $game->id, $team->id));
        $this->assertFalse($this->service->canSignFreeAgent($player, $game->id, $team->id));
    }

    // -------------------------------------------------------
    // Tier 3 (Good, €5M+) — requires modest+
    // -------------------------------------------------------

    public function test_tier3_free_agent_willing_to_join_modest_team(): void
    {
        [$game, $team] = $this->createGameWithTeamReputation(ClubProfile::REPUTATION_MODEST);
        $player = $this->createFreeAgent($game, 3, 10_000_000_00);

        $this->assertEquals('willing', $this->service->getFreeAgentWillingnessLevel($player, $game->id, $team->id));
        $this->assertTrue($this->service->canSignFreeAgent($player, $game->id, $team->id));
    }

    public function test_tier3_free_agent_reluctant_with_local_team(): void
    {
        [$game, $team] = $this->createGameWithTeamReputation(ClubProfile::REPUTATION_LOCAL);
        $player = $this->createFreeAgent($game, 3, 10_000_000_00);

        $this->assertEquals('reluctant', $this->service->getFreeAgentWillingnessLevel($player, $game->id, $team->id));
        $this->assertFalse($this->service->canSignFreeAgent($player, $game->id, $team->id));
    }

    // -------------------------------------------------------
    // Tier 1-2 (Developing/Average) — any team
    // -------------------------------------------------------

    public function test_tier2_free_agent_willing_to_join_any_team(): void
    {
        [$game, $team] = $this->createGameWithTeamReputation(ClubProfile::REPUTATION_LOCAL);
        $player = $this->createFreeAgent($game, 2, 2_000_000_00);

        $this->assertEquals('willing', $this->service->getFreeAgentWillingnessLevel($player, $game->id, $team->id));
        $this->assertTrue($this->service->canSignFreeAgent($player, $game->id, $team->id));
    }

    public function test_tier1_free_agent_willing_to_join_any_team(): void
    {
        [$game, $team] = $this->createGameWithTeamReputation(ClubProfile::REPUTATION_LOCAL);
        $player = $this->createFreeAgent($game, 1, 500_000_00);

        $this->assertEquals('willing', $this->service->getFreeAgentWillingnessLevel($player, $game->id, $team->id));
        $this->assertTrue($this->service->canSignFreeAgent($player, $game->id, $team->id));
    }

    // -------------------------------------------------------
    // Edge: higher reputation always works
    // -------------------------------------------------------

    public function test_elite_team_can_sign_any_tier_free_agent(): void
    {
        [$game, $team] = $this->createGameWithTeamReputation(ClubProfile::REPUTATION_ELITE);

        foreach ([1, 2, 3, 4, 5] as $tier) {
            $player = $this->createFreeAgent($game, $tier, $tier * 10_000_000_00);
            $this->assertTrue(
                $this->service->canSignFreeAgent($player, $game->id, $team->id),
                "Elite team should be able to sign tier {$tier} free agent"
            );
        }
    }

    // -------------------------------------------------------
    // Edge: uses tier from model, falls back to market value
    // -------------------------------------------------------

    public function test_uses_market_value_fallback_when_tier_is_null(): void
    {
        [$game, $team] = $this->createGameWithTeamReputation(ClubProfile::REPUTATION_LOCAL);
        $player = $this->createFreeAgent($game, 1, 80_000_000_00);

        // Override tier to null to test fallback
        $player->tier = null;

        // €80M → tier 5 via fallback → requires continental+ → local team = unwilling
        $this->assertEquals('unwilling', $this->service->getFreeAgentWillingnessLevel($player, $game->id, $team->id));
    }
}
