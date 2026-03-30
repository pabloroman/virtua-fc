<?php

namespace Tests\Feature;

use App\Jobs\DeleteGameJob;
use App\Models\Competition;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Player;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeleteGameJobTest extends TestCase
{
    use RefreshDatabase;

    private function createGameWithTeam(): array
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        Competition::factory()->league()->create(['id' => 'ESP1']);

        $game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'competition_id' => 'ESP1',
            'season' => '2024',
        ]);

        return [$game, $team];
    }

    private function createGeneratedPlayer(Game $game, Team $team): Player
    {
        $player = Player::create([
            'id' => Str::uuid()->toString(),
            'transfermarkt_id' => 'gen-'.Str::uuid()->toString(),
            'name' => 'Test Generated',
            'nationality' => ['Spain'],
            'date_of_birth' => '2000-01-01',
            'technical_ability' => 60,
            'physical_ability' => 60,
        ]);

        GamePlayer::create([
            'id' => Str::uuid()->toString(),
            'game_id' => $game->id,
            'player_id' => $player->id,
            'team_id' => $team->id,
            'position' => 'Centre-Back',
            'market_value_cents' => 1_000_000_00,
            'contract_until' => '2027-06-30',
            'annual_wage' => 500_000_00,
            'fitness' => 90,
            'morale' => 70,
            'durability' => 80,
            'game_technical_ability' => 60,
            'game_physical_ability' => 60,
            'potential' => 70,
            'potential_low' => 65,
            'potential_high' => 75,
            'season_appearances' => 0,
            'tier' => 3,
            'number' => 5,
        ]);

        return $player;
    }

    private function createRealPlayer(Game $game, Team $team): Player
    {
        $player = Player::create([
            'id' => Str::uuid()->toString(),
            'transfermarkt_id' => '12345',
            'name' => 'Real Player',
            'nationality' => ['Spain'],
            'date_of_birth' => '1995-03-10',
            'technical_ability' => 80,
            'physical_ability' => 75,
        ]);

        GamePlayer::create([
            'id' => Str::uuid()->toString(),
            'game_id' => $game->id,
            'player_id' => $player->id,
            'team_id' => $team->id,
            'position' => 'Attacking Midfield',
            'market_value_cents' => 20_000_000_00,
            'contract_until' => '2028-06-30',
            'annual_wage' => 5_000_000_00,
            'fitness' => 95,
            'morale' => 80,
            'durability' => 85,
            'game_technical_ability' => 80,
            'game_physical_ability' => 75,
            'potential' => 85,
            'potential_low' => 80,
            'potential_high' => 90,
            'season_appearances' => 0,
            'tier' => 1,
            'number' => 10,
        ]);

        return $player;
    }

    public function test_deleting_game_removes_orphaned_generated_players(): void
    {
        [$game, $team] = $this->createGameWithTeam();
        $generatedPlayer = $this->createGeneratedPlayer($game, $team);

        (new DeleteGameJob($game->id))->handle();

        $this->assertDatabaseMissing('games', ['id' => $game->id]);
        $this->assertDatabaseMissing('game_players', ['game_id' => $game->id]);
        $this->assertDatabaseMissing('players', ['id' => $generatedPlayer->id]);
    }

    public function test_deleting_game_does_not_remove_real_players(): void
    {
        [$game, $team] = $this->createGameWithTeam();
        $realPlayer = $this->createRealPlayer($game, $team);

        (new DeleteGameJob($game->id))->handle();

        $this->assertDatabaseMissing('games', ['id' => $game->id]);
        $this->assertDatabaseHas('players', ['id' => $realPlayer->id]);
    }

    public function test_deleting_game_does_not_remove_generated_players_referenced_by_other_games(): void
    {
        [$game1, $team] = $this->createGameWithTeam();
        $generatedPlayer = $this->createGeneratedPlayer($game1, $team);

        // Create a second game referencing the same generated player
        $user2 = User::factory()->create();
        $game2 = Game::factory()->create([
            'user_id' => $user2->id,
            'team_id' => $team->id,
            'competition_id' => 'ESP1',
            'season' => '2024',
        ]);

        GamePlayer::create([
            'id' => Str::uuid()->toString(),
            'game_id' => $game2->id,
            'player_id' => $generatedPlayer->id,
            'team_id' => $team->id,
            'position' => 'Centre-Back',
            'market_value_cents' => 1_000_000_00,
            'contract_until' => '2027-06-30',
            'annual_wage' => 500_000_00,
            'fitness' => 90,
            'morale' => 70,
            'durability' => 80,
            'game_technical_ability' => 60,
            'game_physical_ability' => 60,
            'potential' => 70,
            'potential_low' => 65,
            'potential_high' => 75,
            'season_appearances' => 0,
            'tier' => 3,
            'number' => 5,
        ]);

        // Delete only game1
        (new DeleteGameJob($game1->id))->handle();

        $this->assertDatabaseMissing('games', ['id' => $game1->id]);
        // Player should still exist because game2 references it
        $this->assertDatabaseHas('players', ['id' => $generatedPlayer->id]);
        $this->assertDatabaseHas('game_players', [
            'game_id' => $game2->id,
            'player_id' => $generatedPlayer->id,
        ]);
    }
}
