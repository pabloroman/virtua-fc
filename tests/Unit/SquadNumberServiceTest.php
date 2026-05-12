<?php

namespace Tests\Unit;

use App\Models\Game;
use App\Models\GamePlayer;
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

        $unresolvable = $this->service->reassignNumbers($this->game);

        $this->assertEquals(1, $unresolvable);

        $orphan->refresh();
        $this->assertNull($orphan->number, 'Unresolvable over-23 should have number cleared');
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

        // 1 over-23 wrongly sitting in an academy slot (e.g. aged out of U-23
        // at season transition) — should be promoted to a first-team slot.
        $agedOut = $this->createPlayer(age: 24, number: 29, position: 'Centre-Forward');

        $unresolvable = $this->service->reassignNumbers($this->game);

        $this->assertEquals(0, $unresolvable);

        $agedOut->refresh();
        $this->assertNotNull($agedOut->number);
        $this->assertLessThanOrEqual(25, $agedOut->number, 'Over-23 in academy should be moved to a first-team slot');
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

        $this->service->reassignNumbers($this->game);

        $numbers = GamePlayer::where('game_id', $this->game->id)
            ->where('team_id', $this->team->id)
            ->whereNotNull('number')
            ->pluck('number');

        $this->assertEquals($numbers->count(), $numbers->unique()->count(), 'All assigned numbers must be unique');
    }

    public function test_reassign_does_not_auto_enroll_user_excluded_players(): void
    {
        // User has 22 players enrolled in first team (slots 1-22).
        for ($i = 1; $i <= 22; $i++) {
            $this->createPlayer(age: 27, number: $i, position: 'Central Midfield');
        }

        // 3 players the user deliberately left unenrolled — e.g. transfer-listed,
        // loaned-in surplus, players they intend to sell or send back. They
        // must stay null after reassignment, otherwise they'd jump into the
        // matchday squad ahead of intentionally registered players.
        $forSale = $this->createPlayer(age: 29, number: null, position: 'Centre-Back');
        $loanedIn = $this->createPlayer(age: 26, number: null, position: 'Right Winger');
        $youngSurplus = $this->createPlayer(age: 19, number: null, position: 'Left-Back');

        $unresolvable = $this->service->reassignNumbers($this->game);

        $this->assertEquals(0, $unresolvable);
        $forSale->refresh();
        $loanedIn->refresh();
        $youngSurplus->refresh();
        $this->assertNull($forSale->number, 'Transfer-listed player must stay unenrolled');
        $this->assertNull($loanedIn->number, 'Loaned-in surplus player must stay unenrolled');
        $this->assertNull($youngSurplus->number, 'Surplus under-23 must stay unenrolled');
    }

    private function createPlayer(int $age, ?int $number, string $position = 'Central Midfield'): GamePlayer
    {
        return GamePlayer::factory()
            ->forGame($this->game)
            ->forTeam($this->team)
            ->age($age, $this->game->current_date)
            ->create([
                'position' => $position,
                'number' => $number,
            ]);
    }
}
