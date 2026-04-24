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
