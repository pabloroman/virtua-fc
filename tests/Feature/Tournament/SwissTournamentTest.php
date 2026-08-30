<?php

namespace Tests\Feature\Tournament;

use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GameStanding;
use App\Models\Team;
use App\Models\User;
use App\Modules\Competition\Configs\WorldCupSwissConfig;
use App\Modules\Competition\Services\StandingsCalculator;
use App\Modules\Competition\Services\SwissDrawService;
use App\Modules\Competition\Services\SwissKnockoutGenerator;
use App\Modules\Lineup\Services\FormationBiasResolver;
use App\Modules\Lineup\Services\FormationRecommender;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Season\Jobs\SetupTournamentGame;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SwissTournamentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<int, array{id: string, pot: int, country: string}>
     */
    private function swissTeamsJson(): array
    {
        $data = json_decode(file_get_contents(base_path('data/2025/WCSWISS/teams.json')), true);
        return $data['clubs'];
    }

    private function seedNationalTeams(): void
    {
        foreach ($this->swissTeamsJson() as $club) {
            Team::factory()->create([
                'type' => 'national',
                'fifa_code' => $club['id'],
                'is_placeholder' => false,
            ]);
        }
    }

    private function seedCompetition(): Competition
    {
        $competition = Competition::factory()->create([
            'id' => 'WCSWISS',
            'name' => 'game.wcswiss_name',
            'handler_type' => 'swiss_format',
            'season' => '2025',
        ]);

        foreach (Team::worldCupEligible()->pluck('id') as $teamId) {
            DB::table('competition_teams')->insert([
                'competition_id' => 'WCSWISS',
                'team_id' => $teamId,
                'season' => '2025',
            ]);
        }

        return $competition;
    }

    public function test_setup_builds_a_48_team_swiss_league_phase(): void
    {
        $this->seedNationalTeams();
        $this->seedCompetition();

        $user = User::factory()->create();
        $userTeam = Team::worldCupEligible()->first();

        $game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $userTeam->id,
            'competition_id' => 'WCSWISS',
            'game_mode' => Game::MODE_TOURNAMENT,
            'season' => '2025',
            'current_date' => '2026-06-11',
        ]);

        (new SetupTournamentGame($game->id, $userTeam->id, 'WCSWISS'))->handle(
            app(NotificationService::class),
            app(FormationRecommender::class),
            app(FormationBiasResolver::class),
            app(SwissDrawService::class),
            app(StandingsCalculator::class),
        );

        // 48 entries, 192 league fixtures, 48 flat standings rows.
        $this->assertSame(48, CompetitionEntry::where('game_id', $game->id)->count());
        $this->assertSame(48, GameStanding::where('game_id', $game->id)->count());
        $this->assertSame(0, GameStanding::where('game_id', $game->id)->whereNotNull('group_label')->count());

        $fixtures = GameMatch::where('game_id', $game->id)->where('competition_id', 'WCSWISS')->get();
        $this->assertCount(192, $fixtures);
        $this->assertTrue($fixtures->every(fn ($m) => !$m->played && $m->cup_tie_id === null));

        // Every nation plays exactly 8 league matches.
        $perTeam = [];
        foreach ($fixtures as $match) {
            $perTeam[$match->home_team_id] = ($perTeam[$match->home_team_id] ?? 0) + 1;
            $perTeam[$match->away_team_id] = ($perTeam[$match->away_team_id] ?? 0) + 1;
        }
        $this->assertCount(48, $perTeam);
        foreach ($perTeam as $count) {
            $this->assertSame(8, $count);
        }
    }

    public function test_wcswiss_resolves_to_world_cup_swiss_config(): void
    {
        $competition = Competition::factory()->create([
            'id' => 'WCSWISS',
            'handler_type' => 'swiss_format',
            'season' => '2025',
        ]);

        $this->assertInstanceOf(WorldCupSwissConfig::class, $competition->getConfig());
    }

    public function test_knockout_seeds_top_24_and_ignores_eliminated_nations(): void
    {
        $competition = Competition::factory()->create([
            'id' => 'WCSWISS',
            'handler_type' => 'swiss_format',
            'season' => '2025',
        ]);

        $user = User::factory()->create();
        $game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => Team::factory()->create()->id,
            'competition_id' => 'WCSWISS',
            'game_mode' => Game::MODE_TOURNAMENT,
            'season' => '2025',
        ]);

        // Seed a full 48-team final league table.
        $teamsByPosition = [];
        for ($pos = 1; $pos <= 48; $pos++) {
            $team = Team::factory()->create(['type' => 'national', 'fifa_code' => "T{$pos}"]);
            $teamsByPosition[$pos] = $team->id;
            GameStanding::create([
                'game_id' => $game->id,
                'competition_id' => 'WCSWISS',
                'team_id' => $team->id,
                'position' => $pos,
                'played' => 8,
                'won' => 0,
                'drawn' => 0,
                'lost' => 0,
                'goals_for' => 0,
                'goals_against' => 0,
                'points' => max(0, 48 - $pos),
            ]);
        }

        $generator = app(SwissKnockoutGenerator::class);

        // Round 1 (playoff): exactly 8 ties drawn from positions 9-24.
        $playoff = $generator->generateMatchups($game, 'WCSWISS', SwissKnockoutGenerator::ROUND_KNOCKOUT_PLAYOFF);
        $this->assertCount(8, $playoff);

        $playoffTeamIds = collect($playoff)->flatMap(fn ($m) => [$m[0], $m[1]])->all();
        $qualifyingIds = collect(range(9, 24))->map(fn ($p) => $teamsByPosition[$p])->all();
        sort($playoffTeamIds);
        sort($qualifyingIds);
        $this->assertSame($qualifyingIds, $playoffTeamIds, 'Playoff must contain exactly positions 9-24');

        // Eliminated nations (positions 25-48) never appear.
        foreach (range(25, 48) as $pos) {
            $this->assertNotContains($teamsByPosition[$pos], $playoffTeamIds);
        }

        // Persist the playoff winners, then confirm the Round of 16 draws 8 ties
        // (top 8 seeds + 8 playoff winners) — identical shape to the UCL bracket.
        foreach ($playoff as [$home, $away, $bracket]) {
            CupTie::create([
                'game_id' => $game->id,
                'competition_id' => 'WCSWISS',
                'round_number' => SwissKnockoutGenerator::ROUND_KNOCKOUT_PLAYOFF,
                'bracket_position' => $bracket,
                'home_team_id' => $home,
                'away_team_id' => $away,
                'winner_id' => $away,
                'completed' => true,
            ]);
        }

        $r16 = $generator->generateMatchups($game, 'WCSWISS', SwissKnockoutGenerator::ROUND_OF_16);
        $this->assertCount(8, $r16);
    }
}
