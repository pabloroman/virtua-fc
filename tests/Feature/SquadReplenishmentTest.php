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
        // 22 replenished + 2-3 youth = 24-25
        $this->assertGreaterThanOrEqual(24, $finalCount);
        $this->assertLessThanOrEqual(25, $finalCount);

        $generated = $result->getMetadata('squadReplenishment');
        // 7 replenishment + 2-3 youth = 9-10
        $replenishment = array_filter($generated, fn ($e) => $e['type'] === 'replenishment');
        $youth = array_filter($generated, fn ($e) => $e['type'] === 'youth_intake');
        $this->assertCount(7, $replenishment);
        $this->assertGreaterThanOrEqual(2, count($youth));
    }

    public function test_ai_team_at_minimum_receives_youth_intake(): void
    {
        // Create an AI team with exactly 22 players — youth intake still adds 2-3
        $this->createSquadForTeam($this->aiTeam, 22);

        $processor = app(SquadReplenishmentProcessor::class);
        $data = new SeasonTransitionData(oldSeason: '2024', newSeason: '2025', competitionId: 'ESP1');

        $result = $processor->process($this->game, $data);

        $finalCount = GamePlayer::where('game_id', $this->game->id)
            ->where('team_id', $this->aiTeam->id)
            ->count();
        $this->assertGreaterThanOrEqual(24, $finalCount);
        $this->assertLessThanOrEqual(25, $finalCount);

        $generated = $result->getMetadata('squadReplenishment');
        $this->assertNotEmpty($generated);

        $youthEntries = array_filter($generated, fn ($e) => $e['type'] === 'youth_intake');
        $this->assertGreaterThanOrEqual(2, count($youthEntries));
        $this->assertLessThanOrEqual(3, count($youthEntries));
    }

    public function test_ai_team_above_minimum_receives_youth_intake(): void
    {
        // Create an AI team with 25 players (above minimum) — youth intake still applies
        $this->createSquadForTeam($this->aiTeam, 25);

        $processor = app(SquadReplenishmentProcessor::class);
        $data = new SeasonTransitionData(oldSeason: '2024', newSeason: '2025', competitionId: 'ESP1');

        $result = $processor->process($this->game, $data);

        $finalCount = GamePlayer::where('game_id', $this->game->id)
            ->where('team_id', $this->aiTeam->id)
            ->count();
        // 25 + 2-3 youth = 27-28
        $this->assertGreaterThanOrEqual(27, $finalCount);
        $this->assertLessThanOrEqual(28, $finalCount);
    }

    public function test_ai_team_above_minimum_but_missing_goalkeepers_gets_them(): void
    {
        // Simulate the scenario: user bought all goalkeepers from an AI team
        // Team has 24 outfield players but 0 goalkeepers
        $outfieldPositions = [
            'Centre-Back', 'Centre-Back', 'Centre-Back', 'Centre-Back',
            'Left-Back', 'Left-Back', 'Right-Back', 'Right-Back',
            'Defensive Midfield', 'Defensive Midfield',
            'Central Midfield', 'Central Midfield', 'Central Midfield',
            'Attacking Midfield', 'Attacking Midfield',
            'Left Midfield', 'Right Midfield',
            'Left Winger', 'Right Winger',
            'Centre-Forward', 'Centre-Forward', 'Centre-Forward',
            'Second Striker', 'Second Striker',
        ];

        foreach ($outfieldPositions as $position) {
            $this->createGamePlayer($this->aiTeam, $position);
        }

        $this->assertEquals(24, GamePlayer::where('game_id', $this->game->id)
            ->where('team_id', $this->aiTeam->id)
            ->count());

        $gkBefore = GamePlayer::where('game_id', $this->game->id)
            ->where('team_id', $this->aiTeam->id)
            ->where('position', 'Goalkeeper')
            ->count();
        $this->assertEquals(0, $gkBefore);

        $processor = app(SquadReplenishmentProcessor::class);
        $data = new SeasonTransitionData(oldSeason: '2024', newSeason: '2025', competitionId: 'ESP1');

        $processor->process($this->game, $data);

        // Should have generated at least 2 goalkeepers (group minimum)
        $gkAfter = GamePlayer::where('game_id', $this->game->id)
            ->where('team_id', $this->aiTeam->id)
            ->where('position', 'Goalkeeper')
            ->count();
        $this->assertGreaterThanOrEqual(2, $gkAfter);
    }

    public function test_user_team_is_replenished_when_below_minimum(): void
    {
        // Create user team with only 10 players (well below minimum)
        $this->createSquadForTeam($this->userTeam, 10);

        $processor = app(SquadReplenishmentProcessor::class);
        $data = new SeasonTransitionData(oldSeason: '2024', newSeason: '2025', competitionId: 'ESP1');

        $result = $processor->process($this->game, $data);

        $finalCount = GamePlayer::where('game_id', $this->game->id)
            ->where('team_id', $this->userTeam->id)
            ->count();
        // Should be replenished to MIN_SQUAD_SIZE (22)
        $this->assertEquals(22, $finalCount);

        $generated = $result->getMetadata('squadReplenishment');
        $emergencyEntries = array_filter($generated, fn ($e) => $e['type'] === 'emergency_replenishment');
        $this->assertCount(12, $emergencyEntries);
    }

    public function test_user_team_at_or_above_minimum_is_not_replenished(): void
    {
        // Create user team with 22 players (at minimum) — should NOT be replenished
        $this->createSquadForTeam($this->userTeam, 22);

        $processor = app(SquadReplenishmentProcessor::class);
        $data = new SeasonTransitionData(oldSeason: '2024', newSeason: '2025', competitionId: 'ESP1');

        $processor->process($this->game, $data);

        $finalCount = GamePlayer::where('game_id', $this->game->id)
            ->where('team_id', $this->userTeam->id)
            ->count();
        $this->assertEquals(22, $finalCount);
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

        // Should have filled to 22 (replenishment) + 2-3 (youth) = 24-25
        $finalCount = GamePlayer::where('game_id', $this->game->id)
            ->where('team_id', $this->aiTeam->id)
            ->count();
        $this->assertGreaterThanOrEqual(24, $finalCount);

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

        // Both teams should be at least 22 (replenished) + 2-3 youth
        $this->assertGreaterThanOrEqual(24, $team1Count);
        $this->assertGreaterThanOrEqual(22, $team2Count);

        $generated = $result->getMetadata('squadReplenishment');
        // 7 replenishment + 3 replenishment + 2-3 youth per team (4-6 total youth)
        $this->assertGreaterThanOrEqual(14, count($generated));
    }

    public function test_metadata_contains_generated_player_info(): void
    {
        $this->createSquadForTeam($this->aiTeam, 20);
        // Give user team enough players to avoid emergency replenishment
        $this->createSquadForTeam($this->userTeam, 22);

        $processor = app(SquadReplenishmentProcessor::class);
        $data = new SeasonTransitionData(oldSeason: '2024', newSeason: '2025', competitionId: 'ESP1');

        $result = $processor->process($this->game, $data);

        $generated = $result->getMetadata('squadReplenishment');
        // 2 replenishment + 2-3 youth = 4-5 total
        $this->assertGreaterThanOrEqual(4, count($generated));

        $aiEntries = array_filter($generated, fn ($e) => $e['teamId'] === $this->aiTeam->id);
        foreach ($aiEntries as $entry) {
            $this->assertArrayHasKey('playerId', $entry);
            $this->assertArrayHasKey('playerName', $entry);
            $this->assertArrayHasKey('position', $entry);
            $this->assertArrayHasKey('teamId', $entry);
            $this->assertArrayHasKey('type', $entry);
            $this->assertEquals($this->aiTeam->id, $entry['teamId']);
        }
    }

    // =========================================
    // Youth intake tests
    // =========================================

    public function test_youth_intake_generates_young_players(): void
    {
        // Create a fully-staffed AI team
        $this->createSquadForTeam($this->aiTeam, 22);

        $processor = app(SquadReplenishmentProcessor::class);
        $data = new SeasonTransitionData(oldSeason: '2024', newSeason: '2025', competitionId: 'ESP1');

        $result = $processor->process($this->game, $data);

        $generated = $result->getMetadata('squadReplenishment');
        $youthEntries = array_filter($generated, fn ($e) => $e['type'] === 'youth_intake');

        foreach ($youthEntries as $entry) {
            $gamePlayer = GamePlayer::find($entry['playerId']);
            $age = $gamePlayer->age($this->game->current_date);
            $this->assertGreaterThanOrEqual(17, $age, "Youth player should be at least 17");
            $this->assertLessThanOrEqual(20, $age, "Youth player should be at most 20");
        }
    }

    public function test_youth_players_are_weaker_than_team_average(): void
    {
        // Create a team with avg ability 70
        for ($i = 0; $i < 22; $i++) {
            $position = $i < 2 ? 'Goalkeeper' : ($i < 7 ? 'Centre-Back' : ($i < 12 ? 'Central Midfield' : 'Centre-Forward'));
            $this->createGamePlayer($this->aiTeam, $position, techAbility: 70, physAbility: 70);
        }

        $processor = app(SquadReplenishmentProcessor::class);
        $data = new SeasonTransitionData(oldSeason: '2024', newSeason: '2025', competitionId: 'ESP1');

        $result = $processor->process($this->game, $data);

        $generated = $result->getMetadata('squadReplenishment');
        $youthEntries = array_filter($generated, fn ($e) => $e['type'] === 'youth_intake');

        foreach ($youthEntries as $entry) {
            $gamePlayer = GamePlayer::find($entry['playerId']);
            $avgAbility = ($gamePlayer->game_technical_ability + $gamePlayer->game_physical_ability) / 2;
            // Youth should be 55-75% of team avg (70) = roughly 38-53
            $this->assertLessThan(70, $avgAbility, "Youth player ability should be below team average");
        }
    }

    public function test_youth_intake_releases_oldest_when_near_cap(): void
    {
        // Create a team with 27 players — some old (age 32+), some young
        for ($i = 0; $i < 20; $i++) {
            $position = $i < 2 ? 'Goalkeeper' : ($i < 7 ? 'Centre-Back' : ($i < 12 ? 'Central Midfield' : 'Centre-Forward'));
            $this->createGamePlayer($this->aiTeam, $position, techAbility: 65, physAbility: 65);
        }
        // Add 7 old, weak players (age 33)
        $referenceDate = $this->game->current_date ?? now();
        for ($i = 0; $i < 7; $i++) {
            $player = Player::factory()->create([
                'date_of_birth' => $referenceDate->copy()->subYears(33),
                'technical_ability' => 35,
                'physical_ability' => 35,
            ]);
            GamePlayer::factory()->create([
                'game_id' => $this->game->id,
                'player_id' => $player->id,
                'team_id' => $this->aiTeam->id,
                'position' => 'Central Midfield',
                'game_technical_ability' => 35,
                'game_physical_ability' => 35,
            ]);
        }

        $this->assertEquals(27, GamePlayer::where('game_id', $this->game->id)
            ->where('team_id', $this->aiTeam->id)
            ->count());

        $processor = app(SquadReplenishmentProcessor::class);
        $data = new SeasonTransitionData(oldSeason: '2024', newSeason: '2025', competitionId: 'ESP1');

        $processor->process($this->game, $data);

        // Squad should not exceed the cap (28) + youth count (3 max)
        $finalCount = GamePlayer::where('game_id', $this->game->id)
            ->where('team_id', $this->aiTeam->id)
            ->count();
        $this->assertLessThanOrEqual(31, $finalCount);

        // Some old players should have been released (team_id set to null)
        $released = GamePlayer::where('game_id', $this->game->id)
            ->whereNull('team_id')
            ->count();
        $this->assertGreaterThan(0, $released);
    }

    public function test_youth_intake_skips_user_team(): void
    {
        // Give the user team 22 players
        $this->createSquadForTeam($this->userTeam, 22);
        // Give the AI team 22 players
        $this->createSquadForTeam($this->aiTeam, 22);

        $userCountBefore = GamePlayer::where('game_id', $this->game->id)
            ->where('team_id', $this->userTeam->id)
            ->count();

        $processor = app(SquadReplenishmentProcessor::class);
        $data = new SeasonTransitionData(oldSeason: '2024', newSeason: '2025', competitionId: 'ESP1');

        $processor->process($this->game, $data);

        $userCountAfter = GamePlayer::where('game_id', $this->game->id)
            ->where('team_id', $this->userTeam->id)
            ->count();

        // User team should be unchanged
        $this->assertEquals($userCountBefore, $userCountAfter);

        // AI team should have received youth intake
        $aiCountAfter = GamePlayer::where('game_id', $this->game->id)
            ->where('team_id', $this->aiTeam->id)
            ->count();
        $this->assertGreaterThan(22, $aiCountAfter);
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
