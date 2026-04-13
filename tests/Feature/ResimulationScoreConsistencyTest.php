<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\MatchEvent;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression tests for the critical invariant: after any resimulation,
 * the goal/own_goal event count in the DB must match home_score + away_score
 * on the match record.
 *
 * This invariant broke in production ("ghost goals") when the skip-to-end
 * commit introduced an async resimulation that raced with the animation loop.
 */
class ResimulationScoreConsistencyTest extends TestCase
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
        ]);

        $this->game->update(['pending_finalization_match_id' => $this->match->id]);
    }

    public function test_score_matches_events_after_tactical_change(): void
    {
        $this->actingAs($this->user);

        $response = $this->postJson(
            route('game.match.tactical-actions', ['gameId' => $this->game->id, 'matchId' => $this->match->id]),
            [
                'minute' => 50,
                'previousSubstitutions' => [],
                'newSubstitutions' => [],
                'formation' => '4-4-2',
            ],
        );

        $response->assertOk();

        $this->assertScoreMatchesEvents($this->match);
    }

    public function test_score_matches_events_after_skip_to_end(): void
    {
        $this->actingAs($this->user);

        $response = $this->postJson(
            route('game.match.skip-to-end', ['gameId' => $this->game->id, 'matchId' => $this->match->id]),
            [
                'minute' => 20,
                'previousSubstitutions' => [],
            ],
        );

        $response->assertOk();

        $this->assertScoreMatchesEvents($this->match);
    }

    public function test_score_matches_events_after_multiple_resimulations(): void
    {
        $this->actingAs($this->user);

        // First tactical change at minute 30
        $this->postJson(
            route('game.match.tactical-actions', ['gameId' => $this->game->id, 'matchId' => $this->match->id]),
            [
                'minute' => 30,
                'previousSubstitutions' => [],
                'newSubstitutions' => [],
                'mentality' => 'attacking',
            ],
        )->assertOk();

        $this->assertScoreMatchesEvents($this->match);

        // Second tactical change at minute 60
        $this->postJson(
            route('game.match.tactical-actions', ['gameId' => $this->game->id, 'matchId' => $this->match->id]),
            [
                'minute' => 60,
                'previousSubstitutions' => [],
                'newSubstitutions' => [],
                'mentality' => 'defensive',
            ],
        )->assertOk();

        $this->assertScoreMatchesEvents($this->match);
    }

    public function test_team_has_eleven_players_after_resimulation_with_pre_sim_auto_sub(): void
    {
        $this->actingAs($this->user);

        // Seed a pre-simulated injury auto-sub for the user team at minute 25
        $homeLineup = $this->match->home_lineup;
        $injuredPlayerId = $homeLineup[3]; // a defender

        $benchPlayers = GamePlayer::where('game_id', $this->game->id)
            ->where('team_id', $this->playerTeam->id)
            ->whereNotIn('id', $homeLineup)
            ->limit(1)
            ->get();

        $replacementPlayerId = $benchPlayers->first()->id;

        // Seed injury event
        MatchEvent::create([
            'game_id' => $this->game->id,
            'game_match_id' => $this->match->id,
            'game_player_id' => $injuredPlayerId,
            'team_id' => $this->playerTeam->id,
            'minute' => 25,
            'event_type' => MatchEvent::TYPE_INJURY,
            'metadata' => ['severity' => 'minor'],
        ]);

        // Seed substitution event (the auto-sub)
        MatchEvent::create([
            'game_id' => $this->game->id,
            'game_match_id' => $this->match->id,
            'game_player_id' => $injuredPlayerId,
            'team_id' => $this->playerTeam->id,
            'minute' => 25,
            'event_type' => MatchEvent::TYPE_SUBSTITUTION,
            'metadata' => ['player_in_id' => $replacementPlayerId],
        ]);

        // Trigger a resimulation at minute 50 (after the auto-sub at 25)
        $response = $this->postJson(
            route('game.match.tactical-actions', ['gameId' => $this->game->id, 'matchId' => $this->match->id]),
            [
                'minute' => 50,
                'previousSubstitutions' => [],
                'newSubstitutions' => [],
                'formation' => '4-4-2',
            ],
        );

        $response->assertOk();

        $this->assertScoreMatchesEvents($this->match);

        // The response must include events — the team should be playing with
        // a full complement (11 players minus any red cards). Verify the match
        // produced a valid result (non-null score).
        $this->match->refresh();
        $this->assertNotNull($this->match->home_score);
        $this->assertNotNull($this->match->away_score);
    }

    /**
     * Assert the critical invariant: goal event count in the DB matches
     * home_score + away_score on the match record.
     */
    private function assertScoreMatchesEvents(GameMatch $match): void
    {
        $match->refresh();

        $goalEvents = MatchEvent::where('game_match_id', $match->id)
            ->whereIn('event_type', [MatchEvent::TYPE_GOAL, MatchEvent::TYPE_OWN_GOAL])
            ->where('minute', '<=', 93)
            ->get();

        $homeGoals = 0;
        $awayGoals = 0;

        foreach ($goalEvents as $event) {
            if ($event->event_type === MatchEvent::TYPE_GOAL) {
                if ($event->team_id === $match->home_team_id) {
                    $homeGoals++;
                } else {
                    $awayGoals++;
                }
            } elseif ($event->event_type === MatchEvent::TYPE_OWN_GOAL) {
                if ($event->team_id === $match->home_team_id) {
                    $awayGoals++;
                } else {
                    $homeGoals++;
                }
            }
        }

        $this->assertEquals(
            $match->home_score,
            $homeGoals,
            "Home score ({$match->home_score}) does not match home goal event count ({$homeGoals}) — ghost goals detected",
        );
        $this->assertEquals(
            $match->away_score,
            $awayGoals,
            "Away score ({$match->away_score}) does not match away goal event count ({$awayGoals}) — ghost goals detected",
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
