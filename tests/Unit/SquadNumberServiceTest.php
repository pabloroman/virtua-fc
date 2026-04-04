<?php

namespace Tests\Unit;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Player;
use App\Models\Team;
use App\Modules\Squad\Services\SquadNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SquadNumberServiceTest extends TestCase
{
    use RefreshDatabase;

    private SquadNumberService $service;

    private Game $game;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SquadNumberService();
        $this->team = Team::factory()->create();
        $this->game = Game::factory()->forTeam($this->team)->atDate('2024-08-15')->create();
    }

    public function test_reassign_gives_slot_1_to_over23_when_only_free_slot(): void
    {
        // 24 over-23 players in first-team slots 2-25
        for ($i = 2; $i <= 25; $i++) {
            $this->createPlayer(age: 28, number: $i, position: 'Central Midfield');
        }

        // 1 under-23 in slot 1
        $this->createPlayer(age: 20, number: 1, position: 'Goalkeeper');

        // 1 over-23 in academy slot 26 (needs first-team slot)
        $over23InAcademy = $this->createPlayer(age: 25, number: 26, position: 'Centre-Back');

        $unresolvable = $this->service->reassignNumbers($this->game);

        $this->assertEquals(0, $unresolvable);

        $over23InAcademy->refresh();
        $this->assertEquals(1, $over23InAcademy->number, 'Over-23 should get slot 1 (the only free first-team slot)');
    }

    public function test_reassign_clears_stale_academy_number_when_no_first_team_slot_available(): void
    {
        // Fill all 25 first-team slots with over-23 players
        for ($i = 1; $i <= 25; $i++) {
            $this->createPlayer(age: 28, number: $i, position: 'Central Midfield');
        }

        // 1 over-23 in academy slot 26 — truly unresolvable (all 25 taken by over-23)
        $orphan = $this->createPlayer(age: 25, number: 26, position: 'Centre-Back');

        // 1 under-23 without a number
        $youngster = $this->createPlayer(age: 19, number: null, position: 'Left Winger');

        $unresolvable = $this->service->reassignNumbers($this->game);

        $this->assertEquals(1, $unresolvable);

        $orphan->refresh();
        $this->assertNull($orphan->number, 'Unresolvable over-23 should have number cleared');

        $youngster->refresh();
        $this->assertNotNull($youngster->number, 'Under-23 should get an academy slot');
        $this->assertGreaterThanOrEqual(26, $youngster->number);
    }

    public function test_reassign_handles_normal_operation_without_collisions(): void
    {
        // 10 over-23 in first-team slots
        for ($i = 1; $i <= 10; $i++) {
            $this->createPlayer(age: 28, number: $i);
        }

        // 3 under-23 in academy slots
        $this->createPlayer(age: 20, number: 26, position: 'Goalkeeper');
        $this->createPlayer(age: 19, number: 27, position: 'Right Winger');
        $this->createPlayer(age: 21, number: 28, position: 'Left-Back');

        // 1 over-23 without a number (e.g. returned from loan)
        $loanReturn = $this->createPlayer(age: 26, number: null, position: 'Centre-Forward');

        // 1 under-23 without a number
        $newYouth = $this->createPlayer(age: 18, number: null, position: 'Central Midfield');

        $unresolvable = $this->service->reassignNumbers($this->game);

        $this->assertEquals(0, $unresolvable);

        $loanReturn->refresh();
        $this->assertNotNull($loanReturn->number);
        $this->assertLessThanOrEqual(25, $loanReturn->number, 'Over-23 should get a first-team slot');

        $newYouth->refresh();
        $this->assertNotNull($newYouth->number);
    }

    public function test_reassign_no_duplicate_numbers(): void
    {
        // Mix of players that need reassignment
        for ($i = 2; $i <= 20; $i++) {
            $this->createPlayer(age: 28, number: $i);
        }

        $this->createPlayer(age: 20, number: 1, position: 'Goalkeeper');

        // Several over-23 in academy needing first-team slots
        $this->createPlayer(age: 25, number: 26, position: 'Right-Back');
        $this->createPlayer(age: 24, number: 27, position: 'Left-Back');

        // Under-23 without numbers
        $this->createPlayer(age: 19, number: null, position: 'Central Midfield');
        $this->createPlayer(age: 18, number: null, position: 'Right Winger');

        $this->service->reassignNumbers($this->game);

        $numbers = GamePlayer::where('game_id', $this->game->id)
            ->where('team_id', $this->team->id)
            ->whereNotNull('number')
            ->pluck('number');

        $this->assertEquals($numbers->count(), $numbers->unique()->count(), 'All assigned numbers must be unique');
    }

    private function createPlayer(int $age, ?int $number, string $position = 'Central Midfield'): GamePlayer
    {
        $player = Player::factory()->age($age, $this->game->current_date)->create();

        return GamePlayer::factory()
            ->forGame($this->game)
            ->forTeam($this->team)
            ->create([
                'player_id' => $player->id,
                'position' => $position,
                'number' => $number,
            ]);
    }
}
