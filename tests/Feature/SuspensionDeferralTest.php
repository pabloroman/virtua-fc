<?php

namespace Tests\Feature;

use App\Http\Actions\AdvanceMatchday;
use App\Modules\Match\Services\MatchFinalizationService;
use App\Models\Competition;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\GameStanding;
use App\Models\Player;
use App\Models\PlayerSuspension;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuspensionDeferralTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Team $playerTeam;
    private Team $opponentTeam;
    private Competition $competition;
    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $this->playerTeam = Team::factory()->create(['name' => 'Player Team']);
        $this->opponentTeam = Team::factory()->create(['name' => 'Opponent Team']);

        $this->competition = Competition::factory()->league()->create([
            'id' => 'ESP1',
            'name' => 'LaLiga',
        ]);

        $this->playerTeam->competitions()->attach($this->competition->id, ['season' => '2024']);
        $this->opponentTeam->competitions()->attach($this->competition->id, ['season' => '2024']);

        $this->game = Game::factory()->create([
            'user_id' => $this->user->id,
            'team_id' => $this->playerTeam->id,
            'competition_id' => $this->competition->id,
            'season' => '2024',
            'current_date' => '2024-08-15',
            'current_matchday' => 0,
        ]);
    }

    public function test_suspended_player_suspension_not_served_before_finalization(): void
    {
        // Create players for both teams (11 per team minimum for lineup)
        $this->createSquad($this->playerTeam);
        $this->createSquad($this->opponentTeam);

        // Create a suspended bench player on the user's team
        $suspendedPlayer = GamePlayer::factory()
            ->forGame($this->game)
            ->forTeam($this->playerTeam)
            ->create(['position' => 'Right Winger']);

        PlayerSuspension::create([
            'game_player_id' => $suspendedPlayer->id,
            'competition_id' => $this->competition->id,
            'matches_remaining' => 1,
            'yellow_cards' => 5,
        ]);

        // Create match and standings
        GameMatch::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => $this->competition->id,
            'round_number' => 1,
            'home_team_id' => $this->playerTeam->id,
            'away_team_id' => $this->opponentTeam->id,
            'scheduled_date' => Carbon::parse('2024-08-16'),
        ]);

        foreach ([$this->playerTeam, $this->opponentTeam] as $team) {
            GameStanding::create([
                'game_id' => $this->game->id,
                'competition_id' => $this->competition->id,
                'team_id' => $team->id,
                'position' => 0,
                'played' => 0,
                'won' => 0,
                'drawn' => 0,
                'lost' => 0,
                'goals_for' => 0,
                'goals_against' => 0,
                'points' => 0,
            ]);
        }

        // Advance matchday — match is simulated but finalization is deferred
        $action = app(AdvanceMatchday::class);
        $action($this->game->id);

        // BEFORE finalization: suspension should NOT be served yet
        $suspension = PlayerSuspension::where('game_player_id', $suspendedPlayer->id)
            ->where('competition_id', $this->competition->id)
            ->first();

        $this->assertNotNull($suspension);
        $this->assertEquals(
            1,
            $suspension->matches_remaining,
            'Suspension should NOT be served before match finalization — the player must remain ineligible during the live match'
        );

        // Finalize the match
        $this->game->refresh();
        $match = GameMatch::find($this->game->pending_finalization_match_id);
        app(MatchFinalizationService::class)->finalize($match, $this->game);

        // AFTER finalization: suspension should now be served
        $suspension->refresh();
        $this->assertEquals(
            0,
            $suspension->matches_remaining,
            'Suspension should be served after match finalization'
        );
    }

    public function test_suspended_player_excluded_from_bench_during_live_match(): void
    {
        // Create players for both teams
        $this->createSquad($this->playerTeam);
        $this->createSquad($this->opponentTeam);

        // Create a suspended bench player on the user's team
        $suspendedPlayer = GamePlayer::factory()
            ->forGame($this->game)
            ->forTeam($this->playerTeam)
            ->create(['position' => 'Right Winger']);

        PlayerSuspension::create([
            'game_player_id' => $suspendedPlayer->id,
            'competition_id' => $this->competition->id,
            'matches_remaining' => 1,
            'yellow_cards' => 5,
        ]);

        // Create match and standings
        GameMatch::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => $this->competition->id,
            'round_number' => 1,
            'home_team_id' => $this->playerTeam->id,
            'away_team_id' => $this->opponentTeam->id,
            'scheduled_date' => Carbon::parse('2024-08-16'),
        ]);

        foreach ([$this->playerTeam, $this->opponentTeam] as $team) {
            GameStanding::create([
                'game_id' => $this->game->id,
                'competition_id' => $this->competition->id,
                'team_id' => $team->id,
                'position' => 0,
                'played' => 0,
                'won' => 0,
                'drawn' => 0,
                'lost' => 0,
                'goals_for' => 0,
                'goals_against' => 0,
                'points' => 0,
            ]);
        }

        // Advance matchday
        $action = app(AdvanceMatchday::class);
        $action($this->game->id);
        $this->game->refresh();

        // Query bench players the same way ShowLiveMatch does
        $playerMatch = GameMatch::find($this->game->pending_finalization_match_id);
        $userLineupIds = $playerMatch->home_lineup ?? [];

        $suspendedPlayerIds = PlayerSuspension::where('competition_id', $playerMatch->competition_id)
            ->where('matches_remaining', '>', 0)
            ->pluck('game_player_id')
            ->toArray();

        $benchPlayerIds = GamePlayer::where('game_id', $this->game->id)
            ->where('team_id', $this->game->team_id)
            ->whereNotIn('id', $userLineupIds)
            ->whereNotIn('id', $suspendedPlayerIds)
            ->pluck('id')
            ->toArray();

        $this->assertNotContains(
            $suspendedPlayer->id,
            $benchPlayerIds,
            'Suspended player should NOT appear in the bench during the live match'
        );
    }

    /**
     * Create a minimal 11-player squad for a team (1 GK + 10 outfield).
     */
    private function createSquad(Team $team): void
    {
        // Goalkeeper
        GamePlayer::factory()
            ->forGame($this->game)
            ->forTeam($team)
            ->goalkeeper()
            ->create();

        // 4 Defenders
        foreach (['Centre-Back', 'Centre-Back', 'Left-Back', 'Right-Back'] as $position) {
            GamePlayer::factory()
                ->forGame($this->game)
                ->forTeam($team)
                ->create(['position' => $position]);
        }

        // 4 Midfielders
        foreach (['Central Midfield', 'Central Midfield', 'Central Midfield', 'Central Midfield'] as $position) {
            GamePlayer::factory()
                ->forGame($this->game)
                ->forTeam($team)
                ->create(['position' => $position]);
        }

        // 2 Forwards
        foreach (['Centre-Forward', 'Centre-Forward'] as $position) {
            GamePlayer::factory()
                ->forGame($this->game)
                ->forTeam($team)
                ->create(['position' => $position]);
        }
    }
}
