<?php

namespace Tests\Feature;

use App\Http\Actions\StartNewSeason;
use App\Http\Views\ShowGame;
use App\Http\Views\ShowSeasonEnd;
use App\Models\Competition;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GameStanding;
use App\Models\Team;
use App\Models\User;
use App\Modules\Competition\Enums\PlayoffState;
use App\Modules\Competition\Playoffs\PlayoffGeneratorFactory;
use App\Modules\Match\Services\MatchFinalizationService;
use App\Modules\Season\Jobs\ProcessSeasonTransition;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Regression shield for the "abandoned live match" bug:
 * the user reached the live-match screen but never clicked Continue, leaving
 * their match with played=true and standings_applied=false. In-season the
 * MatchdayOrchestrator's safety net recovers on the next advance, but at
 * end-of-season there is no next advance — the user goes straight to
 * season-end and StartNewSeason dispatches the closing pipeline against stale
 * standings, cascading into the promotion/relegation imbalance error.
 *
 * These tests verify that the HTTP entry points (StartNewSeason, ShowGame,
 * ShowSeasonEnd) all finalize the pending match before doing anything else.
 */
class AbandonedMatchFinalizationTest extends TestCase
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
            'tier' => 1,
        ]);

        $this->game = Game::factory()->create([
            'user_id' => $this->user->id,
            'team_id' => $this->playerTeam->id,
            'competition_id' => $this->competition->id,
            'season' => '2025',
            'current_date' => '2026-05-24',
            // Past the welcome/setup gates — the guard we're testing runs
            // after those checks, so we need to be past them to reach it.
            'needs_welcome' => false,
            'needs_new_season_setup' => false,
            'setup_completed_at' => Carbon::parse('2025-07-01'),
        ]);

        $this->createStandings();
    }

    public function test_finalize_pending_if_any_is_noop_without_pending_match(): void
    {
        $this->assertNull($this->game->pending_finalization_match_id);

        $result = app(MatchFinalizationService::class)->finalizePendingIfAny($this->game->id);

        $this->assertFalse($result, 'Should return false when there is nothing to finalize');
    }

    public function test_finalize_pending_if_any_applies_standings_for_abandoned_match(): void
    {
        // Simulate the exact stuck state: match is played but standings weren't
        // applied, and the game is flagged as pending finalization.
        $match = $this->createAbandonedMatch(homeScore: 2, awayScore: 0);
        $this->game->update(['pending_finalization_match_id' => $match->id]);

        $result = app(MatchFinalizationService::class)->finalizePendingIfAny($this->game->id);

        $this->assertTrue($result);

        $match->refresh();
        $this->assertTrue(
            $match->standings_applied,
            'standings_applied must flip to true after finalization'
        );

        $this->game->refresh();
        $this->assertNull(
            $this->game->pending_finalization_match_id,
            'pending flag must be cleared after finalization'
        );

        // Home team won 2-0 → 3 points, +2 goal difference.
        $homeStanding = GameStanding::where('game_id', $this->game->id)
            ->where('team_id', $this->playerTeam->id)
            ->first();
        $this->assertEquals(3, $homeStanding->points);
        $this->assertEquals(1, $homeStanding->played);
        $this->assertEquals(2, $homeStanding->goals_for);
        $this->assertEquals(0, $homeStanding->goals_against);
    }

    public function test_finalize_pending_if_any_clears_flag_when_match_not_actually_played(): void
    {
        // Defensive path: pending_finalization_match_id points to a match that
        // doesn't actually have played=true. Shouldn't try to finalize; should
        // just clear the stale flag so the game can progress.
        $match = GameMatch::factory()
            ->forGame($this->game)
            ->forCompetition($this->competition)
            ->between($this->playerTeam, $this->opponentTeam)
            ->create(['played' => false]);

        $this->game->update(['pending_finalization_match_id' => $match->id]);

        app(MatchFinalizationService::class)->finalizePendingIfAny($this->game->id);

        $this->game->refresh();
        $this->assertNull($this->game->pending_finalization_match_id);

        $match->refresh();
        $this->assertFalse($match->standings_applied);
    }

    public function test_start_new_season_finalizes_abandoned_match_before_dispatch(): void
    {
        Queue::fake();
        $this->fakeNoPlayoffs();

        $match = $this->createAbandonedMatch(homeScore: 3, awayScore: 1);
        $this->game->update(['pending_finalization_match_id' => $match->id]);

        // Pre-condition: standings haven't been applied.
        $this->assertFalse($match->standings_applied);

        $action = $this->app->make(StartNewSeason::class);
        $action($this->game->id);

        // The abandoned match must have been finalized before the transition
        // was dispatched. Without the guard, the closing pipeline would run
        // against stale standings and (for some league configurations) crash
        // with a promotion/relegation imbalance.
        $match->refresh();
        $this->assertTrue($match->standings_applied);

        $this->game->refresh();
        $this->assertNull($this->game->pending_finalization_match_id);
        $this->assertNotNull($this->game->season_transitioning_at);

        Queue::assertPushed(ProcessSeasonTransition::class);
    }

    public function test_show_game_finalizes_abandoned_match(): void
    {
        $match = $this->createAbandonedMatch(homeScore: 1, awayScore: 1);
        $this->game->update(['pending_finalization_match_id' => $match->id]);

        $this->actingAs($this->user)->get(route('show-game', $this->game->id));

        $match->refresh();
        $this->assertTrue(
            $match->standings_applied,
            'ShowGame must finalize any lingering pending match so a user returning after abandoning the live screen sees consistent state'
        );

        $this->game->refresh();
        $this->assertNull($this->game->pending_finalization_match_id);
    }

    public function test_show_season_end_finalizes_abandoned_match(): void
    {
        $match = $this->createAbandonedMatch(homeScore: 0, awayScore: 2);
        $this->game->update(['pending_finalization_match_id' => $match->id]);

        $this->actingAs($this->user)->get(route('game.season-end', $this->game->id));

        $match->refresh();
        $this->assertTrue(
            $match->standings_applied,
            'ShowSeasonEnd must finalize before building the summary, otherwise the user sees stale standings'
        );
    }

    /**
     * Replace the PlayoffGeneratorFactory with a no-op so StartNewSeason's
     * playoff-in-progress guard passes trivially. The behavior we're testing
     * (pending-match finalization) runs before that guard.
     */
    private function fakeNoPlayoffs(): void
    {
        $this->app->instance(PlayoffGeneratorFactory::class, new class extends PlayoffGeneratorFactory {
            public function __construct() {}
            public function all(): array { return []; }
        });
    }

    /**
     * Build the precise stuck state seen in production:
     *   - match is played (score set, played=true)
     *   - standings_applied is still false
     *   - the two teams' GameStanding rows do NOT reflect the match yet
     *
     * This mimics the output of MatchResultProcessor::bulkUpdateMatchScores
     * when the deferred user match's standings haven't yet been applied by
     * MatchFinalizationService::finalize.
     */
    private function createAbandonedMatch(int $homeScore, int $awayScore): GameMatch
    {
        return GameMatch::factory()
            ->forGame($this->game)
            ->forCompetition($this->competition)
            ->between($this->playerTeam, $this->opponentTeam)
            ->played($homeScore, $awayScore)
            ->scheduledOn('2026-05-24')
            ->create(['standings_applied' => false]);
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
}
