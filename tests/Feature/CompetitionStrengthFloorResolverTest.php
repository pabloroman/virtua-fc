<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Modules\Match\Services\CompetitionStrengthFloorResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompetitionStrengthFloorResolverTest extends TestCase
{
    use RefreshDatabase;

    private function makeGame(): Game
    {
        $userTeam = Team::factory()->create();

        return Game::factory()->create([
            'team_id' => $userTeam->id,
            'season' => '2025',
        ]);
    }

    /**
     * Add a team to a competition with 11 players all at the given overall, so
     * the team's top-11 mean rating equals that overall exactly.
     */
    private function addTeam(Game $game, string $competitionId, int $overall): Team
    {
        $team = Team::factory()->create();
        CompetitionEntry::create([
            'game_id' => $game->id,
            'competition_id' => $competitionId,
            'team_id' => $team->id,
        ]);
        GamePlayer::factory()->forGame($game)->forTeam($team)->count(11)->create([
            'overall_score' => $overall,
            'position' => 'Central Midfield',
        ]);

        return $team;
    }

    private function matchIn(string $competitionId): GameMatch
    {
        $match = new GameMatch;
        $match->competition_id = $competitionId;

        return $match;
    }

    public function test_league_floor_matches_the_ratio_target_formula(): void
    {
        config(['match_simulation.strength_ratio_target' => 1.34]);
        $game = $this->makeGame();
        Competition::factory()->league()->create(['id' => 'TSTA', 'season' => '2025']);

        foreach ([70, 74, 78, 82, 86, 90] as $overall) {
            $this->addTeam($game, 'TSTA', $overall);
        }

        // F = (R*bottom - top)/(R-1) = (1.34*70 - 90)/0.34 ≈ 11.18
        $floor = (new CompetitionStrengthFloorResolver)->leagueFloor($game, 'TSTA');

        $this->assertEqualsWithDelta(11.18, $floor, 0.3);
    }

    public function test_fewer_than_minimum_teams_disables_the_floor(): void
    {
        $game = $this->makeGame();
        Competition::factory()->league()->create(['id' => 'TSTA', 'season' => '2025']);

        foreach ([70, 78, 84, 90] as $overall) { // only 4 teams (< MIN_TEAMS)
            $this->addTeam($game, 'TSTA', $overall);
        }

        $this->assertSame(0.0, (new CompetitionStrengthFloorResolver)->leagueFloor($game, 'TSTA'));
    }

    public function test_cup_match_uses_global_floor_below_a_high_narrow_league_floor(): void
    {
        config(['match_simulation.strength_ratio_target' => 1.34]);
        $game = $this->makeGame();

        // High, narrow domestic league → a high floor.
        Competition::factory()->league()->create(['id' => 'TSTA', 'season' => '2025']);
        foreach ([82, 84, 86, 88, 89, 90] as $overall) {
            $this->addTeam($game, 'TSTA', $overall);
        }
        // A second, much weaker domestic league widens the global pool.
        Competition::factory()->league()->create(['id' => 'TSTB', 'season' => '2025']);
        foreach ([60, 62, 64, 66, 68, 70] as $overall) {
            $this->addTeam($game, 'TSTB', $overall);
        }
        // A domestic cup.
        Competition::factory()->knockoutCup()->create(['id' => 'TSTCUP', 'season' => '2025']);

        $resolver = new CompetitionStrengthFloorResolver;
        $leagueFloor = $resolver->floorForMatch($game, $this->matchIn('TSTA'));
        $cupFloor = $resolver->floorForMatch($game, $this->matchIn('TSTCUP'));

        $this->assertGreaterThan(40.0, $leagueFloor, 'high narrow league should get a large floor');
        $this->assertSame($resolver->leagueFloor($game, 'TSTA'), $leagueFloor, 'league match uses its own league floor');
        $this->assertLessThan($leagueFloor, $cupFloor, 'cup match uses the lower global cross-band floor');
        $this->assertSame($resolver->globalFloor($game), $cupFloor);
    }

    public function test_disabled_flag_returns_zero(): void
    {
        config(['match_simulation.strength_floor_enabled' => false]);
        $game = $this->makeGame();
        Competition::factory()->league()->create(['id' => 'TSTA', 'season' => '2025']);
        foreach ([70, 74, 78, 82, 86, 90] as $overall) {
            $this->addTeam($game, 'TSTA', $overall);
        }

        $this->assertSame(0.0, (new CompetitionStrengthFloorResolver)->floorForMatch($game, $this->matchIn('TSTA')));
    }

    public function test_is_domestic_league_classification(): void
    {
        $league = new Competition([
            'handler_type' => 'league',
            'role' => Competition::ROLE_LEAGUE,
            'scope' => Competition::SCOPE_DOMESTIC,
        ]);
        $this->assertTrue($league->isDomesticLeague());

        $cup = new Competition([
            'handler_type' => 'knockout_cup',
            'role' => Competition::ROLE_DOMESTIC_CUP,
            'scope' => Competition::SCOPE_DOMESTIC,
        ]);
        $this->assertFalse($cup->isDomesticLeague());

        // Continental Swiss is a "league" handler but NOT a domestic league.
        $continental = new Competition([
            'handler_type' => 'swiss_format',
            'role' => Competition::ROLE_EUROPEAN,
            'scope' => Competition::SCOPE_CONTINENTAL,
        ]);
        $this->assertFalse($continental->isDomesticLeague());
    }
}
