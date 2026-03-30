<?php

namespace Tests\Feature;

use App\Http\Actions\AdvanceMatchday;
use App\Modules\Lineup\Services\SubstitutionService;
use App\Modules\Match\Services\MatchFinalizationService;
use App\Models\Competition;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\GameStanding;
use App\Models\MatchEvent;
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

        $this->createStandings();

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

        $this->createStandings();

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

    public function test_red_card_suspension_not_consumed_during_finalization(): void
    {
        // Create squads for both teams
        $this->createSquad($this->playerTeam);
        $this->createSquad($this->opponentTeam);

        // Pick a starter from the user's team who will "receive" a red card
        $redCardPlayer = GamePlayer::where('game_id', $this->game->id)
            ->where('team_id', $this->playerTeam->id)
            ->where('position', 'Centre-Forward')
            ->first();

        // Create match and standings
        $match = GameMatch::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => $this->competition->id,
            'round_number' => 1,
            'home_team_id' => $this->playerTeam->id,
            'away_team_id' => $this->opponentTeam->id,
            'scheduled_date' => Carbon::parse('2024-08-16'),
            'played' => true,
            'home_score' => 1,
            'away_score' => 0,
            'home_lineup' => GamePlayer::where('game_id', $this->game->id)
                ->where('team_id', $this->playerTeam->id)
                ->limit(11)
                ->pluck('id')
                ->toArray(),
            'away_lineup' => GamePlayer::where('game_id', $this->game->id)
                ->where('team_id', $this->opponentTeam->id)
                ->limit(11)
                ->pluck('id')
                ->toArray(),
        ]);

        $this->createStandings();

        // Simulate what processAll does: create a red card event and suspension
        MatchEvent::create([
            'game_id' => $this->game->id,
            'game_match_id' => $match->id,
            'game_player_id' => $redCardPlayer->id,
            'team_id' => $this->playerTeam->id,
            'minute' => 75,
            'event_type' => MatchEvent::TYPE_RED_CARD,
            'metadata' => ['second_yellow' => false],
        ]);

        PlayerSuspension::create([
            'game_player_id' => $redCardPlayer->id,
            'competition_id' => $this->competition->id,
            'matches_remaining' => 1,
            'yellow_cards' => 0,
        ]);

        // Set pending finalization
        $this->game->update(['pending_finalization_match_id' => $match->id]);

        // Finalize the match
        app(MatchFinalizationService::class)->finalize($match, $this->game);

        // The red card suspension should NOT have been consumed — the player
        // must miss the NEXT match, not the one where they received the card
        $suspension = PlayerSuspension::where('game_player_id', $redCardPlayer->id)
            ->where('competition_id', $this->competition->id)
            ->first();

        $this->assertNotNull($suspension);
        $this->assertEquals(
            1,
            $suspension->matches_remaining,
            'Red card suspension from the just-played match should NOT be served during finalization — the ban applies to the next match'
        );

        // Player should be unavailable for the next match
        $this->assertFalse(
            $redCardPlayer->isAvailable(Carbon::parse('2024-08-23'), $this->competition->id),
            'Red-carded player should be unavailable for the next match'
        );
    }

    public function test_preexisting_suspension_still_served_when_other_player_gets_card(): void
    {
        // Create squads for both teams
        $this->createSquad($this->playerTeam);
        $this->createSquad($this->opponentTeam);

        // One player has a pre-existing suspension (sat out this match)
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

        // Another player (starter) gets a red card during the match
        $redCardPlayer = GamePlayer::where('game_id', $this->game->id)
            ->where('team_id', $this->playerTeam->id)
            ->where('position', 'Centre-Forward')
            ->first();

        // Create match (suspendedPlayer is NOT in lineup)
        $lineup = GamePlayer::where('game_id', $this->game->id)
            ->where('team_id', $this->playerTeam->id)
            ->where('id', '!=', $suspendedPlayer->id)
            ->limit(11)
            ->pluck('id')
            ->toArray();

        $match = GameMatch::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => $this->competition->id,
            'round_number' => 1,
            'home_team_id' => $this->playerTeam->id,
            'away_team_id' => $this->opponentTeam->id,
            'scheduled_date' => Carbon::parse('2024-08-16'),
            'played' => true,
            'home_score' => 1,
            'away_score' => 0,
            'home_lineup' => $lineup,
            'away_lineup' => GamePlayer::where('game_id', $this->game->id)
                ->where('team_id', $this->opponentTeam->id)
                ->limit(11)
                ->pluck('id')
                ->toArray(),
        ]);

        $this->createStandings();

        // Red card event and suspension for the starter
        MatchEvent::create([
            'game_id' => $this->game->id,
            'game_match_id' => $match->id,
            'game_player_id' => $redCardPlayer->id,
            'team_id' => $this->playerTeam->id,
            'minute' => 75,
            'event_type' => MatchEvent::TYPE_RED_CARD,
            'metadata' => ['second_yellow' => false],
        ]);

        PlayerSuspension::create([
            'game_player_id' => $redCardPlayer->id,
            'competition_id' => $this->competition->id,
            'matches_remaining' => 1,
            'yellow_cards' => 0,
        ]);

        $this->game->update(['pending_finalization_match_id' => $match->id]);
        app(MatchFinalizationService::class)->finalize($match, $this->game);

        // Pre-existing suspension SHOULD be served (player sat out the match)
        $existingSuspension = PlayerSuspension::where('game_player_id', $suspendedPlayer->id)
            ->where('competition_id', $this->competition->id)
            ->first();
        $this->assertEquals(
            0,
            $existingSuspension->matches_remaining,
            'Pre-existing suspension should be served during finalization'
        );

        // New red card suspension should NOT be served
        $newSuspension = PlayerSuspension::where('game_player_id', $redCardPlayer->id)
            ->where('competition_id', $this->competition->id)
            ->first();
        $this->assertEquals(
            1,
            $newSuspension->matches_remaining,
            'New red card suspension should NOT be served — it applies to the next match'
        );
    }

    public function test_suspended_player_excluded_from_resimulation_bench(): void
    {
        // Create squads for both teams
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

        // Build a lineup from non-suspended players (11 starters)
        $lineup = GamePlayer::where('game_id', $this->game->id)
            ->where('team_id', $this->playerTeam->id)
            ->where('id', '!=', $suspendedPlayer->id)
            ->limit(11)
            ->pluck('id')
            ->toArray();

        $opponentLineup = GamePlayer::where('game_id', $this->game->id)
            ->where('team_id', $this->opponentTeam->id)
            ->limit(11)
            ->pluck('id')
            ->toArray();

        $match = GameMatch::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => $this->competition->id,
            'round_number' => 1,
            'home_team_id' => $this->playerTeam->id,
            'away_team_id' => $this->opponentTeam->id,
            'scheduled_date' => Carbon::parse('2024-08-16'),
            'played' => true,
            'home_score' => 1,
            'away_score' => 0,
            'home_lineup' => $lineup,
            'away_lineup' => $opponentLineup,
        ]);

        // Build the user lineup collection as loadTeamsForResimulation expects
        $userLineup = GamePlayer::with('player')->whereIn('id', $lineup)->get();

        $service = app(SubstitutionService::class);
        $result = $service->loadTeamsForResimulation($match, $this->game, $userLineup, []);

        // Suspended player should NOT be in the user's bench
        $homeBenchIds = $result['homeBench']->pluck('id')->toArray();
        $this->assertNotContains(
            $suspendedPlayer->id,
            $homeBenchIds,
            'Suspended player should NOT appear in the resimulation bench'
        );
    }

    public function test_cross_competition_suspension_not_served_when_team_plays_different_competition(): void
    {
        // Setup: two competitions on the same date
        $copa = Competition::factory()->knockoutCup()->create([
            'id' => 'ESPCUP',
            'name' => 'Copa del Rey',
        ]);

        $this->createSquad($this->playerTeam);
        $this->createSquad($this->opponentTeam);

        $aiTeamA = Team::factory()->create(['name' => 'AI Team A']);
        $aiTeamB = Team::factory()->create(['name' => 'AI Team B']);
        $this->createSquad($aiTeamA);
        $this->createSquad($aiTeamB);

        // AI Team A has a Copa suspension
        $suspendedPlayer = GamePlayer::where('game_id', $this->game->id)
            ->where('team_id', $aiTeamA->id)
            ->where('position', 'Centre-Forward')
            ->first();

        PlayerSuspension::create([
            'game_player_id' => $suspendedPlayer->id,
            'competition_id' => $copa->id,
            'matches_remaining' => 1,
            'yellow_cards' => 3,
        ]);

        // La Liga match: player's team vs AI Team A
        GameMatch::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => $this->competition->id,
            'round_number' => 1,
            'home_team_id' => $this->playerTeam->id,
            'away_team_id' => $aiTeamA->id,
            'scheduled_date' => Carbon::parse('2024-08-16'),
        ]);

        // Copa match on the same date: AI Team B vs opponent (AI Team A NOT playing Copa)
        $aiTeamB->competitions()->attach($copa->id, ['season' => '2024']);
        $this->opponentTeam->competitions()->attach($copa->id, ['season' => '2024']);

        GameMatch::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => $copa->id,
            'round_number' => 1,
            'home_team_id' => $aiTeamB->id,
            'away_team_id' => $this->opponentTeam->id,
            'scheduled_date' => Carbon::parse('2024-08-16'),
        ]);

        $this->createStandings();

        // Advance matchday
        $action = app(AdvanceMatchday::class);
        $action($this->game->id);

        // Finalize
        $this->game->refresh();
        if ($this->game->pending_finalization_match_id) {
            $match = GameMatch::find($this->game->pending_finalization_match_id);
            app(MatchFinalizationService::class)->finalize($match, $this->game);
        }

        // AI Team A's Copa suspension should NOT have been served
        // (they played La Liga, not Copa)
        $suspension = PlayerSuspension::where('game_player_id', $suspendedPlayer->id)
            ->where('competition_id', $copa->id)
            ->first();

        $this->assertNotNull($suspension);
        $this->assertEquals(
            1,
            $suspension->matches_remaining,
            'Copa suspension should NOT be served when the team only played a La Liga match'
        );
    }

    public function test_same_competition_suspension_is_served(): void
    {
        $this->createSquad($this->playerTeam);
        $this->createSquad($this->opponentTeam);

        // Player team has a La Liga suspension
        $suspendedPlayer = GamePlayer::factory()
            ->forGame($this->game)
            ->forTeam($this->opponentTeam)
            ->create(['position' => 'Right Winger']);

        PlayerSuspension::create([
            'game_player_id' => $suspendedPlayer->id,
            'competition_id' => $this->competition->id,
            'matches_remaining' => 1,
            'yellow_cards' => 5,
        ]);

        // La Liga match
        GameMatch::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => $this->competition->id,
            'round_number' => 1,
            'home_team_id' => $this->playerTeam->id,
            'away_team_id' => $this->opponentTeam->id,
            'scheduled_date' => Carbon::parse('2024-08-16'),
        ]);

        $this->createStandings();

        // Advance matchday
        $action = app(AdvanceMatchday::class);
        $action($this->game->id);

        // Finalize
        $this->game->refresh();
        $match = GameMatch::find($this->game->pending_finalization_match_id);
        app(MatchFinalizationService::class)->finalize($match, $this->game);

        // La Liga suspension SHOULD be served (team played La Liga)
        $suspension = PlayerSuspension::where('game_player_id', $suspendedPlayer->id)
            ->where('competition_id', $this->competition->id)
            ->first();

        $this->assertNotNull($suspension);
        $this->assertEquals(
            0,
            $suspension->matches_remaining,
            'La Liga suspension should be served when team played a La Liga match'
        );
    }

    private function createStandings(): void
    {
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
        GamePlayer::factory()
            ->forGame($this->game)
            ->forTeam($team)
            ->count(4)
            ->create(['position' => 'Central Midfield']);

        // 2 Forwards
        foreach (['Centre-Forward', 'Centre-Forward'] as $position) {
            GamePlayer::factory()
                ->forGame($this->game)
                ->forTeam($team)
                ->create(['position' => $position]);
        }
    }
}
