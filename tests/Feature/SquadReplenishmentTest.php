<?php

namespace Tests\Feature;

use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Season\Processors\SquadReplenishmentProcessor;
use App\Models\Competition;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Player;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SquadReplenishmentTest extends TestCase
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

    public function test_processor_has_priority_8(): void
    {
        $processor = app(SquadReplenishmentProcessor::class);

        $this->assertEquals(8, $processor->priority());
    }

    public function test_ai_team_below_minimum_gets_replenished(): void
    {
        // Create an AI team with only 15 players (below 22 minimum)
        $this->createSquadForTeam($this->aiTeam, 15);

        $initialCount = GamePlayer::where('game_id', $this->game->id)
            ->where('team_id', $this->aiTeam->id)
            ->count();
        $this->assertEquals(15, $initialCount);

        $processor = app(SquadReplenishmentProcessor::class);
        $data = new SeasonTransitionData(oldSeason: '2024', newSeason: '2025', competitionId: 'ESP1');

        $result = $processor->process($this->game, $data);

        $finalCount = GamePlayer::where('game_id', $this->game->id)
            ->where('team_id', $this->aiTeam->id)
            ->count();
        $this->assertEquals(22, $finalCount);

        $generated = $result->getMetadata('squadReplenishment');
        $this->assertCount(7, $generated);
    }

    public function test_ai_team_at_minimum_is_not_touched(): void
    {
        // Create an AI team with exactly 22 players
        $this->createSquadForTeam($this->aiTeam, 22);

        $processor = app(SquadReplenishmentProcessor::class);
        $data = new SeasonTransitionData(oldSeason: '2024', newSeason: '2025', competitionId: 'ESP1');

        $result = $processor->process($this->game, $data);

        $finalCount = GamePlayer::where('game_id', $this->game->id)
            ->where('team_id', $this->aiTeam->id)
            ->count();
        $this->assertEquals(22, $finalCount);

        $generated = $result->getMetadata('squadReplenishment');
        $this->assertEmpty($generated);
    }

    public function test_ai_team_above_minimum_is_not_touched(): void
    {
        // Create an AI team with 25 players (above minimum)
        $this->createSquadForTeam($this->aiTeam, 25);

        $processor = app(SquadReplenishmentProcessor::class);
        $data = new SeasonTransitionData(oldSeason: '2024', newSeason: '2025', competitionId: 'ESP1');

        $result = $processor->process($this->game, $data);

        $finalCount = GamePlayer::where('game_id', $this->game->id)
            ->where('team_id', $this->aiTeam->id)
            ->count();
        $this->assertEquals(25, $finalCount);
    }

    public function test_user_team_is_never_replenished(): void
    {
        // Create user team with only 10 players (well below minimum)
        $this->createSquadForTeam($this->userTeam, 10);

        $processor = app(SquadReplenishmentProcessor::class);
        $data = new SeasonTransitionData(oldSeason: '2024', newSeason: '2025', competitionId: 'ESP1');

        $processor->process($this->game, $data);

        $finalCount = GamePlayer::where('game_id', $this->game->id)
            ->where('team_id', $this->userTeam->id)
            ->count();
        $this->assertEquals(10, $finalCount);
    }

    public function test_generated_players_fill_depleted_positions(): void
    {
        // Create a team with no goalkeepers and no forwards (only midfielders and defenders)
        $positions = [
            'Centre-Back', 'Centre-Back', 'Centre-Back',
            'Left-Back', 'Right-Back',
            'Central Midfield', 'Central Midfield',
            'Defensive Midfield',
            'Attacking Midfield',
            'Left Midfield',
        ];

        foreach ($positions as $position) {
            $this->createGamePlayer($this->aiTeam, $position);
        }

        $this->assertEquals(10, GamePlayer::where('game_id', $this->game->id)
            ->where('team_id', $this->aiTeam->id)
            ->count());

        $processor = app(SquadReplenishmentProcessor::class);
        $data = new SeasonTransitionData(oldSeason: '2024', newSeason: '2025', competitionId: 'ESP1');

        $processor->process($this->game, $data);

        // Should have filled to 22
        $finalCount = GamePlayer::where('game_id', $this->game->id)
            ->where('team_id', $this->aiTeam->id)
            ->count();
        $this->assertEquals(22, $finalCount);

        // Should have generated goalkeepers (was 0, target 2)
        $gkCount = GamePlayer::where('game_id', $this->game->id)
            ->where('team_id', $this->aiTeam->id)
            ->where('position', 'Goalkeeper')
            ->count();
        $this->assertGreaterThanOrEqual(2, $gkCount);

        // Should have generated forwards (was 0, target 4 across group)
        $forwardCount = GamePlayer::where('game_id', $this->game->id)
            ->where('team_id', $this->aiTeam->id)
            ->whereIn('position', ['Left Winger', 'Right Winger', 'Centre-Forward', 'Second Striker'])
            ->count();
        $this->assertGreaterThanOrEqual(3, $forwardCount);
    }

    public function test_generated_players_have_valid_attributes(): void
    {
        // Create a small squad so replenishment triggers
        $this->createSquadForTeam($this->aiTeam, 18);

        $processor = app(SquadReplenishmentProcessor::class);
        $data = new SeasonTransitionData(oldSeason: '2024', newSeason: '2025', competitionId: 'ESP1');

        $processor->process($this->game, $data);

        $newPlayers = GamePlayer::where('game_id', $this->game->id)
            ->where('team_id', $this->aiTeam->id)
            ->orderBy('created_at', 'desc')
            ->take(4)
            ->get();

        foreach ($newPlayers as $player) {
            $this->assertNotNull($player->player);
            $this->assertNotNull($player->contract_until);
            $this->assertNotNull($player->annual_wage);
            $this->assertGreaterThan(0, $player->game_technical_ability);
            $this->assertGreaterThan(0, $player->game_physical_ability);
            $this->assertGreaterThan(0, $player->market_value_cents);
            $this->assertNotNull($player->position);
        }
    }

    public function test_generated_players_scale_to_team_ability(): void
    {
        // Create a strong team (avg ability ~80)
        $strongTeam = Team::factory()->create(['name' => 'Strong AI Team']);
        for ($i = 0; $i < 18; $i++) {
            $this->createGamePlayer($strongTeam, 'Central Midfield', techAbility: 80, physAbility: 80);
        }

        // Create a weak team (avg ability ~45)
        $weakTeam = Team::factory()->create(['name' => 'Weak AI Team']);
        for ($i = 0; $i < 18; $i++) {
            $this->createGamePlayer($weakTeam, 'Central Midfield', techAbility: 45, physAbility: 45);
        }

        $processor = app(SquadReplenishmentProcessor::class);
        $data = new SeasonTransitionData(oldSeason: '2024', newSeason: '2025', competitionId: 'ESP1');

        $processor->process($this->game, $data);

        // Get newly generated players for each team
        $strongNewPlayers = GamePlayer::where('game_id', $this->game->id)
            ->where('team_id', $strongTeam->id)
            ->orderBy('created_at', 'desc')
            ->take(4)
            ->get();

        $weakNewPlayers = GamePlayer::where('game_id', $this->game->id)
            ->where('team_id', $weakTeam->id)
            ->orderBy('created_at', 'desc')
            ->take(4)
            ->get();

        $strongAvg = $strongNewPlayers->avg(fn ($p) => ($p->game_technical_ability + $p->game_physical_ability) / 2);
        $weakAvg = $weakNewPlayers->avg(fn ($p) => ($p->game_technical_ability + $p->game_physical_ability) / 2);

        $this->assertGreaterThan($weakAvg, $strongAvg);
    }

    public function test_multiple_ai_teams_are_replenished_independently(): void
    {
        $aiTeam2 = Team::factory()->create(['name' => 'AI Team 2']);

        // Team 1: 15 players, Team 2: 19 players
        $this->createSquadForTeam($this->aiTeam, 15);
        $this->createSquadForTeam($aiTeam2, 19);

        $processor = app(SquadReplenishmentProcessor::class);
        $data = new SeasonTransitionData(oldSeason: '2024', newSeason: '2025', competitionId: 'ESP1');

        $result = $processor->process($this->game, $data);

        $team1Count = GamePlayer::where('game_id', $this->game->id)
            ->where('team_id', $this->aiTeam->id)
            ->count();
        $team2Count = GamePlayer::where('game_id', $this->game->id)
            ->where('team_id', $aiTeam2->id)
            ->count();

        $this->assertEquals(22, $team1Count);
        $this->assertEquals(22, $team2Count);

        $generated = $result->getMetadata('squadReplenishment');
        $this->assertCount(10, $generated); // 7 + 3
    }

    public function test_metadata_contains_generated_player_info(): void
    {
        $this->createSquadForTeam($this->aiTeam, 20);

        $processor = app(SquadReplenishmentProcessor::class);
        $data = new SeasonTransitionData(oldSeason: '2024', newSeason: '2025', competitionId: 'ESP1');

        $result = $processor->process($this->game, $data);

        $generated = $result->getMetadata('squadReplenishment');
        $this->assertCount(2, $generated);

        foreach ($generated as $entry) {
            $this->assertArrayHasKey('playerId', $entry);
            $this->assertArrayHasKey('playerName', $entry);
            $this->assertArrayHasKey('position', $entry);
            $this->assertArrayHasKey('teamId', $entry);
            $this->assertEquals($this->aiTeam->id, $entry['teamId']);
        }
    }

    // =========================================
    // Helpers
    // =========================================

    /**
     * Create a balanced squad of the given size for a team.
     */
    private function createSquadForTeam(Team $team, int $count): void
    {
        $positions = [
            'Goalkeeper', 'Goalkeeper',
            'Centre-Back', 'Centre-Back', 'Centre-Back',
            'Left-Back', 'Right-Back',
            'Defensive Midfield', 'Central Midfield', 'Central Midfield',
            'Attacking Midfield', 'Left Midfield',
            'Right Midfield',
            'Left Winger', 'Right Winger',
            'Centre-Forward', 'Centre-Forward',
            'Second Striker',
            // Extra positions for larger squads
            'Centre-Back', 'Central Midfield', 'Right-Back', 'Left-Back',
            'Attacking Midfield', 'Centre-Forward', 'Left Winger',
        ];

        for ($i = 0; $i < $count; $i++) {
            $position = $positions[$i % count($positions)];
            $this->createGamePlayer($team, $position);
        }
    }

    private function createGamePlayer(
        Team $team,
        string $position,
        int $techAbility = 65,
        int $physAbility = 65,
    ): GamePlayer {
        $player = Player::factory()->create([
            'technical_ability' => $techAbility,
            'physical_ability' => $physAbility,
        ]);

        return GamePlayer::factory()->create([
            'game_id' => $this->game->id,
            'player_id' => $player->id,
            'team_id' => $team->id,
            'position' => $position,
            'game_technical_ability' => $techAbility,
            'game_physical_ability' => $physAbility,
        ]);
    }
}
