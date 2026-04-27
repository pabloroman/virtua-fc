<?php

namespace Tests\Unit;

use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\Game;
use App\Models\GameStanding;
use App\Models\SimulatedSeason;
use App\Models\Team;
use App\Models\User;
use App\Modules\Competition\Promotions\PromotionSlotAllocator;
use App\Modules\Competition\Services\ReserveTeamFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests the single-pass slot allocator that prevents the historical
 * "team appears in both direct-promotion and bracket lists" bug.
 *
 * The invariant under test: for any standings layout, directPromotions
 * and playoffQualifiers are always disjoint, with their counts capped
 * at the configured slots.
 */
class PromotionSlotAllocatorTest extends TestCase
{
    use RefreshDatabase;

    private const TOP_DIVISION = 'ESP1';
    private const BOTTOM_DIVISION = 'ESP2';

    private Game $game;
    /** @var Team[] $topDivisionTeams */
    private array $topDivisionTeams = [];

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        Competition::factory()->league()->create(['id' => self::TOP_DIVISION, 'tier' => 1]);
        Competition::factory()->league()->create(['id' => self::BOTTOM_DIVISION, 'tier' => 2]);

        $userTeam = Team::factory()->create();
        $this->game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $userTeam->id,
            'competition_id' => self::TOP_DIVISION,
            'season' => '2025',
        ]);

        // Seed a 20-team top-division roster so the reserve filter has a
        // reference set for parent-club lookups. Tests that need a specific
        // team to be a reserve's parent push their custom team in via
        // makeReserveParentInTopDivision().
        for ($i = 0; $i < 20; $i++) {
            $team = Team::factory()->create();
            $this->topDivisionTeams[] = $team;
            CompetitionEntry::create([
                'game_id' => $this->game->id,
                'competition_id' => self::TOP_DIVISION,
                'team_id' => $team->id,
                'entry_round' => 1,
            ]);
        }
    }

    private function allocator(): PromotionSlotAllocator
    {
        return new PromotionSlotAllocator(new ReserveTeamFilter);
    }

    /**
     * Place a team at a given standings position in ESP2.
     */
    private function placeAtPosition(int $position, Team $team): void
    {
        GameStanding::create([
            'game_id' => $this->game->id,
            'competition_id' => self::BOTTOM_DIVISION,
            'team_id' => $team->id,
            'position' => $position,
            'played' => 42,
            'won' => max(0, 25 - $position),
            'drawn' => 5,
            'lost' => $position,
            'goals_for' => max(20, 70 - $position),
            'goals_against' => 20 + $position,
            'points' => max(0, (25 - $position) * 3 + 5),
        ]);
    }

    /**
     * Build a 22-team ESP2 standings layout. Returns the teams keyed by
     * position so individual tests can mark a specific position as a reserve.
     *
     * @return array<int, Team> Position (1-indexed) => Team
     */
    private function seedFullStandings(): array
    {
        $teamsByPosition = [];
        for ($i = 1; $i <= 22; $i++) {
            $team = Team::factory()->create();
            $this->placeAtPosition($i, $team);
            $teamsByPosition[$i] = $team;
        }
        return $teamsByPosition;
    }

    /**
     * Make the team at the given position a reserve whose parent club
     * is in the top division (so the filter blocks it). Replaces the
     * existing standings row in-place.
     */
    private function makeReserve(int $position, array &$teamsByPosition): Team
    {
        $parent = $this->topDivisionTeams[0];
        $reserve = Team::factory()->create(['parent_team_id' => $parent->id]);

        // Replace the existing row at this position
        GameStanding::where('game_id', $this->game->id)
            ->where('competition_id', self::BOTTOM_DIVISION)
            ->where('position', $position)
            ->delete();

        $this->placeAtPosition($position, $reserve);
        $teamsByPosition[$position] = $reserve;

        return $reserve;
    }

    /** Convenience: extract teamIds from a slot list in order. */
    private function teamIds(array $slots): array
    {
        return array_column($slots, 'teamId');
    }

    // ──────────────────────────────────────────────────
    // Happy path
    // ──────────────────────────────────────────────────

    public function test_no_reserves_assigns_top_two_to_direct_and_next_four_to_playoff(): void
    {
        $teams = $this->seedFullStandings();

        $allocation = $this->allocator()->allocate($this->game, self::BOTTOM_DIVISION, 2, 4);

        $this->assertSame(
            [$teams[1]->id, $teams[2]->id],
            $this->teamIds($allocation->directPromotions),
        );
        $this->assertSame(
            [$teams[3]->id, $teams[4]->id, $teams[5]->id, $teams[6]->id],
            $this->teamIds($allocation->playoffQualifiers),
        );
    }

    // ──────────────────────────────────────────────────
    // Reserve clustering — the bug class this fix addresses
    // ──────────────────────────────────────────────────

    /**
     * The Angel/Pontevedra scenario: ESP2 champion is a reserve (Real Madrid
     * Castilla). Direct promotions slide to positions 2, 3 — and crucially,
     * the bracket starts at position 4, not 3. Pre-fix, position 3 was both
     * directly promoted (post-filter) and seeded into the bracket.
     */
    public function test_reserve_at_position_one_shifts_direct_promotions_down_without_collision(): void
    {
        $teams = $this->seedFullStandings();
        $this->makeReserve(1, $teams);

        $allocation = $this->allocator()->allocate($this->game, self::BOTTOM_DIVISION, 2, 4);

        $direct = $this->teamIds($allocation->directPromotions);
        $playoff = $this->teamIds($allocation->playoffQualifiers);

        $this->assertSame([$teams[2]->id, $teams[3]->id], $direct);
        $this->assertSame(
            [$teams[4]->id, $teams[5]->id, $teams[6]->id, $teams[7]->id],
            $playoff,
        );
        $this->assertEmpty(
            array_intersect($direct, $playoff),
            'Direct and playoff slots must be disjoint',
        );
    }

    public function test_reserve_at_position_two_shifts_only_second_direct_slot_down(): void
    {
        $teams = $this->seedFullStandings();
        $this->makeReserve(2, $teams);

        $allocation = $this->allocator()->allocate($this->game, self::BOTTOM_DIVISION, 2, 4);

        $this->assertSame(
            [$teams[1]->id, $teams[3]->id],
            $this->teamIds($allocation->directPromotions),
        );
        $this->assertSame(
            [$teams[4]->id, $teams[5]->id, $teams[6]->id, $teams[7]->id],
            $this->teamIds($allocation->playoffQualifiers),
        );
    }

    public function test_two_reserves_at_top_pushes_both_lists_further_down(): void
    {
        $teams = $this->seedFullStandings();
        $this->makeReserve(1, $teams);
        $this->makeReserve(2, $teams);

        $allocation = $this->allocator()->allocate($this->game, self::BOTTOM_DIVISION, 2, 4);

        $this->assertSame(
            [$teams[3]->id, $teams[4]->id],
            $this->teamIds($allocation->directPromotions),
        );
        $this->assertSame(
            [$teams[5]->id, $teams[6]->id, $teams[7]->id, $teams[8]->id],
            $this->teamIds($allocation->playoffQualifiers),
        );
    }

    public function test_reserve_inside_bracket_range_is_skipped_without_affecting_direct(): void
    {
        $teams = $this->seedFullStandings();
        $this->makeReserve(4, $teams);

        $allocation = $this->allocator()->allocate($this->game, self::BOTTOM_DIVISION, 2, 4);

        $this->assertSame(
            [$teams[1]->id, $teams[2]->id],
            $this->teamIds($allocation->directPromotions),
        );
        $this->assertSame(
            [$teams[3]->id, $teams[5]->id, $teams[6]->id, $teams[7]->id],
            $this->teamIds($allocation->playoffQualifiers),
        );
    }

    public function test_reserves_in_both_direct_and_bracket_ranges(): void
    {
        $teams = $this->seedFullStandings();
        $this->makeReserve(1, $teams);
        $this->makeReserve(5, $teams);

        $allocation = $this->allocator()->allocate($this->game, self::BOTTOM_DIVISION, 2, 4);

        $this->assertSame(
            [$teams[2]->id, $teams[3]->id],
            $this->teamIds($allocation->directPromotions),
        );
        // Position 5 is skipped, so bracket = [4, 6, 7, 8]
        $this->assertSame(
            [$teams[4]->id, $teams[6]->id, $teams[7]->id, $teams[8]->id],
            $this->teamIds($allocation->playoffQualifiers),
        );
    }

    public function test_three_consecutive_reserves_at_top(): void
    {
        $teams = $this->seedFullStandings();
        $this->makeReserve(1, $teams);
        $this->makeReserve(2, $teams);
        $this->makeReserve(3, $teams);

        $allocation = $this->allocator()->allocate($this->game, self::BOTTOM_DIVISION, 2, 4);

        $this->assertSame(
            [$teams[4]->id, $teams[5]->id],
            $this->teamIds($allocation->directPromotions),
        );
        $this->assertSame(
            [$teams[6]->id, $teams[7]->id, $teams[8]->id, $teams[9]->id],
            $this->teamIds($allocation->playoffQualifiers),
        );
    }

    // ──────────────────────────────────────────────────
    // Reserve filter scope — only blocks when parent is in top division
    // ──────────────────────────────────────────────────

    public function test_reserve_whose_parent_is_not_in_top_division_is_eligible(): void
    {
        $teams = $this->seedFullStandings();

        // Build a reserve whose parent is *not* in ESP1 — should NOT be filtered.
        $unrelatedParent = Team::factory()->create();
        $reserve = Team::factory()->create(['parent_team_id' => $unrelatedParent->id]);

        GameStanding::where('game_id', $this->game->id)
            ->where('competition_id', self::BOTTOM_DIVISION)
            ->where('position', 1)
            ->delete();

        $this->placeAtPosition(1, $reserve);

        $allocation = $this->allocator()->allocate($this->game, self::BOTTOM_DIVISION, 2, 4);

        $this->assertSame($reserve->id, $allocation->directPromotions[0]['teamId']);
    }

    // ──────────────────────────────────────────────────
    // Disjointness invariant (defensive)
    // ──────────────────────────────────────────────────

    /**
     * Property-style: across many randomised reserve placements, direct and
     * playoff lists must always be disjoint. This is the load-bearing
     * invariant that PromotionRelegationProcessor::validatePlan asserts.
     *
     * Range tested: 0..3 reserves at random positions in 1..7. Combined
     * with the 22-team standings, this covers every plausible Spanish layout.
     *
     * @dataProvider randomReserveLayouts
     */
    public function test_direct_and_playoff_are_always_disjoint(array $reservePositions): void
    {
        $teams = $this->seedFullStandings();
        foreach ($reservePositions as $pos) {
            $this->makeReserve($pos, $teams);
        }

        $allocation = $this->allocator()->allocate($this->game, self::BOTTOM_DIVISION, 2, 4);

        $direct = $this->teamIds($allocation->directPromotions);
        $playoff = $this->teamIds($allocation->playoffQualifiers);

        $this->assertCount(2, $direct, 'Should always fill direct slots');
        $this->assertCount(4, $playoff, 'Should always fill playoff slots');
        $this->assertEmpty(
            array_intersect($direct, $playoff),
            'Slot lists must be disjoint for any reserve layout. '
            . 'Reserves at: ' . json_encode($reservePositions),
        );
    }

    public static function randomReserveLayouts(): array
    {
        return [
            'no reserves'              => [[]],
            'reserve at 1'             => [[1]],
            'reserve at 2'             => [[2]],
            'reserve at 3'             => [[3]],
            'reserve at 4'             => [[4]],
            'reserves at 1,2'          => [[1, 2]],
            'reserves at 1,3'          => [[1, 3]],
            'reserves at 1,4'          => [[1, 4]],
            'reserves at 2,4'          => [[2, 4]],
            'reserves at 1,2,3'        => [[1, 2, 3]],
            'reserves at 1,3,5'        => [[1, 3, 5]],
            'reserves at 2,4,6'        => [[2, 4, 6]],
        ];
    }

    // ──────────────────────────────────────────────────
    // Insufficient eligible teams
    // ──────────────────────────────────────────────────

    public function test_returns_partial_allocation_when_not_enough_eligible_teams(): void
    {
        // Only 4 teams in the standings, all eligible — caller asks for 2+4=6
        for ($i = 1; $i <= 4; $i++) {
            $this->placeAtPosition($i, Team::factory()->create());
        }

        $allocation = $this->allocator()->allocate($this->game, self::BOTTOM_DIVISION, 2, 4);

        // Allocator is non-throwing: it returns what it found and lets the
        // caller decide whether to fail. Direct fills first.
        $this->assertCount(2, $allocation->directPromotions);
        $this->assertCount(2, $allocation->playoffQualifiers);
    }

    // ──────────────────────────────────────────────────
    // Simulated standings fallback
    // ──────────────────────────────────────────────────

    public function test_falls_back_to_simulated_season_when_no_real_standings_exist(): void
    {
        $teams = [];
        for ($i = 0; $i < 22; $i++) {
            $teams[] = Team::factory()->create();
        }

        SimulatedSeason::create([
            'game_id' => $this->game->id,
            'season' => '2025',
            'competition_id' => self::BOTTOM_DIVISION,
            'results' => array_map(fn ($t) => $t->id, $teams),
        ]);

        $allocation = $this->allocator()->allocate($this->game, self::BOTTOM_DIVISION, 2, 4);

        $this->assertSame(
            [$teams[0]->id, $teams[1]->id],
            $this->teamIds($allocation->directPromotions),
        );
        $this->assertSame(
            [$teams[2]->id, $teams[3]->id, $teams[4]->id, $teams[5]->id],
            $this->teamIds($allocation->playoffQualifiers),
        );
    }

    public function test_simulated_path_also_applies_reserve_filter(): void
    {
        $parent = $this->topDivisionTeams[0];
        $reserve = Team::factory()->create(['parent_team_id' => $parent->id]);
        $regulars = [];
        for ($i = 0; $i < 21; $i++) {
            $regulars[] = Team::factory()->create();
        }

        SimulatedSeason::create([
            'game_id' => $this->game->id,
            'season' => '2025',
            'competition_id' => self::BOTTOM_DIVISION,
            // Reserve "wins" the simulated league at index 0 (position 1)
            'results' => array_merge([$reserve->id], array_map(fn ($t) => $t->id, $regulars)),
        ]);

        $allocation = $this->allocator()->allocate($this->game, self::BOTTOM_DIVISION, 2, 4);

        // Reserve at position 1 is filtered; direct slides to positions 2, 3.
        $this->assertSame(
            [$regulars[0]->id, $regulars[1]->id],
            $this->teamIds($allocation->directPromotions),
        );
        $this->assertSame(
            [$regulars[2]->id, $regulars[3]->id, $regulars[4]->id, $regulars[5]->id],
            $this->teamIds($allocation->playoffQualifiers),
        );
    }

    // ──────────────────────────────────────────────────
    // No data at all
    // ──────────────────────────────────────────────────

    public function test_returns_empty_allocation_when_no_data_source_exists(): void
    {
        // No standings, no simulated season.
        $allocation = $this->allocator()->allocate($this->game, self::BOTTOM_DIVISION, 2, 4);

        $this->assertTrue($allocation->isEmpty());
        $this->assertSame([], $allocation->directPromotions);
        $this->assertSame([], $allocation->playoffQualifiers);
    }

    // ──────────────────────────────────────────────────
    // Slot-count edge cases
    // ──────────────────────────────────────────────────

    public function test_zero_playoff_slots_returns_only_direct_promotions(): void
    {
        $teams = $this->seedFullStandings();

        $allocation = $this->allocator()->allocate($this->game, self::BOTTOM_DIVISION, 2, 0);

        $this->assertCount(2, $allocation->directPromotions);
        $this->assertCount(0, $allocation->playoffQualifiers);
    }

    public function test_zero_direct_slots_assigns_everything_to_playoff(): void
    {
        $teams = $this->seedFullStandings();

        $allocation = $this->allocator()->allocate($this->game, self::BOTTOM_DIVISION, 0, 4);

        $this->assertCount(0, $allocation->directPromotions);
        $this->assertSame(
            [$teams[1]->id, $teams[2]->id, $teams[3]->id, $teams[4]->id],
            $this->teamIds($allocation->playoffQualifiers),
        );
    }

    // ──────────────────────────────────────────────────
    // Standings-position metadata is preserved
    // ──────────────────────────────────────────────────

    public function test_each_slot_carries_its_standings_position(): void
    {
        $teams = $this->seedFullStandings();
        $this->makeReserve(1, $teams);

        $allocation = $this->allocator()->allocate($this->game, self::BOTTOM_DIVISION, 2, 4);

        $this->assertSame(2, $allocation->directPromotions[0]['position']);
        $this->assertSame(3, $allocation->directPromotions[1]['position']);
        $this->assertSame(4, $allocation->playoffQualifiers[0]['position']);
        $this->assertSame(7, $allocation->playoffQualifiers[3]['position']);
    }
}
