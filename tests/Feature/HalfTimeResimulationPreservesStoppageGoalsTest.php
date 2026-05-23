<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\MatchEvent;
use App\Models\Team;
use App\Models\User;
use App\Modules\Match\Enums\MatchPhase;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression test for the "vanishing 45+2 goal" bug.
 *
 * When the live clock crosses into half-time, enterHalfTime() in the
 * Alpine component snaps state.currentMinute back to 45 — destroying the
 * absolute clock value that 1H-stoppage events were revealed at. If the
 * user then submits a tactical action at the break (an extremely common
 * pattern), the frontend POSTs minute=45 and the backend would revert
 * every event whose absolute minute is > 45, deleting any goal stored
 * with phase=FIRST_HALF_STOPPAGE from the DB.
 *
 * The fix lifts the resim anchor at minute=45 to 45 + first_half_stoppage,
 * mirroring the existing 90-anchor that protects 2H stoppage events.
 */
class HalfTimeResimulationPreservesStoppageGoalsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Team $playerTeam;
    private Team $opponentTeam;
    private Competition $competition;
    private Game $game;
    private GameMatch $match;

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
        ]);

        $this->createSquad($this->playerTeam, 18);
        $this->createSquad($this->opponentTeam, 18);

        $homeLineup = GamePlayer::where('game_id', $this->game->id)
            ->where('team_id', $this->playerTeam->id)
            ->limit(11)
            ->pluck('id')
            ->toArray();

        $awayLineup = GamePlayer::where('game_id', $this->game->id)
            ->where('team_id', $this->opponentTeam->id)
            ->limit(11)
            ->pluck('id')
            ->toArray();

        $this->match = GameMatch::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => $this->competition->id,
            'round_number' => 1,
            'home_team_id' => $this->playerTeam->id,
            'away_team_id' => $this->opponentTeam->id,
            'scheduled_date' => Carbon::parse('2024-08-16'),
            'played' => true,
            'home_score' => 1,
            'away_score' => 0,
            'home_lineup' => $homeLineup,
            'away_lineup' => $awayLineup,
            'home_possession' => 55,
            'away_possession' => 45,
            'substitutions' => [],
            'first_half_stoppage' => 2,
            'second_half_stoppage' => 3,
        ]);

        $this->game->update(['pending_finalization_match_id' => $this->match->id]);
    }

    public function test_first_half_stoppage_goal_survives_half_time_tactical_change(): void
    {
        $scorerId = $this->match->home_lineup[9]; // a forward

        $goalEvent = MatchEvent::create([
            'game_id' => $this->game->id,
            'game_match_id' => $this->match->id,
            'game_player_id' => $scorerId,
            'team_id' => $this->playerTeam->id,
            'minute' => 45,
            'phase' => MatchPhase::FIRST_HALF_STOPPAGE,
            'stoppage_minute' => 2,
            'event_type' => MatchEvent::TYPE_GOAL,
        ]);

        $this->actingAs($this->user);

        // Half-time tactical action: the frontend's live clock has been
        // snapped back to 45 by enterHalfTime, so it POSTs minute=45 even
        // though the user already watched the 45+2 goal during stoppage.
        $response = $this->postJson(
            route('game.match.tactical-actions', ['gameId' => $this->game->id, 'matchId' => $this->match->id]),
            [
                'minute' => 45,
                'previousSubstitutions' => [],
                'newSubstitutions' => [],
                'formation' => '4-4-2',
            ],
        );

        $response->assertOk();

        // The goal event must still exist in the DB.
        $this->assertNotNull(
            MatchEvent::find($goalEvent->id),
            'First-half stoppage goal was reverted by the half-time resimulation',
        );

        // And the match's home_score must still reflect it.
        $this->match->refresh();
        $this->assertGreaterThanOrEqual(
            1,
            $this->match->home_score,
            'Home score dropped below 1 after half-time resim — the 45+2 goal was lost',
        );
    }

    public function test_second_half_stoppage_goal_survives_minute_90_tactical_change(): void
    {
        // Pins the symmetric pre-existing safety net at minute=90 so a
        // regression on either anchor surfaces here.
        $scorerId = $this->match->home_lineup[9];

        $goalEvent = MatchEvent::create([
            'game_id' => $this->game->id,
            'game_match_id' => $this->match->id,
            'game_player_id' => $scorerId,
            'team_id' => $this->playerTeam->id,
            'minute' => 90,
            'phase' => MatchPhase::SECOND_HALF_STOPPAGE,
            'stoppage_minute' => 2,
            'event_type' => MatchEvent::TYPE_GOAL,
        ]);

        $this->actingAs($this->user);

        $response = $this->postJson(
            route('game.match.tactical-actions', ['gameId' => $this->game->id, 'matchId' => $this->match->id]),
            [
                'minute' => 90,
                'previousSubstitutions' => [],
                'newSubstitutions' => [],
                'formation' => '4-4-2',
            ],
        );

        $response->assertOk();

        $this->assertNotNull(
            MatchEvent::find($goalEvent->id),
            'Second-half stoppage goal was reverted by the minute-90 resimulation',
        );
    }

    private function createSquad(Team $team, int $size): void
    {
        GamePlayer::factory()->forGame($this->game)->forTeam($team)->goalkeeper()->create();

        foreach (['Centre-Back', 'Centre-Back', 'Left-Back', 'Right-Back'] as $pos) {
            GamePlayer::factory()->forGame($this->game)->forTeam($team)->create(['position' => $pos]);
        }

        GamePlayer::factory()->forGame($this->game)->forTeam($team)->count(4)->create(['position' => 'Central Midfield']);

        foreach (['Centre-Forward', 'Centre-Forward'] as $pos) {
            GamePlayer::factory()->forGame($this->game)->forTeam($team)->create(['position' => $pos]);
        }

        $positionsCycle = ['Centre-Back', 'Left-Back', 'Right-Back', 'Central Midfield', 'Centre-Forward', 'Goalkeeper', 'Right Winger'];
        $bench = $size - 11;
        for ($i = 0; $i < $bench; $i++) {
            GamePlayer::factory()->forGame($this->game)->forTeam($team)->create([
                'position' => $positionsCycle[$i % count($positionsCycle)],
            ]);
        }
    }
}
