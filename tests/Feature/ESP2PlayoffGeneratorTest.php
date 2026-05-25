<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameStanding;
use App\Models\Team;
use App\Models\User;
use App\Modules\Competition\Playoffs\ESP2PlayoffGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Integration tests for {@see ESP2PlayoffGenerator}, focused on the
 * regular-season-position-aware home/away assignment for the playoff final.
 *
 * Spanish rule: in the final between the two semifinal winners, the first
 * leg is hosted by the team that finished lower in the regular season —
 * so the higher finisher's stadium gets the deciding second leg. Bracket
 * order alone can't decide this because either semifinal can be won by
 * its lower seed (e.g. pos 6 beats pos 3).
 */
class ESP2PlayoffGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        Competition::factory()->league()->create([
            'id' => 'ESP2', 'tier' => 2, 'handler_type' => 'league_with_playoff',
        ]);

        $user = User::factory()->create();
        $team = Team::factory()->create();
        $this->game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'competition_id' => 'ESP2',
            'season' => '2025',
        ]);
    }

    public function test_final_lower_finisher_hosts_first_leg_when_both_higher_seeds_win(): void
    {
        $teams = $this->seedEsp2Standings();
        // Bracket 1 (pos 6 v pos 3): pos 3 wins. Bracket 2 (pos 5 v pos 4): pos 4 wins.
        // Final = pos 3 vs pos 4 → pos 4 (lower finisher) hosts leg 1.
        $this->createCompletedSemifinal(1, $teams[6], $teams[3], winner: $teams[3]);
        $this->createCompletedSemifinal(2, $teams[5], $teams[4], winner: $teams[4]);

        $matchups = (new ESP2PlayoffGenerator('ESP2'))->generateMatchups($this->game, 2);

        $this->assertCount(1, $matchups);
        $this->assertEquals($teams[4]->id, $matchups[0][0], 'pos 4 (lower finisher) should host leg 1');
        $this->assertEquals($teams[3]->id, $matchups[0][1]);
    }

    public function test_final_lower_finisher_hosts_first_leg_when_lower_seed_wins_first_bracket(): void
    {
        $teams = $this->seedEsp2Standings();
        // Bracket 1 (pos 6 v pos 3): pos 6 wins. Bracket 2 (pos 5 v pos 4): pos 4 wins.
        // Final = pos 6 vs pos 4 → pos 6 (lower finisher) hosts leg 1.
        $this->createCompletedSemifinal(1, $teams[6], $teams[3], winner: $teams[6]);
        $this->createCompletedSemifinal(2, $teams[5], $teams[4], winner: $teams[4]);

        $matchups = (new ESP2PlayoffGenerator('ESP2'))->generateMatchups($this->game, 2);

        $this->assertEquals($teams[6]->id, $matchups[0][0], 'pos 6 (lower finisher) should host leg 1');
        $this->assertEquals($teams[4]->id, $matchups[0][1]);
    }

    public function test_final_lower_finisher_hosts_first_leg_when_lower_seed_wins_second_bracket(): void
    {
        $teams = $this->seedEsp2Standings();
        // Bracket 1: pos 3 wins. Bracket 2: pos 5 wins. Final = pos 5 vs pos 3 → pos 5 hosts.
        $this->createCompletedSemifinal(1, $teams[6], $teams[3], winner: $teams[3]);
        $this->createCompletedSemifinal(2, $teams[5], $teams[4], winner: $teams[5]);

        $matchups = (new ESP2PlayoffGenerator('ESP2'))->generateMatchups($this->game, 2);

        $this->assertEquals($teams[5]->id, $matchups[0][0], 'pos 5 (lower finisher) should host leg 1');
        $this->assertEquals($teams[3]->id, $matchups[0][1]);
    }

    public function test_final_lower_finisher_hosts_first_leg_when_both_lower_seeds_win(): void
    {
        $teams = $this->seedEsp2Standings();
        // Bracket 1: pos 6 wins. Bracket 2: pos 5 wins. Final = pos 6 vs pos 5 → pos 6 hosts.
        $this->createCompletedSemifinal(1, $teams[6], $teams[3], winner: $teams[6]);
        $this->createCompletedSemifinal(2, $teams[5], $teams[4], winner: $teams[5]);

        $matchups = (new ESP2PlayoffGenerator('ESP2'))->generateMatchups($this->game, 2);

        $this->assertEquals($teams[6]->id, $matchups[0][0], 'pos 6 (lower finisher) should host leg 1');
        $this->assertEquals($teams[5]->id, $matchups[0][1]);
    }

    /**
     * @return array<int, Team> 1-indexed by ESP2 standings position.
     */
    private function seedEsp2Standings(int $count = 22): array
    {
        $teams = [];
        for ($i = 1; $i <= $count; $i++) {
            $team = Team::factory()->create();
            $teams[$i] = $team;
            CompetitionEntry::create([
                'game_id' => $this->game->id,
                'competition_id' => 'ESP2',
                'team_id' => $team->id,
                'entry_round' => 1,
            ]);
            GameStanding::create([
                'game_id' => $this->game->id,
                'competition_id' => 'ESP2',
                'team_id' => $team->id,
                'position' => $i,
                'played' => 42,
                'won' => max(0, 25 - $i),
                'drawn' => 5,
                'lost' => $i,
                'goals_for' => 70 - $i,
                'goals_against' => 20 + $i,
                'points' => max(0, 25 - $i) * 3 + 5,
            ]);
        }
        return $teams;
    }

    private function createCompletedSemifinal(int $bracketPosition, Team $home, Team $away, Team $winner): void
    {
        CupTie::factory()
            ->forGame($this->game)
            ->inRound(1)
            ->between($home, $away)
            ->completed($winner, 'aggregate')
            ->create([
                'competition_id' => 'ESP2',
                'bracket_position' => $bracketPosition,
            ]);
    }
}
