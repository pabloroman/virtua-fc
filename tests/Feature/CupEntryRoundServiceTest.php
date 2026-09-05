<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\Game;
use App\Models\Team;
use App\Models\User;
use App\Modules\Competition\Services\CupEntryRoundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * CupEntryRoundService decides the round every club joins a domestic cup at
 * before the first draw: the round each club's data file declared, then the
 * supercup skip-ahead.
 *
 * Round counts come from the real data/2025 schedules: ESPCUP has seven
 * rounds, ESPSUP two. Rules are driven through config overrides and seeded
 * rounds so the tests describe the abstraction rather than Spain.
 */
class CupEntryRoundServiceTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        Competition::factory()->league()->create(['id' => 'ESP1', 'country' => 'ES', 'tier' => 1]);
        Competition::factory()->league()->create(['id' => 'ESP2', 'country' => 'ES', 'tier' => 2]);
        Competition::factory()->knockoutCup()->create(['id' => 'ESPCUP', 'country' => 'ES']);
        Competition::factory()->knockoutCup()->create(['id' => 'ESPSUP', 'country' => 'ES']);
        $user = User::factory()->create();
        $userTeam = Team::factory()->create(['country' => 'ES']);
        $this->game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $userTeam->id,
            'competition_id' => 'ESP1',
            'country' => 'ES',
            'season' => '2025',
            'base_season' => '2025',
        ]);
    }

    public function test_supercup_field_skips_ahead_to_the_configured_round(): void
    {
        $topFlight = $this->teamsIn('ESP1', 20);
        $ghosts = $this->ghosts(96);
        $this->enter('ESPCUP', [...$topFlight, ...$ghosts]);
        $supercup = array_slice($topFlight, 0, 4);
        $this->enter('ESPSUP', $supercup);

        $this->service()->assignEntryRounds($this->game->id, 'ES');

        $rounds = $this->rounds('ESPCUP');
        foreach ($supercup as $team) {
            $this->assertSame(3, $rounds[$team->id], 'supercup club joins at the round of 32');
        }
        $this->assertSame(112, $this->countAtRound('ESPCUP', 1));
        $this->assertSame(4, $this->countAtRound('ESPCUP', 3));
    }

    public function test_supercup_club_missing_from_the_cup_is_inserted_at_its_round(): void
    {
        $topFlight = $this->teamsIn('ESP1', 20);
        $ghosts = $this->ghosts(96);
        $supercup = array_slice($topFlight, 0, 4);
        // The fourth supercup club is not in the cup field at all.
        $this->enter('ESPCUP', [...array_slice($topFlight, 1), ...$ghosts]);
        $this->enter('ESPSUP', $supercup);

        $this->service()->assignEntryRounds($this->game->id, 'ES');

        $this->assertSame(3, $this->rounds('ESPCUP')[$supercup[0]->id]);
        $this->assertSame(116, CompetitionEntry::where('game_id', $this->game->id)->where('competition_id', 'ESPCUP')->count());
        $this->assertSame(4, $this->countAtRound('ESPCUP', 3));
    }

    public function test_ghosts_are_restored_to_their_seeded_round_each_season(): void
    {
        config(['countries.ES.supercup' => null]);

        $teams = $this->ghosts(4);
        $this->enter('ESPSUP', $teams);
        // A previous season had left one ghost at round 2; its data file
        // says round 1, so that is where it goes back to.
        $this->seedRounds('ESPSUP', $teams, 1);
        CompetitionEntry::where('game_id', $this->game->id)
            ->where('team_id', $teams[0]->id)
            ->update(['entry_round' => 2]);

        $this->service()->assignEntryRounds($this->game->id, 'ES');

        $this->assertSame([1, 1, 1, 1], array_values($this->rounds('ESPSUP')));
    }

    /**
     * The FA Cup shape — lower-league clubs in round one, league clubs at
     * round three — is a property of the cup's teams.json, not of engine
     * code. League clubs get their declared round just like ghosts do.
     */
    public function test_a_cups_shape_comes_from_the_declared_entry_rounds(): void
    {
        config(['countries.ES.supercup' => null]);

        $topFlight = $this->teamsIn('ESP1', 20);
        $ghosts = $this->ghosts(96);
        $this->enter('ESPCUP', [...$topFlight, ...$ghosts]);
        $this->seedRounds('ESPCUP', $topFlight, 3);
        $this->seedRounds('ESPCUP', $ghosts, 1);

        $this->service()->assignEntryRounds($this->game->id, 'ES');

        $rounds = $this->rounds('ESPCUP');
        foreach ($topFlight as $team) {
            $this->assertSame(3, $rounds[$team->id], 'league clubs join at the round their data declares');
        }
        $this->assertSame(96, $this->countAtRound('ESPCUP', 1));
        $this->assertSame(20, $this->countAtRound('ESPCUP', 3));
    }

    public function test_a_declared_round_beyond_the_final_is_clamped_to_it(): void
    {
        config(['countries.ES.supercup' => null]);

        $teams = $this->ghosts(2);
        $this->enter('ESPSUP', $teams);
        $this->seedRounds('ESPSUP', $teams, 9);

        $this->service()->assignEntryRounds($this->game->id, 'ES');

        // ESPSUP has two rounds; nothing may be parked past the final.
        $this->assertSame([2, 2], array_values($this->rounds('ESPSUP')));
    }

    public function test_reserve_teams_in_the_supercup_are_never_inserted_into_the_cup(): void
    {
        $topFlight = $this->teamsIn('ESP1', 20);
        $reserve = Team::factory()->create(['country' => 'ES', 'parent_team_id' => $topFlight[0]->id]);
        $ghosts = $this->ghosts(96);
        $this->enter('ESPCUP', [...$topFlight, ...$ghosts]);
        $this->enter('ESPSUP', [...array_slice($topFlight, 0, 3), $reserve]);

        $this->service()->assignEntryRounds($this->game->id, 'ES');

        $this->assertArrayNotHasKey($reserve->id, $this->rounds('ESPCUP'));
        $this->assertSame(3, $this->countAtRound('ESPCUP', 3));
        $this->assertSame(113, $this->countAtRound('ESPCUP', 1));
    }

    /**
     * Declare the entry round a cup's data file gave each club.
     *
     * @param  Team[]  $teams
     */
    private function seedRounds(string $competitionId, array $teams, int $entryRound): void
    {
        DB::table('competition_teams')->insert(array_map(fn (Team $team) => [
            'competition_id' => $competitionId,
            'team_id' => $team->id,
            'season' => '2025',
            'entry_round' => $entryRound,
        ], $teams));
    }

    private function service(): CupEntryRoundService
    {
        return app(CupEntryRoundService::class);
    }

    /**
     * @return Team[]
     */
    private function teamsIn(string $competitionId, int $count): array
    {
        $teams = [];
        for ($i = 0; $i < $count; $i++) {
            $teams[] = Team::factory()->create(['country' => 'ES']);
        }
        $this->enter($competitionId, $teams);

        return $teams;
    }

    /**
     * Clubs with no league entry — cup-only ghosts.
     *
     * @return Team[]
     */
    private function ghosts(int $count): array
    {
        $teams = [];
        for ($i = 0; $i < $count; $i++) {
            $teams[] = Team::factory()->create(['country' => 'ES']);
        }

        return $teams;
    }

    /**
     * @param  Team[]  $teams
     */
    private function enter(string $competitionId, array $teams, int $entryRound = 1): void
    {
        CompetitionEntry::insert(array_map(fn (Team $team) => [
            'game_id' => $this->game->id,
            'competition_id' => $competitionId,
            'team_id' => $team->id,
            'entry_round' => $entryRound,
        ], $teams));
    }

    /**
     * @return array<string, int>  team id => entry round
     */
    private function rounds(string $competitionId): array
    {
        return CompetitionEntry::where('game_id', $this->game->id)
            ->where('competition_id', $competitionId)
            ->pluck('entry_round', 'team_id')
            ->map(fn ($round) => (int) $round)
            ->all();
    }

    private function countAtRound(string $competitionId, int $round): int
    {
        return CompetitionEntry::where('game_id', $this->game->id)
            ->where('competition_id', $competitionId)
            ->where('entry_round', $round)
            ->count();
    }
}
