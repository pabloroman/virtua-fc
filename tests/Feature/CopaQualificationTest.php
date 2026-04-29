<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\Game;
use App\Models\GameStanding;
use App\Models\SimulatedSeason;
use App\Models\Team;
use App\Models\User;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Season\Processors\CopaQualificationProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CopaQualificationTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    /** @var Team[][] keyed by competition id, ordered by position (0-indexed) */
    private array $teamsByCompetition = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Existing tests below verify rule-level semantics (auto_qualify +
        // top_per_group + reserve cascade) in isolation. The new
        // `target_size` invariant runs as an outer layer on top of those
        // rules; tests for it live in the dedicated section at the bottom
        // of this file and set target_size explicitly. To keep the rule-
        // semantics tests stable, suppress target_size by default here.
        config(['countries.ES.cup_qualification.ESPCUP.target_size' => null]);

        Competition::factory()->league()->create(['id' => 'ESP1', 'country' => 'ES', 'tier' => 1]);
        Competition::factory()->league()->create([
            'id' => 'ESP2', 'country' => 'ES', 'tier' => 2, 'handler_type' => 'league_with_playoff',
        ]);
        Competition::factory()->league()->create([
            'id' => 'ESP3A', 'country' => 'ES', 'tier' => 3, 'handler_type' => 'league_with_playoff',
        ]);
        Competition::factory()->league()->create([
            'id' => 'ESP3B', 'country' => 'ES', 'tier' => 3, 'handler_type' => 'league_with_playoff',
        ]);
        Competition::factory()->knockoutCup()->create(['id' => 'ESPCUP', 'country' => 'ES']);

        $user = User::factory()->create();
        $userTeam = Team::factory()->create(['country' => 'ES']);
        $this->game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $userTeam->id,
            'competition_id' => 'ESP1',
            'season' => '2025',
        ]);

        $this->createLeague('ESP1', 20, $userTeam);
        $this->createLeague('ESP2', 22);
        $this->createLeague('ESP3A', 20);
        $this->createLeague('ESP3B', 20);
    }

    public function test_base_case_qualifies_all_tier_1_and_2_plus_top_5_per_primera_rfef_group(): void
    {
        $this->runProcessor();

        $entries = $this->cupEntries();

        $this->assertCount(20 + 22 + 5 + 5, $entries);

        foreach ($this->teamsByCompetition['ESP1'] as $team) {
            $this->assertContains($team->id, $entries, "ESP1 team {$team->id} should qualify");
        }
        foreach ($this->teamsByCompetition['ESP2'] as $team) {
            $this->assertContains($team->id, $entries, "ESP2 team {$team->id} should qualify");
        }
        foreach (['ESP3A', 'ESP3B'] as $group) {
            foreach (array_slice($this->teamsByCompetition[$group], 0, 5) as $team) {
                $this->assertContains($team->id, $entries, "Top-5 {$group} team {$team->id} should qualify");
            }
            foreach (array_slice($this->teamsByCompetition[$group], 5) as $team) {
                $this->assertNotContains($team->id, $entries, "Non-top-5 {$group} team {$team->id} should not qualify");
            }
        }
    }

    public function test_reserve_team_in_top_5_is_skipped_and_next_non_reserve_qualifies(): void
    {
        // Make position 3 in ESP3A a reserve of an ESP1 team — it must be
        // skipped and position 6 bumped up into the top 5.
        $parent = $this->teamsByCompetition['ESP1'][0];
        $this->teamsByCompetition['ESP3A'][2]->update(['parent_team_id' => $parent->id]);

        $this->runProcessor();

        $entries = $this->cupEntries();
        $esp3a = $this->teamsByCompetition['ESP3A'];

        $this->assertContains($esp3a[0]->id, $entries, 'pos 1 qualifies');
        $this->assertContains($esp3a[1]->id, $entries, 'pos 2 qualifies');
        $this->assertNotContains($esp3a[2]->id, $entries, 'reserve at pos 3 skipped');
        $this->assertContains($esp3a[3]->id, $entries, 'pos 4 qualifies');
        $this->assertContains($esp3a[4]->id, $entries, 'pos 5 qualifies');
        $this->assertContains($esp3a[5]->id, $entries, 'pos 6 bumped into top 5');
        $this->assertNotContains($esp3a[6]->id, $entries, 'pos 7 does not qualify');

        $esp3aQualifiers = array_values(array_filter(
            $entries,
            fn (string $id) => in_array($id, array_map(fn (Team $t) => $t->id, $esp3a), true),
        ));
        $this->assertCount(5, $esp3aQualifiers, 'ESP3A still contributes exactly 5 qualifiers');
    }

    public function test_reserve_in_auto_qualify_tier_cascades_extra_slot_to_primera_rfef_groups(): void
    {
        // Three reserves occupying ESP2 slots — cascade should add 3 total
        // picks across the two groups (round-robin: A=+2, B=+1 → A picks 7,
        // B picks 6), keeping the cup at 52 qualifiers.
        $parents = $this->teamsByCompetition['ESP1'];
        $this->teamsByCompetition['ESP2'][5]->update(['parent_team_id' => $parents[0]->id]);
        $this->teamsByCompetition['ESP2'][10]->update(['parent_team_id' => $parents[1]->id]);
        $this->teamsByCompetition['ESP2'][15]->update(['parent_team_id' => $parents[2]->id]);

        $this->runProcessor();

        $entries = $this->cupEntries();
        $this->assertCount(52, $entries, 'Cup should stay at 52 qualifiers');

        $esp3aIds = array_map(fn (Team $t) => $t->id, $this->teamsByCompetition['ESP3A']);
        $esp3bIds = array_map(fn (Team $t) => $t->id, $this->teamsByCompetition['ESP3B']);

        $esp3aCount = count(array_intersect($entries, $esp3aIds));
        $esp3bCount = count(array_intersect($entries, $esp3bIds));

        $this->assertEquals(7, $esp3aCount, 'ESP3A absorbs 2 cascaded seats');
        $this->assertEquals(6, $esp3bCount, 'ESP3B absorbs 1 cascaded seat');

        // The three reserves themselves never qualify
        $this->assertNotContains($this->teamsByCompetition['ESP2'][5]->id, $entries);
        $this->assertNotContains($this->teamsByCompetition['ESP2'][10]->id, $entries);
        $this->assertNotContains($this->teamsByCompetition['ESP2'][15]->id, $entries);
    }

    public function test_lower_tier_seed_teams_without_any_playable_tier_entry_are_preserved(): void
    {
        // Lower-division "flavour" teams: they sit in ESPCUP but aren't
        // registered in ESP1/2/3 at all. They should survive the rebuild.
        $regional1 = Team::factory()->create(['country' => 'ES', 'name' => 'CD Numancia']);
        $regional2 = Team::factory()->create(['country' => 'ES', 'name' => 'Real Jaén CF']);

        foreach ([$regional1, $regional2] as $team) {
            CompetitionEntry::create([
                'game_id' => $this->game->id,
                'competition_id' => 'ESPCUP',
                'team_id' => $team->id,
                'entry_round' => 1,
            ]);
        }

        $this->runProcessor();

        $entries = $this->cupEntries();

        $this->assertContains($regional1->id, $entries, 'Lower-tier seed team preserved');
        $this->assertContains($regional2->id, $entries, 'Lower-tier seed team preserved');
        $this->assertCount(52 + 2, $entries, 'Qualifiers + preserved seed teams');
    }

    public function test_reserve_team_previously_in_copa_is_removed_on_rebuild(): void
    {
        $parent = $this->teamsByCompetition['ESP1'][0];
        $strayReserve = Team::factory()->create([
            'country' => 'ES',
            'parent_team_id' => $parent->id,
        ]);

        CompetitionEntry::create([
            'game_id' => $this->game->id,
            'competition_id' => 'ESPCUP',
            'team_id' => $strayReserve->id,
            'entry_round' => 1,
        ]);

        $this->runProcessor();

        $this->assertNotContains($strayReserve->id, $this->cupEntries());
    }

    public function test_falls_back_to_simulated_season_when_standings_are_empty(): void
    {
        // Mirror a non-player group where the season was simulated: drop
        // standings for ESP3B and register results in SimulatedSeason.
        GameStanding::where('game_id', $this->game->id)
            ->where('competition_id', 'ESP3B')
            ->delete();

        SimulatedSeason::create([
            'game_id' => $this->game->id,
            'season' => '2025',
            'competition_id' => 'ESP3B',
            'results' => array_map(fn (Team $t) => $t->id, $this->teamsByCompetition['ESP3B']),
        ]);

        $this->runProcessor();

        $entries = $this->cupEntries();

        foreach (array_slice($this->teamsByCompetition['ESP3B'], 0, 5) as $team) {
            $this->assertContains($team->id, $entries, 'Simulated top-5 ESP3B team qualifies');
        }
        foreach (array_slice($this->teamsByCompetition['ESP3B'], 5) as $team) {
            $this->assertNotContains($team->id, $entries, 'Simulated non-top-5 does not qualify');
        }
    }

    // ---------------------------------------------------------------------
    // target_size invariant
    //
    // These tests cover the outer top-up layer that maintains a stable cup
    // size across season transitions. Without it the cup permanently shrinks
    // because the rule replaces the seed's ~21 ESP3 teams with only 11 —
    // which produced 93 broken Copa del Rey draws in production with odd
    // team pools cascading into missing semifinal ties.
    // ---------------------------------------------------------------------

    public function test_target_size_tops_up_qualifiers_to_meet_total(): void
    {
        // Rule produces 52 qualifiers (20+22+5+5). target_size=70 means we
        // need to pull 18 more non-reserves from the ESP3 groups.
        config(['countries.ES.cup_qualification.ESPCUP.target_size' => 70]);

        $this->runProcessor();

        $entries = $this->cupEntries();
        $this->assertCount(70, $entries, 'Cup must reach the target size via top-up');

        // ESP1+ESP2 still all in (auto_qualify exhaustive)
        foreach (['ESP1', 'ESP2'] as $comp) {
            foreach ($this->teamsByCompetition[$comp] as $team) {
                $this->assertContains($team->id, $entries);
            }
        }

        // ESP3 contribution: 5 base each + 18 top-up split round-robin = 9 extra
        // each (5+9 = 14 each, 28 total) — but ESP3A goes first so it
        // gets 9 extras and ESP3B gets 9 extras (pairs round-robin: idx 0,1,0,1...)
        $esp3aIds = array_map(fn (Team $t) => $t->id, $this->teamsByCompetition['ESP3A']);
        $esp3bIds = array_map(fn (Team $t) => $t->id, $this->teamsByCompetition['ESP3B']);

        $esp3a = count(array_intersect($entries, $esp3aIds));
        $esp3b = count(array_intersect($entries, $esp3bIds));

        $this->assertSame(28, $esp3a + $esp3b, 'ESP3 groups together absorb the 18-team top-up');
        // The 18 extras go round-robin starting from ESP3A → ESP3A=9 extra, ESP3B=9 extra.
        $this->assertEqualsWithDelta(14, $esp3a, 1, 'ESP3A got fair share');
        $this->assertEqualsWithDelta(14, $esp3b, 1, 'ESP3B got fair share');
    }

    public function test_target_size_top_up_pulls_in_position_order(): void
    {
        config(['countries.ES.cup_qualification.ESPCUP.target_size' => 56]);

        $this->runProcessor();

        $entries = $this->cupEntries();
        $this->assertCount(56, $entries);

        // 4 extras over 52 qualifiers, distributed round-robin: ESP3A gets
        // 2 extras (positions 6 & 7), ESP3B gets 2 extras (positions 6 & 7).
        $esp3a = $this->teamsByCompetition['ESP3A'];
        $esp3b = $this->teamsByCompetition['ESP3B'];

        $this->assertContains($esp3a[5]->id, $entries, 'ESP3A pos 6 pulled in by top-up');
        $this->assertContains($esp3a[6]->id, $entries, 'ESP3A pos 7 pulled in by top-up');
        $this->assertNotContains($esp3a[7]->id, $entries, 'ESP3A pos 8 still excluded');

        $this->assertContains($esp3b[5]->id, $entries, 'ESP3B pos 6 pulled in by top-up');
        $this->assertContains($esp3b[6]->id, $entries, 'ESP3B pos 7 pulled in by top-up');
        $this->assertNotContains($esp3b[7]->id, $entries, 'ESP3B pos 8 still excluded');
    }

    public function test_target_size_skips_reserves_during_top_up(): void
    {
        // Mark ESP3A pos 6 as a reserve. Top-up must skip it and grab pos 7
        // for that ESP3A slot — final count still hits target.
        $parent = $this->teamsByCompetition['ESP1'][0];
        $this->teamsByCompetition['ESP3A'][5]->update(['parent_team_id' => $parent->id]);

        config(['countries.ES.cup_qualification.ESPCUP.target_size' => 54]);

        $this->runProcessor();

        $entries = $this->cupEntries();
        $this->assertCount(54, $entries);
        $this->assertNotContains(
            $this->teamsByCompetition['ESP3A'][5]->id,
            $entries,
            'Reserve at ESP3A pos 6 must never qualify even during top-up'
        );
        $this->assertContains(
            $this->teamsByCompetition['ESP3A'][6]->id,
            $entries,
            'ESP3A pos 7 takes the slot the reserve would have'
        );
    }

    public function test_target_size_counts_untouched_regional_teams_against_target(): void
    {
        // Regional teams in cup but outside playable tiers. They survive the
        // rebuild and count toward target_size, so the qualifier top-up
        // should pull less from groups.
        $regional = collect(range(1, 10))->map(
            fn (int $i) => Team::factory()->create(['country' => 'ES', 'name' => "Regional {$i}"])
        );

        foreach ($regional as $team) {
            CompetitionEntry::create([
                'game_id' => $this->game->id,
                'competition_id' => 'ESPCUP',
                'team_id' => $team->id,
                'entry_round' => 1,
            ]);
        }

        config(['countries.ES.cup_qualification.ESPCUP.target_size' => 70]);

        $this->runProcessor();

        $entries = $this->cupEntries();
        $this->assertCount(70, $entries);

        // 10 regional are preserved → only 60 qualifiers needed (52 base + 8 top-up)
        foreach ($regional as $team) {
            $this->assertContains($team->id, $entries);
        }

        $esp3aIds = array_map(fn (Team $t) => $t->id, $this->teamsByCompetition['ESP3A']);
        $esp3bIds = array_map(fn (Team $t) => $t->id, $this->teamsByCompetition['ESP3B']);
        $esp3Total = count(array_intersect($entries, $esp3aIds))
            + count(array_intersect($entries, $esp3bIds));

        // 5+5 base + 8 top-up across both groups = 18 ESP3 total
        $this->assertSame(18, $esp3Total);
    }

    public function test_target_size_unreachable_throws_loudly(): void
    {
        // target_size larger than the entire eligible team pool. The
        // processor must throw — silent shortfalls are exactly how the
        // 93-broken-Copa-del-Rey-draws bug stayed hidden in production.
        config(['countries.ES.cup_qualification.ESPCUP.target_size' => 1000]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/ESPCUP.*target_size 1000 unreachable.*short by/i');

        $this->runProcessor();
    }

    public function test_target_size_below_rule_output_does_not_remove_qualifiers(): void
    {
        // Rule output is already 52. A target_size of 30 would imply removal
        // — but the contract is "top up, never trim", so qualifiers stay.
        config(['countries.ES.cup_qualification.ESPCUP.target_size' => 30]);

        $this->runProcessor();

        $this->assertCount(
            52,
            $this->cupEntries(),
            'Rule output must not be trimmed below its natural size'
        );
    }

    public function test_target_size_handles_one_group_exhausting_before_target(): void
    {
        // Mark every ESP3A team after pos 5 as a reserve so the group has
        // exactly 5 eligible teams. With target_size demanding more from
        // ESP3, the round-robin must skip exhausted ESP3A and keep pulling
        // from ESP3B.
        $parent = $this->teamsByCompetition['ESP1'][0];
        for ($i = 5; $i < 20; $i++) {
            $this->teamsByCompetition['ESP3A'][$i]->update(['parent_team_id' => $parent->id]);
        }

        config(['countries.ES.cup_qualification.ESPCUP.target_size' => 65]);

        $this->runProcessor();

        $entries = $this->cupEntries();

        // Available: ESP1 (20) + ESP2 (22) + ESP3A 5 non-reserves + ESP3B 20 = 67.
        // Target 65: pull rule baseline (52, but ESP3A only contributes 5 — actually
        // the cascade DOES NOT trigger here since reserves aren't in
        // auto_qualify_tiers, so baseline stays 52). Then top-up needs 13
        // more. ESP3A has 0 left after 5; ESP3B has 15 left → all from B.
        $this->assertCount(65, $entries);

        $esp3bIds = array_map(fn (Team $t) => $t->id, $this->teamsByCompetition['ESP3B']);
        $esp3bCount = count(array_intersect($entries, $esp3bIds));
        $this->assertSame(18, $esp3bCount, 'ESP3B absorbed the entire top-up after ESP3A exhausted');
    }

    public function test_target_size_keeps_round_1_count_even_for_supercup_cascade(): void
    {
        // Production scenario: target_size 116 on Spain config. After
        // SeasonInitializationService bumps 4 supercup teams to round 3,
        // round 1 must contain an even count for a clean knockout cascade.
        config(['countries.ES.cup_qualification.ESPCUP.target_size' => 116]);

        // Add 64 regional teams to reach the production-like baseline.
        $regional = collect(range(1, 64))->map(
            fn (int $i) => Team::factory()->create(['country' => 'ES'])
        );
        foreach ($regional as $team) {
            CompetitionEntry::create([
                'game_id' => $this->game->id,
                'competition_id' => 'ESPCUP',
                'team_id' => $team->id,
                'entry_round' => 1,
            ]);
        }

        $this->runProcessor();

        $entries = $this->cupEntries();
        $this->assertCount(116, $entries);

        // Simulate the supercup bump for 4 teams.
        CompetitionEntry::where('game_id', $this->game->id)
            ->where('competition_id', 'ESPCUP')
            ->whereIn('team_id', array_slice($entries, 0, 4))
            ->update(['entry_round' => 3]);

        $round1 = CompetitionEntry::where('game_id', $this->game->id)
            ->where('competition_id', 'ESPCUP')
            ->where('entry_round', 1)
            ->count();

        $this->assertSame(112, $round1, 'Round 1 must be even for clean cascade');
        $this->assertSame(0, $round1 % 2, 'Round 1 parity invariant');
    }

    private function runProcessor(): void
    {
        app(CopaQualificationProcessor::class)->process(
            $this->game,
            new SeasonTransitionData(oldSeason: '2025', newSeason: '2026', competitionId: 'ESP1'),
        );
    }

    /**
     * @return string[]
     */
    private function cupEntries(): array
    {
        return CompetitionEntry::where('game_id', $this->game->id)
            ->where('competition_id', 'ESPCUP')
            ->pluck('team_id')
            ->all();
    }

    private function createLeague(string $competitionId, int $count, ?Team $firstTeam = null): void
    {
        $teams = [];
        for ($i = 0; $i < $count; $i++) {
            $team = ($i === 0 && $firstTeam)
                ? $firstTeam
                : Team::factory()->create(['country' => 'ES']);

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
                'position' => $i + 1,
                'played' => 38,
                'won' => max(0, 20 - $i),
                'drawn' => 5,
                'lost' => max(0, $i - 5),
                'goals_for' => max(10, 60 - $i * 2),
                'goals_against' => 20 + $i,
                'points' => max(0, (20 - $i) * 3 + 5),
            ]);

            $teams[] = $team;
        }

        $this->teamsByCompetition[$competitionId] = $teams;
    }
}
