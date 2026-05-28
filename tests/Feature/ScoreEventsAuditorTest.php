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
use App\Modules\Match\Support\ScoreEventsAuditor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Tests for the regulation-vs-events drift check added for issue #1158.
 *
 * The auditor stays quiet when persisted state is consistent and emits a
 * `Log::warning(...)` (no throw — a user mid-match shouldn't see a 500)
 * when regulation-phase goal events disagree with `home_score`/`away_score`.
 */
class ScoreEventsAuditorTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;
    private GameMatch $match;
    private GamePlayer $homePlayer;
    private GamePlayer $awayPlayer;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $homeTeam = Team::factory()->create();
        $awayTeam = Team::factory()->create();
        $competition = Competition::factory()->league()->create();

        $this->game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $homeTeam->id,
            'competition_id' => $competition->id,
        ]);

        $this->homePlayer = GamePlayer::factory()->forGame($this->game)->forTeam($homeTeam)->create();
        $this->awayPlayer = GamePlayer::factory()->forGame($this->game)->forTeam($awayTeam)->create();

        $this->match = GameMatch::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => $competition->id,
            'home_team_id' => $homeTeam->id,
            'away_team_id' => $awayTeam->id,
            'home_score' => 0,
            'away_score' => 0,
            'played' => true,
        ]);
    }

    public function test_no_warning_when_regulation_events_match_scoreboard(): void
    {
        $this->match->update(['home_score' => 2, 'away_score' => 1]);
        $this->seedRegulationGoal($this->homePlayer, minute: 20);
        $this->seedRegulationGoal($this->homePlayer, minute: 40);
        $this->seedRegulationGoal($this->awayPlayer, minute: 70);

        Log::spy();

        ScoreEventsAuditor::audit($this->match->refresh(), 'test_consistent');

        Log::shouldNotHaveReceived('warning');
    }

    public function test_regulation_warning_fires_when_event_count_disagrees_with_score(): void
    {
        // Score says 2-1 but only one regulation goal event is persisted.
        // The previous combined-only audit would *also* flag this (1 event
        // vs expected total 3), but the new regulation-specific warning
        // gives a clearer signal — regulation drift, not ET drift.
        $this->match->update(['home_score' => 2, 'away_score' => 1]);
        $this->seedRegulationGoal($this->homePlayer, minute: 30);

        Log::spy();

        ScoreEventsAuditor::audit($this->match->refresh(), 'test_regulation_drift');

        Log::shouldHaveReceived('warning')
            ->with('Match regulation score/events mismatch', \Mockery::on(function (array $ctx) {
                return $ctx['regulation_goal_events'] === 1
                    && $ctx['expected_regulation'] === 3
                    && $ctx['home_score'] === 2
                    && $ctx['away_score'] === 1;
            }))
            ->once();
    }

    public function test_regulation_warning_distinguishes_drift_when_total_balances_via_offsetting_et_mistake(): void
    {
        // Total: 1 event + 1 ET event = 2 events. Total expected =
        // home(2) + away(0) + home_et(0) + away_et(0) = 2 — the combined
        // audit is silent. But regulation alone is wrong (1 ev vs 2
        // expected), and the new check catches it.
        $this->match->update([
            'home_score' => 2,
            'away_score' => 0,
            'home_score_et' => 0,
            'away_score_et' => 0,
        ]);
        $this->seedRegulationGoal($this->homePlayer, minute: 30);
        // One stray ET goal event with no ET on the scoreboard.
        $this->seedExtraTimeGoal($this->homePlayer, minute: 95);

        Log::spy();

        ScoreEventsAuditor::audit($this->match->refresh(), 'test_offsetting_drift');

        Log::shouldHaveReceived('warning')
            ->with('Match regulation score/events mismatch', \Mockery::any())
            ->once();
    }

    private function seedRegulationGoal(GamePlayer $player, int $minute): void
    {
        MatchEvent::create([
            'game_id' => $this->game->id,
            'game_match_id' => $this->match->id,
            'game_player_id' => $player->id,
            'team_id' => $player->team_id,
            'minute' => $minute,
            'phase' => MatchPhase::FIRST_HALF, // explicit; the value gets re-decomposed by the model only when phase is null
            'event_type' => MatchEvent::TYPE_GOAL,
        ]);
    }

    private function seedExtraTimeGoal(GamePlayer $player, int $minute): void
    {
        MatchEvent::create([
            'game_id' => $this->game->id,
            'game_match_id' => $this->match->id,
            'game_player_id' => $player->id,
            'team_id' => $player->team_id,
            'minute' => $minute,
            'phase' => MatchPhase::ET_FIRST_HALF,
            'event_type' => MatchEvent::TYPE_GOAL,
        ]);
    }
}
