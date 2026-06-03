<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\Game;
use App\Models\GameStanding;
use App\Models\SimulatedSeason;
use App\Models\Team;
use App\Models\User;
use App\Modules\Competition\Promotions\RepairOutcome;
use App\Modules\Competition\Promotions\ReserveParentCoexistenceRepairer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Direct tests for the extracted repair service. Tier maps come from the
 * (config-driven) Spain CountryConfig — ESP1=tier1, ESP2=tier2,
 * ESP3A/ESP3B=tier3 — so only the per-game DB state is seeded here.
 */
class ReserveParentCoexistenceRepairerTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;
    private ReserveParentCoexistenceRepairer $repairer;

    protected function setUp(): void
    {
        parent::setUp();

        Competition::factory()->league()->create(['id' => 'ESP1', 'tier' => 1, 'handler_type' => 'league']);
        Competition::factory()->league()->create(['id' => 'ESP2', 'tier' => 2, 'handler_type' => 'league_with_playoff']);
        Competition::factory()->league()->create(['id' => 'ESP3A', 'tier' => 3, 'handler_type' => 'league_with_playoff']);
        Competition::factory()->league()->create(['id' => 'ESP3B', 'tier' => 3, 'handler_type' => 'league_with_playoff']);

        $user = User::factory()->create();
        $team = Team::factory()->create(['country' => 'ES']);
        $this->game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'competition_id' => 'ESP2',
            'season' => '2025',
            'country' => 'ES',
        ]);

        $this->repairer = app(ReserveParentCoexistenceRepairer::class);
    }

    public function test_plan_returns_nothing_to_fix_for_healthy_hierarchy(): void
    {
        [$parent, $reserve] = $this->reservePair();
        $this->entry('ESP2', $parent->id);
        $this->standing('ESP2', $parent->id, 5);
        $this->entry('ESP3A', $reserve->id);
        $this->standing('ESP3A', $reserve->id, 5);

        $result = $this->repairer->plan($this->game);

        $this->assertSame(RepairOutcome::NothingToFix, $result->outcome);
    }

    public function test_repair_swaps_inverted_reserve_and_parent(): void
    {
        [$parent, $reserve] = $this->reservePair();
        // Inverted: reserve wrongly above parent (reserve ESP1, parent ESP2).
        $this->entry('ESP1', $reserve->id);
        $this->standing('ESP1', $reserve->id, 8);
        $this->entry('ESP2', $parent->id);
        $this->standing('ESP2', $parent->id, 4);

        $result = $this->repairer->repair($this->game);

        $this->assertSame(RepairOutcome::Repaired, $result->outcome);
        // Hierarchy restored: parent above reserve.
        $this->assertEntry('ESP1', $parent->id);
        $this->assertEntry('ESP2', $reserve->id);
        $this->assertNotEntry('ESP1', $reserve->id);
        // Standings team_id swapped, positions preserved.
        $this->assertSame($parent->id, $this->teamAt('ESP1', 8));
        $this->assertSame($reserve->id, $this->teamAt('ESP2', 4));
    }

    public function test_repair_resolves_coexistence_by_promoting_parent(): void
    {
        [$parent, $reserve] = $this->reservePair();
        // Both in ESP2.
        $this->entry('ESP2', $parent->id);
        $this->standing('ESP2', $parent->id, 6);
        $this->entry('ESP2', $reserve->id);
        $this->standing('ESP2', $reserve->id, 12);
        // ESP1 bottom team to swap the parent up with.
        $esp1Bottom = Team::factory()->create(['country' => 'ES']);
        $this->entry('ESP1', $esp1Bottom->id);
        $this->standing('ESP1', $esp1Bottom->id, 20);

        $result = $this->repairer->repair($this->game);

        $this->assertSame(RepairOutcome::Repaired, $result->outcome);
        // Parent rises to ESP1; displaced bottom team drops to ESP2; reserve stays.
        $this->assertEntry('ESP1', $parent->id);
        $this->assertEntry('ESP2', $esp1Bottom->id);
        $this->assertEntry('ESP2', $reserve->id);
        $this->assertNotEntry('ESP2', $parent->id);
    }

    public function test_repair_handles_simulated_league_slots(): void
    {
        [$parent, $reserve] = $this->reservePair();
        // Coexist in ESP2 (played standings).
        $this->entry('ESP2', $parent->id);
        $this->standing('ESP2', $parent->id, 6);
        $this->entry('ESP2', $reserve->id);
        $this->standing('ESP2', $reserve->id, 12);
        // ESP1 is simulated (no game_standings) — exercises the sim-slot path.
        $a = Team::factory()->create(['country' => 'ES']);
        $b = Team::factory()->create(['country' => 'ES']);
        $this->entry('ESP1', $a->id);
        $this->entry('ESP1', $b->id);
        SimulatedSeason::create([
            'game_id' => $this->game->id, 'season' => '2025', 'competition_id' => 'ESP1', 'results' => [$a->id, $b->id],
        ]);

        $result = $this->repairer->repair($this->game);

        $this->assertSame(RepairOutcome::Repaired, $result->outcome);
        // Parent took the bottom sim slot (was $b); $b dropped to ESP2.
        $sim = SimulatedSeason::where('game_id', $this->game->id)->where('competition_id', 'ESP1')->first();
        $this->assertSame([$a->id, $parent->id], $sim->results);
        $this->assertEntry('ESP1', $parent->id);
        $this->assertEntry('ESP2', $b->id);
    }

    public function test_plan_is_unsafe_when_a_team_has_multiple_league_entries(): void
    {
        [$parent, $reserve] = $this->reservePair();
        $this->entry('ESP1', $reserve->id);
        $this->entry('ESP2', $reserve->id); // multi-league corruption
        $this->entry('ESP2', $parent->id);

        $result = $this->repairer->plan($this->game);

        $this->assertSame(RepairOutcome::Unsafe, $result->outcome);
        $this->assertStringContainsString('multiple league entries', (string) $result->reason);
    }

    public function test_plan_is_unsafe_for_coexistence_in_top_tier(): void
    {
        [$parent, $reserve] = $this->reservePair();
        // Both in ESP1 — there is no higher tier to swap the parent up into.
        $this->entry('ESP1', $parent->id);
        $this->standing('ESP1', $parent->id, 3);
        $this->entry('ESP1', $reserve->id);
        $this->standing('ESP1', $reserve->id, 9);

        $result = $this->repairer->plan($this->game);

        $this->assertSame(RepairOutcome::Unsafe, $result->outcome);
        $this->assertStringContainsString('no higher tier', (string) $result->reason);
    }

    public function test_plan_is_unsafe_when_slot_cannot_be_located(): void
    {
        [$parent, $reserve] = $this->reservePair();
        // Inverted entries present but no standings/sim rows to swap.
        $this->entry('ESP1', $reserve->id);
        $this->entry('ESP2', $parent->id);

        $result = $this->repairer->plan($this->game);

        $this->assertSame(RepairOutcome::Unsafe, $result->outcome);
        $this->assertStringContainsString('Cannot locate slot', (string) $result->reason);
    }

    public function test_apply_throws_when_entry_row_count_is_not_one(): void
    {
        [$parent, $reserve] = $this->reservePair();
        $this->entry('ESP1', $reserve->id);
        $this->standing('ESP1', $reserve->id, 8);
        $this->entry('ESP2', $parent->id);
        $this->standing('ESP2', $parent->id, 4);

        $plan = $this->repairer->plan($this->game);
        $this->assertSame(RepairOutcome::Repaired, $plan->outcome);

        // Corrupt the state between plan and apply so the entry update hits 0 rows.
        CompetitionEntry::where('game_id', $this->game->id)
            ->where('team_id', $reserve->id)
            ->where('competition_id', 'ESP1')
            ->delete();

        $this->expectException(\RuntimeException::class);
        DB::transaction(fn () => $this->repairer->apply($this->game, $plan));
    }

    /**
     * @return array{0: Team, 1: Team} [parent, reserve]
     */
    private function reservePair(): array
    {
        $parent = Team::factory()->create(['country' => 'ES']);
        $reserve = Team::factory()->create(['country' => 'ES', 'parent_team_id' => $parent->id]);

        return [$parent, $reserve];
    }

    private function entry(string $competitionId, string $teamId): void
    {
        CompetitionEntry::create([
            'game_id' => $this->game->id,
            'competition_id' => $competitionId,
            'team_id' => $teamId,
            'entry_round' => 1,
        ]);
    }

    private function standing(string $competitionId, string $teamId, int $position): void
    {
        GameStanding::create([
            'game_id' => $this->game->id,
            'competition_id' => $competitionId,
            'team_id' => $teamId,
            'position' => $position,
            'played' => 10, 'won' => 5, 'drawn' => 2, 'lost' => 3,
            'goals_for' => 15, 'goals_against' => 10, 'points' => 17,
        ]);
    }

    private function assertEntry(string $competitionId, string $teamId): void
    {
        $this->assertTrue(
            CompetitionEntry::where('game_id', $this->game->id)
                ->where('competition_id', $competitionId)
                ->where('team_id', $teamId)
                ->exists(),
            "Expected {$teamId} to have a {$competitionId} entry.",
        );
    }

    private function assertNotEntry(string $competitionId, string $teamId): void
    {
        $this->assertFalse(
            CompetitionEntry::where('game_id', $this->game->id)
                ->where('competition_id', $competitionId)
                ->where('team_id', $teamId)
                ->exists(),
            "Expected {$teamId} to NOT have a {$competitionId} entry.",
        );
    }

    private function teamAt(string $competitionId, int $position): ?string
    {
        return GameStanding::where('game_id', $this->game->id)
            ->where('competition_id', $competitionId)
            ->where('position', $position)
            ->value('team_id');
    }
}
