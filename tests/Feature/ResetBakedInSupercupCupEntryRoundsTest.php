<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\Team;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The seeder used to bake the supercup skip-ahead into
 * competition_teams.entry_round. CupEntryRoundService now reads that column
 * as the round a cup's data file declared, so a database still carrying the
 * baked bump parks last season's supercup clubs at the round of 32 on top of
 * this season's field — five clubs where the cup budgeted for four, and a
 * first round drawn from an odd pool.
 *
 * The repair migration undoes the old rule without touching a round a data
 * file declares for itself.
 */
class ResetBakedInSupercupCupEntryRoundsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Competition::factory()->knockoutCup()->create(['id' => 'ESPCUP', 'country' => 'ES']);
        Competition::factory()->knockoutCup()->create(['id' => 'ESPSUP', 'country' => 'ES']);
    }

    public function test_it_clears_the_baked_supercup_bump_from_the_cup(): void
    {
        $supercupClubs = $this->teams(4);
        $this->seedRounds('ESPSUP', $supercupClubs, 1);
        // The old seeder wrote the country's cup_entry_round on exactly the
        // clubs that were in that season's supercup.
        $this->seedRounds('ESPCUP', $supercupClubs, 3);
        $this->seedRounds('ESPCUP', $this->teams(112), 1);

        $this->migration()->up();

        $this->assertSame(116, $this->countAtRound('ESPCUP', 1));
        $this->assertSame(0, $this->countAtRound('ESPCUP', 3));
    }

    public function test_it_leaves_a_round_the_data_file_declares_alone(): void
    {
        // An FA Cup-shaped field: league clubs join at round 3 because their
        // teams.json says so, not because they are in a supercup.
        $leagueClubs = $this->teams(44);
        $this->seedRounds('ESPCUP', $leagueClubs, 3);
        $this->seedRounds('ESPCUP', $this->teams(80), 1);
        $this->seedRounds('ESPSUP', $this->teams(4), 1);

        $this->migration()->up();

        $this->assertSame(44, $this->countAtRound('ESPCUP', 3));
        $this->assertSame(80, $this->countAtRound('ESPCUP', 1));
    }

    public function test_it_matches_the_supercup_field_of_the_same_season(): void
    {
        // A club bumped in 2025 that only reached the supercup in 2026 keeps
        // its 2025 round: the old seeder could not have written it there.
        $club = $this->teams(1);
        $this->seedRounds('ESPSUP', $club, 1, '2026');
        $this->seedRounds('ESPCUP', $club, 3, '2025');

        $this->migration()->up();

        $this->assertSame(3, $this->countAtRound('ESPCUP', 3, '2025'));
    }

    private function migration(): Migration
    {
        return require database_path(
            'migrations/2026_09_05_000002_reset_baked_in_supercup_cup_entry_rounds.php'
        );
    }

    /**
     * @return Team[]
     */
    private function teams(int $count): array
    {
        $teams = [];
        for ($i = 0; $i < $count; $i++) {
            $teams[] = Team::factory()->create(['country' => 'ES']);
        }

        return $teams;
    }

    /**
     * The round a cup's data file gave each club, as the seeder records it.
     *
     * @param  Team[]  $teams
     */
    private function seedRounds(string $competitionId, array $teams, int $entryRound, string $season = '2025'): void
    {
        DB::table('competition_teams')->insert(array_map(fn (Team $team) => [
            'competition_id' => $competitionId,
            'team_id' => $team->id,
            'season' => $season,
            'entry_round' => $entryRound,
        ], $teams));
    }

    private function countAtRound(string $competitionId, int $entryRound, string $season = '2025'): int
    {
        return DB::table('competition_teams')
            ->where('competition_id', $competitionId)
            ->where('season', $season)
            ->where('entry_round', $entryRound)
            ->count();
    }
}
