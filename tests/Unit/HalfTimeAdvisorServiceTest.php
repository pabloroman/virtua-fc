<?php

namespace Tests\Unit;

use App\Models\Game;
use App\Models\GameMatch;
use App\Models\MatchEvent;
use App\Models\Team;
use App\Modules\Coaching\DTOs\CoachingTip;
use App\Modules\Coaching\DTOs\Confidence;
use App\Modules\Coaching\Services\HalfTimeAdvisorService;
use App\Modules\Lineup\Enums\DefensiveLineHeight;
use App\Modules\Lineup\Enums\Mentality;
use App\Modules\Lineup\Enums\PlayingStyle;
use App\Modules\Lineup\Enums\PressingIntensity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Tests\TestCase;

class HalfTimeAdvisorServiceTest extends TestCase
{
    use RefreshDatabase;

    private HalfTimeAdvisorService $service;

    private Game $game;

    private Team $userTeam;

    private Team $opponent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = $this->app->make(HalfTimeAdvisorService::class);
        $this->userTeam = Team::factory()->create();
        $this->opponent = Team::factory()->create();
        $this->game = Game::factory()->forTeam($this->userTeam)->create();
    }

    public function test_returns_chasing_tip_when_two_goals_down(): void
    {
        $match = $this->makeMatch(
            events: [
                ['minute' => 20, 'type' => MatchEvent::TYPE_GOAL, 'team_id' => $this->opponent->id],
                ['minute' => 35, 'type' => MatchEvent::TYPE_GOAL, 'team_id' => $this->opponent->id],
            ],
        );

        $tipById = $this->indexById($this->service->buildTips($match, $this->game));

        $this->assertArrayHasKey('result_chasing', $tipById);
        $this->assertEquals(Confidence::HIGH, $tipById['result_chasing']->confidence);
        $this->assertEquals(Mentality::ATTACKING->value, $tipById['result_chasing']->tacticalChange['mentality']);
        $this->assertEquals(PressingIntensity::HIGH_PRESS->value, $tipById['result_chasing']->tacticalChange['pressing']);
    }

    public function test_returns_protecting_lead_tip_when_two_goals_up(): void
    {
        $match = $this->makeMatch(
            userTactics: [
                'mentality' => Mentality::ATTACKING->value,
                'pressing' => PressingIntensity::HIGH_PRESS->value,
                'defensive_line' => DefensiveLineHeight::HIGH_LINE->value,
            ],
            events: [
                ['minute' => 10, 'type' => MatchEvent::TYPE_GOAL, 'team_id' => $this->userTeam->id],
                ['minute' => 30, 'type' => MatchEvent::TYPE_GOAL, 'team_id' => $this->userTeam->id],
            ],
        );

        $tipById = $this->indexById($this->service->buildTips($match, $this->game));

        $this->assertArrayHasKey('result_two_goal_lead', $tipById);
        $change = $tipById['result_two_goal_lead']->tacticalChange;
        $this->assertEquals(Mentality::BALANCED->value, $change['mentality']);
        $this->assertEquals(PressingIntensity::STANDARD->value, $change['pressing']);
        $this->assertEquals(DefensiveLineHeight::NORMAL->value, $change['defensive_line']);
    }

    public function test_one_goal_lead_only_intervenes_when_currently_attacking(): void
    {
        $matchBalanced = $this->makeMatch(
            userTactics: ['mentality' => Mentality::BALANCED->value],
            events: [
                ['minute' => 20, 'type' => MatchEvent::TYPE_GOAL, 'team_id' => $this->userTeam->id],
            ],
        );
        $matchAttacking = $this->makeMatch(
            userTactics: ['mentality' => Mentality::ATTACKING->value],
            events: [
                ['minute' => 20, 'type' => MatchEvent::TYPE_GOAL, 'team_id' => $this->userTeam->id],
            ],
        );

        $balancedIds = $this->indexById($this->service->buildTips($matchBalanced, $this->game));
        $attackingIds = $this->indexById($this->service->buildTips($matchAttacking, $this->game));

        $this->assertArrayNotHasKey('result_one_goal_lead_attacking', $balancedIds);
        $this->assertArrayHasKey('result_one_goal_lead_attacking', $attackingIds);
    }

    public function test_own_goals_credit_the_correct_team(): void
    {
        // Opponent scores an own goal → counts as user's goal.
        $match = $this->makeMatch(
            events: [
                ['minute' => 15, 'type' => MatchEvent::TYPE_OWN_GOAL, 'team_id' => $this->opponent->id],
                ['minute' => 25, 'type' => MatchEvent::TYPE_OWN_GOAL, 'team_id' => $this->opponent->id],
            ],
        );

        $tipById = $this->indexById($this->service->buildTips($match, $this->game));

        $this->assertArrayHasKey('result_two_goal_lead', $tipById);
    }

    public function test_returns_release_press_tip_when_opponent_high_presses_our_high_line(): void
    {
        $match = $this->makeMatch(
            userTactics: ['defensive_line' => DefensiveLineHeight::HIGH_LINE->value],
            opponentTactics: ['pressing' => PressingIntensity::HIGH_PRESS->value],
        );

        $tipById = $this->indexById($this->service->buildTips($match, $this->game));

        $this->assertArrayHasKey('matchup_release_press', $tipById);
        $change = $tipById['matchup_release_press']->tacticalChange;
        $this->assertEquals(DefensiveLineHeight::NORMAL->value, $change['defensive_line']);
        $this->assertEquals(PlayingStyle::DIRECT->value, $change['playing_style']);
    }

    public function test_returns_break_low_block_tip_when_opponent_sits_deep(): void
    {
        $match = $this->makeMatch(
            opponentTactics: ['pressing' => PressingIntensity::LOW_BLOCK->value],
        );

        $tipById = $this->indexById($this->service->buildTips($match, $this->game));

        $this->assertArrayHasKey('matchup_break_low_block', $tipById);
        $this->assertEquals(
            PlayingStyle::POSSESSION->value,
            $tipById['matchup_break_low_block']->tacticalChange['playing_style'],
        );
    }

    public function test_returns_card_risk_tip_advisory_only(): void
    {
        $userPlayerId = (string) Str::uuid();

        $match = $this->makeMatch(
            events: [
                ['minute' => 18, 'type' => MatchEvent::TYPE_YELLOW_CARD, 'team_id' => $this->userTeam->id, 'player_id' => $userPlayerId],
                ['minute' => 31, 'type' => MatchEvent::TYPE_YELLOW_CARD, 'team_id' => $this->userTeam->id, 'player_id' => $userPlayerId],
            ],
        );

        $tipById = $this->indexById($this->service->buildTips($match, $this->game));

        $this->assertArrayHasKey('discipline_card_risk', $tipById);
        $this->assertNull(
            $tipById['discipline_card_risk']->tacticalChange,
            'Discipline tip should be advisory-only (no apply payload).'
        );
    }

    public function test_falls_back_to_balanced_tip_when_nothing_notable(): void
    {
        $match = $this->makeMatch();

        $tips = $this->service->buildTips($match, $this->game);

        $this->assertNotEmpty($tips);
        $this->assertEquals('balanced_general', $tips[0]->id);
    }

    public function test_caps_tips_at_three(): void
    {
        $userPlayerId = (string) Str::uuid();

        $match = $this->makeMatch(
            userTactics: ['defensive_line' => DefensiveLineHeight::HIGH_LINE->value],
            opponentTactics: ['pressing' => PressingIntensity::HIGH_PRESS->value],
            events: [
                ['minute' => 10, 'type' => MatchEvent::TYPE_GOAL, 'team_id' => $this->opponent->id],
                ['minute' => 20, 'type' => MatchEvent::TYPE_GOAL, 'team_id' => $this->opponent->id],
                ['minute' => 30, 'type' => MatchEvent::TYPE_YELLOW_CARD, 'team_id' => $this->userTeam->id, 'player_id' => $userPlayerId],
                ['minute' => 38, 'type' => MatchEvent::TYPE_YELLOW_CARD, 'team_id' => $this->userTeam->id, 'player_id' => $userPlayerId],
            ],
        );

        $this->assertLessThanOrEqual(3, count($this->service->buildTips($match, $this->game)));
    }

    public function test_ignores_events_after_half_time(): void
    {
        // Two opponent goals after 45' should not trigger the chasing tip.
        $match = $this->makeMatch(
            events: [
                ['minute' => 60, 'type' => MatchEvent::TYPE_GOAL, 'team_id' => $this->opponent->id],
                ['minute' => 70, 'type' => MatchEvent::TYPE_GOAL, 'team_id' => $this->opponent->id],
            ],
        );

        $tipById = $this->indexById($this->service->buildTips($match, $this->game));
        $this->assertArrayNotHasKey('result_chasing', $tipById);
    }

    public function test_tips_serialise_to_array(): void
    {
        $match = $this->makeMatch(
            events: [
                ['minute' => 10, 'type' => MatchEvent::TYPE_GOAL, 'team_id' => $this->opponent->id],
                ['minute' => 30, 'type' => MatchEvent::TYPE_GOAL, 'team_id' => $this->opponent->id],
            ],
        );

        $tip = $this->service->buildTips($match, $this->game)[0];
        $array = $tip->toArray();

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('headline', $array);
        $this->assertArrayHasKey('rationale', $array);
        $this->assertArrayHasKey('tacticalChange', $array);
        $this->assertArrayHasKey('tone', $array);
        $this->assertArrayHasKey('confidence', $array);
    }

    /**
     * Build an in-memory GameMatch with attached events. We don't persist the
     * match — tests only need the relation to be loaded and tactical fields
     * present, so an in-memory model with `setRelation('events', ...)` is
     * cheaper and side-effect-free.
     *
     * @param  array<string, string>  $userTactics
     * @param  array<string, string>  $opponentTactics
     * @param  array<int, array{minute: int, type: string, team_id: string, player_id?: string}>  $events
     */
    private function makeMatch(
        array $userTactics = [],
        array $opponentTactics = [],
        array $events = [],
    ): GameMatch {
        $userTactics = array_merge([
            'mentality' => Mentality::BALANCED->value,
            'playing_style' => PlayingStyle::BALANCED->value,
            'pressing' => PressingIntensity::STANDARD->value,
            'defensive_line' => DefensiveLineHeight::NORMAL->value,
        ], $userTactics);

        $opponentTactics = array_merge([
            'mentality' => Mentality::BALANCED->value,
            'playing_style' => PlayingStyle::BALANCED->value,
            'pressing' => PressingIntensity::STANDARD->value,
            'defensive_line' => DefensiveLineHeight::NORMAL->value,
        ], $opponentTactics);

        // User team is home in these fixtures (irrelevant to the logic, kept
        // consistent so isHomeTeam() returns true and tactical-prefix lookup
        // routes home_* fields to the user side).
        $match = new GameMatch([
            'game_id' => $this->game->id,
            'competition_id' => $this->game->competition_id,
            'round_number' => 1,
            'home_team_id' => $this->userTeam->id,
            'away_team_id' => $this->opponent->id,
            'scheduled_date' => $this->game->current_date,
            'home_mentality' => $userTactics['mentality'],
            'home_playing_style' => $userTactics['playing_style'],
            'home_pressing' => $userTactics['pressing'],
            'home_defensive_line' => $userTactics['defensive_line'],
            'away_mentality' => $opponentTactics['mentality'],
            'away_playing_style' => $opponentTactics['playing_style'],
            'away_pressing' => $opponentTactics['pressing'],
            'away_defensive_line' => $opponentTactics['defensive_line'],
        ]);

        $eventCollection = new Collection();
        foreach ($events as $eventSpec) {
            $eventCollection->push(new MatchEvent([
                'game_id' => $this->game->id,
                'team_id' => $eventSpec['team_id'],
                'game_player_id' => $eventSpec['player_id'] ?? (string) Str::uuid(),
                'minute' => $eventSpec['minute'],
                'event_type' => $eventSpec['type'],
            ]));
        }

        $match->setRelation('events', $eventCollection);

        return $match;
    }

    /**
     * @param  array<int, CoachingTip>  $tips
     * @return array<string, CoachingTip>
     */
    private function indexById(array $tips): array
    {
        $indexed = [];
        foreach ($tips as $tip) {
            $indexed[$tip->id] = $tip;
        }

        return $indexed;
    }
}
