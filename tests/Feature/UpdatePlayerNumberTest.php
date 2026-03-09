<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Player;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdatePlayerNumberTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Game $game;
    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->team = Team::factory()->create();
        Competition::factory()->league()->create(['id' => 'ESP1']);

        $this->game = Game::factory()->create([
            'user_id' => $this->user->id,
            'team_id' => $this->team->id,
            'competition_id' => 'ESP1',
            'season' => '2024',
        ]);
    }

    private function createGamePlayer(array $attrs = []): GamePlayer
    {
        $player = Player::factory()->create();

        return GamePlayer::factory()->create(array_merge([
            'game_id' => $this->game->id,
            'team_id' => $this->team->id,
            'player_id' => $player->id,
        ], $attrs));
    }

    public function test_can_update_player_number(): void
    {
        $gamePlayer = $this->createGamePlayer(['number' => 10]);

        $response = $this->actingAs($this->user)
            ->postJson(route('game.squad.number', [$this->game->id, $gamePlayer->id]), [
                'number' => 7,
            ]);

        $response->assertOk()
            ->assertJson(['success' => true, 'number' => 7]);

        $this->assertDatabaseHas('game_players', [
            'id' => $gamePlayer->id,
            'number' => 7,
        ]);
    }

    public function test_can_assign_number_1(): void
    {
        $gamePlayer = $this->createGamePlayer(['number' => 25]);

        $response = $this->actingAs($this->user)
            ->postJson(route('game.squad.number', [$this->game->id, $gamePlayer->id]), [
                'number' => 1,
            ]);

        $response->assertOk()
            ->assertJson(['success' => true, 'number' => 1]);
    }

    public function test_rejects_empty_number(): void
    {
        $gamePlayer = $this->createGamePlayer(['number' => 10]);

        $response = $this->actingAs($this->user)
            ->postJson(route('game.squad.number', [$this->game->id, $gamePlayer->id]), [
                'number' => null,
            ]);

        $response->assertUnprocessable();

        $this->assertDatabaseHas('game_players', [
            'id' => $gamePlayer->id,
            'number' => 10,
        ]);
    }

    public function test_rejects_duplicate_number_on_same_team(): void
    {
        $this->createGamePlayer(['number' => 7]);
        $playerB = $this->createGamePlayer(['number' => 11]);

        $response = $this->actingAs($this->user)
            ->postJson(route('game.squad.number', [$this->game->id, $playerB->id]), [
                'number' => 7,
            ]);

        $response->assertUnprocessable();
    }

    public function test_allows_same_number_on_different_team(): void
    {
        $otherTeam = Team::factory()->create();
        $this->createGamePlayer(['number' => 7, 'team_id' => $otherTeam->id]);
        $playerB = $this->createGamePlayer(['number' => 11]);

        $response = $this->actingAs($this->user)
            ->postJson(route('game.squad.number', [$this->game->id, $playerB->id]), [
                'number' => 7,
            ]);

        $response->assertOk()
            ->assertJson(['success' => true, 'number' => 7]);
    }

    public function test_rejects_number_zero(): void
    {
        $gamePlayer = $this->createGamePlayer(['number' => 10]);

        $response = $this->actingAs($this->user)
            ->postJson(route('game.squad.number', [$this->game->id, $gamePlayer->id]), [
                'number' => 0,
            ]);

        $response->assertUnprocessable();
    }

    public function test_rejects_number_above_99(): void
    {
        $gamePlayer = $this->createGamePlayer(['number' => 10]);

        $response = $this->actingAs($this->user)
            ->postJson(route('game.squad.number', [$this->game->id, $gamePlayer->id]), [
                'number' => 100,
            ]);

        $response->assertUnprocessable();
    }

    public function test_rejects_negative_number(): void
    {
        $gamePlayer = $this->createGamePlayer(['number' => 10]);

        $response = $this->actingAs($this->user)
            ->postJson(route('game.squad.number', [$this->game->id, $gamePlayer->id]), [
                'number' => -1,
            ]);

        $response->assertUnprocessable();
    }

    public function test_cannot_edit_another_teams_player(): void
    {
        $otherTeam = Team::factory()->create();
        $gamePlayer = $this->createGamePlayer(['number' => 10, 'team_id' => $otherTeam->id]);

        $response = $this->actingAs($this->user)
            ->postJson(route('game.squad.number', [$this->game->id, $gamePlayer->id]), [
                'number' => 7,
            ]);

        $response->assertNotFound();
    }

    public function test_keeping_same_number_succeeds(): void
    {
        $gamePlayer = $this->createGamePlayer(['number' => 10]);

        $response = $this->actingAs($this->user)
            ->postJson(route('game.squad.number', [$this->game->id, $gamePlayer->id]), [
                'number' => 10,
            ]);

        $response->assertOk()
            ->assertJson(['success' => true, 'number' => 10]);
    }

    public function test_unauthenticated_access_redirects(): void
    {
        $gamePlayer = $this->createGamePlayer(['number' => 10]);

        $response = $this->postJson(route('game.squad.number', [$this->game->id, $gamePlayer->id]), [
            'number' => 7,
        ]);

        $response->assertUnauthorized();
    }
}
