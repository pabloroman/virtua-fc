<?php

namespace Tests\Unit;

use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\Game;
use App\Models\GameStanding;
use App\Models\Team;
use App\Models\User;
use App\Modules\Competition\Promotions\ReserveCascader;
use App\Modules\Competition\Services\CountryConfig;
use App\Modules\Competition\Services\ReserveTeamFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the case where the per-rule promotion/relegation swap leaves a
 * reserve in the same competition as its parent — the bug uncovered by
 * the production audit queries that found 7 offending parent/reserve pairs
 * across 6 live games.
 *
 * The cascader's contract under test: any parent/reserve pair sharing a
 * competition above the bottom of the chain is resolved by demoting the
 * reserve one tier; conflicts at the bottom of the chain are accepted.
 */
class ReserveCascaderTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        Competition::factory()->league()->create(['id' => 'ESP1', 'tier' => 1]);
        Competition::factory()->league()->create(['id' => 'ESP2', 'tier' => 2]);
        Competition::factory()->league()->create(['id' => 'ESP3A', 'tier' => 3]);
        Competition::factory()->league()->create(['id' => 'ESP3B', 'tier' => 3]);

        $user = User::factory()->create();
        $userTeam = Team::factory()->create();
        $this->game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $userTeam->id,
            'competition_id' => 'ESP1',
            'season' => '2025',
        ]);
    }

    private function cascader(): ReserveCascader
    {
        return new ReserveCascader(app(CountryConfig::class), new ReserveTeamFilter);
    }

    /**
     * Place a team in a competition with a real standings row at $position.
     */
    private function place(string $competitionId, Team $team, int $position): void
    {
        CompetitionEntry::create([
            'game_id' => $this->game->id,
            'competition_id' => $competitionId,
            'team_id' => $team->id,
            'entry_round' => 1,
        ]);

        GameStanding::create([
            'game_id' => $this->game->id,
            'competition_id' => $competitionId,
            'team_id' => $team->id,
            'position' => $position,
            'played' => 0,
            'won' => 0,
            'drawn' => 0,
            'lost' => 0,
            'goals_for' => 0,
            'goals_against' => 0,
            'points' => 0,
        ]);
    }

    public function test_parent_relegated_into_reserve_tier_cascades_reserve_down(): void
    {
        // Parent (Celta) just arrived in ESP2; reserve (Fortuna) was already there.
        $parent = Team::factory()->create(['name' => 'RC Celta']);
        $reserve = Team::factory()->create(['name' => 'RC Celta Fortuna', 'parent_team_id' => $parent->id]);

        $this->place('ESP2', $parent, 21);
        $this->place('ESP2', $reserve, 8);

        // A filler team in ESP3A to act as the backfill candidate.
        $filler = Team::factory()->create();
        $this->place('ESP3A', $filler, 1);

        $affected = $this->cascader()->cascade($this->game);

        $this->assertContains('ESP2', $affected);
        $this->assertContains('ESP3A', $affected);

        $this->assertDatabaseHas('competition_entries', [
            'game_id' => $this->game->id,
            'competition_id' => 'ESP3A',
            'team_id' => $reserve->id,
        ]);
        $this->assertDatabaseMissing('competition_entries', [
            'game_id' => $this->game->id,
            'competition_id' => 'ESP2',
            'team_id' => $reserve->id,
        ]);

        // Filler took the open ESP2 slot.
        $this->assertDatabaseHas('competition_entries', [
            'game_id' => $this->game->id,
            'competition_id' => 'ESP2',
            'team_id' => $filler->id,
        ]);

        // Parent stays put.
        $this->assertDatabaseHas('competition_entries', [
            'game_id' => $this->game->id,
            'competition_id' => 'ESP2',
            'team_id' => $parent->id,
        ]);
    }

    public function test_legacy_conflict_in_top_division_is_repaired(): void
    {
        // Pre-existing drift: parent and reserve both in ESP1.
        $parent = Team::factory()->create();
        $reserve = Team::factory()->create(['parent_team_id' => $parent->id]);

        $this->place('ESP1', $parent, 5);
        $this->place('ESP1', $reserve, 12);
        $this->place('ESP2', Team::factory()->create(), 1);

        $this->cascader()->cascade($this->game);

        $this->assertDatabaseHas('competition_entries', [
            'game_id' => $this->game->id,
            'competition_id' => 'ESP2',
            'team_id' => $reserve->id,
        ]);
    }

    public function test_conflict_at_bottom_of_chain_is_accepted(): void
    {
        // Both teams in ESP3A — bottom of chain, no lower tier to cascade to.
        $parent = Team::factory()->create();
        $reserve = Team::factory()->create(['parent_team_id' => $parent->id]);

        $this->place('ESP3A', $parent, 1);
        $this->place('ESP3A', $reserve, 2);

        $affected = $this->cascader()->cascade($this->game);

        $this->assertEmpty($affected);

        // Both still in ESP3A — not cascaded.
        $this->assertDatabaseHas('competition_entries', [
            'game_id' => $this->game->id,
            'competition_id' => 'ESP3A',
            'team_id' => $reserve->id,
        ]);
        $this->assertDatabaseHas('competition_entries', [
            'game_id' => $this->game->id,
            'competition_id' => 'ESP3A',
            'team_id' => $parent->id,
        ]);
    }

    public function test_replacement_skips_reserves_whose_parent_is_in_destination(): void
    {
        // Parent A in ESP2 with reserve A — needs to cascade.
        $parentA = Team::factory()->create();
        $reserveA = Team::factory()->create(['parent_team_id' => $parentA->id]);
        $this->place('ESP2', $parentA, 21);
        $this->place('ESP2', $reserveA, 8);

        // ESP3A top of standings is parentB; just below is reserveB whose parent
        // is parentC sitting in ESP2. picking reserveB would recreate a conflict.
        $parentB = Team::factory()->create();
        $parentC = Team::factory()->create();
        $reserveB = Team::factory()->create(['parent_team_id' => $parentC->id]);
        $this->place('ESP2', $parentC, 15);
        $this->place('ESP3A', $parentB, 1);
        $this->place('ESP3A', $reserveB, 2);

        $this->cascader()->cascade($this->game);

        // reserveA moved to ESP3A.
        $this->assertDatabaseHas('competition_entries', [
            'game_id' => $this->game->id,
            'competition_id' => 'ESP3A',
            'team_id' => $reserveA->id,
        ]);

        // parentB (position 1) backfilled to ESP2 — reserveB skipped because
        // its parent (parentC) is in ESP2.
        $this->assertDatabaseHas('competition_entries', [
            'game_id' => $this->game->id,
            'competition_id' => 'ESP2',
            'team_id' => $parentB->id,
        ]);
        $this->assertDatabaseHas('competition_entries', [
            'game_id' => $this->game->id,
            'competition_id' => 'ESP3A',
            'team_id' => $reserveB->id,
        ]);
    }

    public function test_destination_is_balanced_across_split_groups(): void
    {
        // ESP2 has parent + reserve. ESP3B is smaller than ESP3A — cascade
        // should pick ESP3B to keep the groups balanced.
        $parent = Team::factory()->create();
        $reserve = Team::factory()->create(['parent_team_id' => $parent->id]);
        $this->place('ESP2', $parent, 21);
        $this->place('ESP2', $reserve, 8);

        for ($i = 1; $i <= 5; $i++) {
            $this->place('ESP3A', Team::factory()->create(), $i);
        }
        for ($i = 1; $i <= 2; $i++) {
            $this->place('ESP3B', Team::factory()->create(), $i);
        }

        $this->cascader()->cascade($this->game);

        $this->assertDatabaseHas('competition_entries', [
            'game_id' => $this->game->id,
            'competition_id' => 'ESP3B',
            'team_id' => $reserve->id,
        ]);
    }

    public function test_cascading_user_team_updates_game_competition_id(): void
    {
        // User controls the reserve that's about to be cascaded.
        $parent = Team::factory()->create();
        $this->game->update(['team_id' => Team::factory()->create()->id]); // not the reserve
        $reserve = Team::factory()->create(['parent_team_id' => $parent->id]);
        $this->game->update(['team_id' => $reserve->id, 'competition_id' => 'ESP2']);

        $this->place('ESP2', $parent, 21);
        $this->place('ESP2', $reserve, 8);
        $this->place('ESP3A', Team::factory()->create(), 1);

        $this->cascader()->cascade($this->game->fresh());

        $this->assertSame('ESP3A', $this->game->fresh()->competition_id);
    }
}
