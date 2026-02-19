<?php

namespace Tests\Feature;

use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Season\Processors\PlayerRetirementProcessor;
use App\Modules\Squad\Services\PlayerRetirementService;
use App\Models\Competition;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Player;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerRetirementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Team $userTeam;
    private Team $aiTeam;
    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->userTeam = Team::factory()->create(['name' => 'User Team']);
        $this->aiTeam = Team::factory()->create(['name' => 'AI Team']);
        Competition::factory()->league()->create(['id' => 'ESP1']);

        $this->game = Game::factory()->create([
            'user_id' => $this->user->id,
            'team_id' => $this->userTeam->id,
            'competition_id' => 'ESP1',
            'season' => '2024',
        ]);
    }

    // =========================================
    // PlayerRetirementService: shouldRetire
    // =========================================

    public function test_young_player_never_retires(): void
    {
        $service = app(PlayerRetirementService::class);
        $player = $this->createGamePlayer(age: 25, position: 'Central Midfield');

        // Run 100 times — should never retire at age 25
        for ($i = 0; $i < 100; $i++) {
            $this->assertFalse($service->shouldRetire($player));
        }
    }

    public function test_outfield_player_below_min_age_never_retires(): void
    {
        $service = app(PlayerRetirementService::class);
        $player = $this->createGamePlayer(age: 32, position: 'Centre-Forward');

        for ($i = 0; $i < 100; $i++) {
            $this->assertFalse($service->shouldRetire($player));
        }
    }

    public function test_goalkeeper_below_min_age_never_retires(): void
    {
        $service = app(PlayerRetirementService::class);
        $player = $this->createGamePlayer(age: 34, position: 'Goalkeeper');

        for ($i = 0; $i < 100; $i++) {
            $this->assertFalse($service->shouldRetire($player));
        }
    }

    public function test_outfield_player_at_max_age_always_retires(): void
    {
        $service = app(PlayerRetirementService::class);
        $player = $this->createGamePlayer(age: 40, position: 'Centre-Back');

        $this->assertTrue($service->shouldRetire($player));
    }

    public function test_goalkeeper_at_max_age_always_retires(): void
    {
        $service = app(PlayerRetirementService::class);
        $player = $this->createGamePlayer(age: 42, position: 'Goalkeeper');

        $this->assertTrue($service->shouldRetire($player));
    }

    public function test_retirement_probability_increases_with_age(): void
    {
        $service = app(PlayerRetirementService::class);
        $retirementCounts = [];

        foreach ([33, 35, 37, 39] as $age) {
            $count = 0;
            for ($i = 0; $i < 500; $i++) {
                $player = $this->createGamePlayer(
                    age: $age,
                    position: 'Central Midfield',
                    fitness: 75,
                    appearances: 10,
                    techAbility: 65,
                    physAbility: 65,
                );
                if ($service->shouldRetire($player)) {
                    $count++;
                }
            }
            $retirementCounts[$age] = $count;
        }

        // Each age group should have more retirements than the previous
        $this->assertGreaterThan($retirementCounts[33], $retirementCounts[35]);
        $this->assertGreaterThan($retirementCounts[35], $retirementCounts[37]);
        $this->assertGreaterThan($retirementCounts[37], $retirementCounts[39]);
    }

    public function test_fit_starters_retire_less_than_unfit_bench_players(): void
    {
        $service = app(PlayerRetirementService::class);

        $fitStarterRetirements = 0;
        $unfitBenchRetirements = 0;
        $iterations = 500;

        for ($i = 0; $i < $iterations; $i++) {
            $fitStarter = $this->createGamePlayer(
                age: 36,
                position: 'Central Midfield',
                fitness: 90,
                appearances: 30,
                techAbility: 80,
                physAbility: 75,
            );
            if ($service->shouldRetire($fitStarter)) {
                $fitStarterRetirements++;
            }

            $unfitBench = $this->createGamePlayer(
                age: 36,
                position: 'Central Midfield',
                fitness: 50,
                appearances: 2,
                techAbility: 45,
                physAbility: 40,
            );
            if ($service->shouldRetire($unfitBench)) {
                $unfitBenchRetirements++;
            }
        }

        $this->assertGreaterThan($fitStarterRetirements, $unfitBenchRetirements);
    }

    // =========================================
    // PlayerRetirementProcessor
    // =========================================

    public function test_processor_retires_announced_players(): void
    {
        $player = $this->createGamePlayer(
            age: 36,
            position: 'Central Midfield',
            team: $this->userTeam,
        );
        $player->update(['retiring_at_season' => '2024']);

        $processor = app(PlayerRetirementProcessor::class);
        $data = new SeasonTransitionData(oldSeason: '2024', newSeason: '2025', competitionId: 'ESP1');

        $result = $processor->process($this->game, $data);

        // Player should be deleted
        $this->assertDatabaseMissing('game_players', ['id' => $player->id]);

        // Metadata should contain retired player info
        $retired = $result->getMetadata('retiredPlayers');
        $this->assertCount(1, $retired);
        $this->assertEquals($player->id, $retired[0]['playerId']);
        $this->assertTrue($retired[0]['wasUserTeam']);
    }

    public function test_processor_generates_replacement_for_ai_team(): void
    {
        $player = $this->createGamePlayer(
            age: 37,
            position: 'Centre-Back',
            team: $this->aiTeam,
            techAbility: 75,
            physAbility: 70,
        );
        $player->update(['retiring_at_season' => '2024']);

        $aiTeamPlayerCount = GamePlayer::where('team_id', $this->aiTeam->id)->count();
        $this->assertEquals(1, $aiTeamPlayerCount);

        $processor = app(PlayerRetirementProcessor::class);
        $data = new SeasonTransitionData(oldSeason: '2024', newSeason: '2025', competitionId: 'ESP1');

        $result = $processor->process($this->game, $data);

        // Original player should be deleted
        $this->assertDatabaseMissing('game_players', ['id' => $player->id]);

        // A replacement should exist for the AI team
        $replacement = GamePlayer::where('team_id', $this->aiTeam->id)
            ->where('game_id', $this->game->id)
            ->first();

        $this->assertNotNull($replacement);
        $this->assertEquals('Centre-Back', $replacement->position);
        $this->assertLessThanOrEqual(28, $replacement->player->age);
        $this->assertGreaterThanOrEqual(22, $replacement->player->age);

        // Metadata should contain replacement info
        $retired = $result->getMetadata('retiredPlayers');
        $this->assertNotNull($retired[0]['replacement']);
        $this->assertEquals('Centre-Back', $retired[0]['replacement']['position']);
    }

    public function test_processor_does_not_generate_replacement_for_user_team(): void
    {
        $player = $this->createGamePlayer(
            age: 36,
            position: 'Left-Back',
            team: $this->userTeam,
        );
        $player->update(['retiring_at_season' => '2024']);

        $processor = app(PlayerRetirementProcessor::class);
        $data = new SeasonTransitionData(oldSeason: '2024', newSeason: '2025', competitionId: 'ESP1');

        $result = $processor->process($this->game, $data);

        // No replacement for user team
        $userPlayers = GamePlayer::where('team_id', $this->userTeam->id)
            ->where('game_id', $this->game->id)
            ->count();
        $this->assertEquals(0, $userPlayers);

        $retired = $result->getMetadata('retiredPlayers');
        $this->assertNull($retired[0]['replacement']);
    }

    public function test_processor_does_not_retire_players_from_different_season(): void
    {
        $player = $this->createGamePlayer(
            age: 36,
            position: 'Central Midfield',
            team: $this->userTeam,
        );
        $player->update(['retiring_at_season' => '2025']); // Announced for next season

        $processor = app(PlayerRetirementProcessor::class);
        $data = new SeasonTransitionData(oldSeason: '2024', newSeason: '2025', competitionId: 'ESP1');

        $processor->process($this->game, $data);

        // Player should still exist (retiring next season, not this one)
        $this->assertDatabaseHas('game_players', ['id' => $player->id]);
    }

    // =========================================
    // GamePlayer model: isRetiring
    // =========================================

    public function test_is_retiring_returns_true_when_set(): void
    {
        $player = $this->createGamePlayer(age: 36, position: 'Central Midfield');
        $player->update(['retiring_at_season' => '2025']);
        $player->refresh();

        $this->assertTrue($player->isRetiring());
    }

    public function test_is_retiring_returns_false_when_null(): void
    {
        $player = $this->createGamePlayer(age: 25, position: 'Central Midfield');

        $this->assertFalse($player->isRetiring());
    }

    // =========================================
    // Contract guards
    // =========================================

    public function test_retiring_player_cannot_be_offered_renewal(): void
    {
        $player = $this->createGamePlayer(
            age: 36,
            position: 'Central Midfield',
            team: $this->userTeam,
        );
        // Set contract to expire this season
        $player->update([
            'contract_until' => now()->subMonth(),
            'retiring_at_season' => '2025',
        ]);
        $player->refresh();

        $this->assertFalse($player->canBeOfferedRenewal());
    }

    public function test_retiring_player_cannot_receive_pre_contract_offers(): void
    {
        $player = $this->createGamePlayer(
            age: 36,
            position: 'Central Midfield',
            team: $this->userTeam,
        );
        $player->update([
            'contract_until' => now()->subMonth(),
            'retiring_at_season' => '2025',
        ]);
        $player->refresh();

        $this->assertFalse($player->canReceivePreContractOffers());
    }

    // =========================================
    // Replacement player generation
    // =========================================

    public function test_replacement_player_has_correct_attributes(): void
    {
        $service = app(PlayerRetirementService::class);
        $retiringPlayer = $this->createGamePlayer(
            age: 37,
            position: 'Goalkeeper',
            team: $this->aiTeam,
            techAbility: 80,
            physAbility: 75,
        );

        $replacement = $service->generateReplacementPlayer($this->game, $retiringPlayer, '2025');

        $this->assertEquals('Goalkeeper', $replacement->position);
        $this->assertEquals($this->aiTeam->id, $replacement->team_id);
        $this->assertEquals($this->game->id, $replacement->game_id);
        $this->assertNotNull($replacement->player);
        $this->assertGreaterThanOrEqual(22, $replacement->player->age);
        $this->assertLessThanOrEqual(28, $replacement->player->age);
        $this->assertNotNull($replacement->contract_until);
        $this->assertNotNull($replacement->annual_wage);
        $this->assertGreaterThan(0, $replacement->game_technical_ability);
        $this->assertGreaterThan(0, $replacement->game_physical_ability);
    }

    // =========================================
    // Helpers
    // =========================================

    private function createGamePlayer(
        int $age,
        string $position,
        ?Team $team = null,
        int $fitness = 80,
        int $appearances = 15,
        int $techAbility = 65,
        int $physAbility = 65,
    ): GamePlayer {
        $player = Player::factory()->age($age)->create([
            'technical_ability' => $techAbility,
            'physical_ability' => $physAbility,
        ]);

        return GamePlayer::factory()->create([
            'game_id' => $this->game->id,
            'player_id' => $player->id,
            'team_id' => ($team ?? $this->userTeam)->id,
            'position' => $position,
            'fitness' => $fitness,
            'season_appearances' => $appearances,
            'game_technical_ability' => $techAbility,
            'game_physical_ability' => $physAbility,
        ]);
    }
}
