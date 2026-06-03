<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\Game;
use App\Models\Team;
use App\Models\User;
use App\Modules\Competition\Exceptions\ReserveParentCoexistenceException;
use App\Modules\Competition\Promotions\CountryPromotionRelegationPlanner;
use App\Modules\Competition\Promotions\PromotionRelegationPlan;
use App\Modules\Competition\Promotions\ReserveParentCoexistenceRepairer;
use App\Modules\Competition\Promotions\ReserveRepairResult;
use App\Modules\Season\Alerts\ReserveCoexistenceHealAlert;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Season\Processors\PromotionRelegationProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Control-flow tests for the in-band self-heal branch added to
 * {@see PromotionRelegationProcessor}. The planner, repairer, and alert are
 * mocked so each branch (heal+replan, unsafe rethrow, single-shot replan
 * failure) is exercised precisely — the planner's own behaviour and the
 * repairer's DB mutations are covered by their dedicated test suites.
 *
 * Note: the success alert fires via DB::afterCommit, which does not run under
 * RefreshDatabase (the wrapping transaction rolls back), so healed() is only
 * asserted permissively here. The synchronous unhealable() path is asserted
 * strictly.
 */
class PromotionRelegationSelfHealTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        Competition::factory()->league()->create(['id' => 'ESP1', 'tier' => 1, 'handler_type' => 'league']);
        Competition::factory()->league()->create(['id' => 'ESP2', 'tier' => 2, 'handler_type' => 'league_with_playoff']);
        Competition::factory()->league()->create(['id' => 'ESP3A', 'tier' => 3, 'handler_type' => 'league_with_playoff']);
        Competition::factory()->league()->create(['id' => 'ESP3B', 'tier' => 3, 'handler_type' => 'league_with_playoff']);
        Competition::factory()->knockoutCup()->create(['id' => 'ESP3PO', 'tier' => 3]);

        $user = User::factory()->create();
        $team = Team::factory()->create(['country' => 'ES']);
        $this->game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'competition_id' => 'ESP2',
            'season' => '2025',
            'country' => 'ES',
        ]);
    }

    public function test_self_heals_then_replans_and_completes(): void
    {
        $planner = Mockery::mock(CountryPromotionRelegationPlanner::class);
        $calls = 0;
        $planner->shouldReceive('planFromSnapshot')->twice()->andReturnUsing(function () use (&$calls) {
            $calls++;
            if ($calls === 1) {
                throw $this->coexistenceException();
            }
            return $this->emptyPlan();
        });

        $repairer = Mockery::mock(ReserveParentCoexistenceRepairer::class);
        $repairer->shouldReceive('repair')->once()->andReturn(ReserveRepairResult::repaired([], []));

        $alert = Mockery::mock(ReserveCoexistenceHealAlert::class);
        // healed() is scheduled via DB::afterCommit; under RefreshDatabase it
        // never fires, so we only forbid the failure alert here.
        $alert->shouldReceive('healed')->zeroOrMoreTimes();
        $alert->shouldNotReceive('unhealable');

        $result = $this->runProcessor($planner, $repairer, $alert);

        $this->assertInstanceOf(SeasonTransitionData::class, $result);
    }

    public function test_rethrows_and_alerts_when_repair_is_unsafe(): void
    {
        $planner = Mockery::mock(CountryPromotionRelegationPlanner::class);
        // Only the first plan attempt — no replan when the repair is unsafe.
        $planner->shouldReceive('planFromSnapshot')->once()->andThrow($this->coexistenceException());

        $repairer = Mockery::mock(ReserveParentCoexistenceRepairer::class);
        $repairer->shouldReceive('repair')->once()->andReturn(
            ReserveRepairResult::unsafe('tier has siblings — manual swap target required'),
        );

        $alert = Mockery::mock(ReserveCoexistenceHealAlert::class);
        $alert->shouldReceive('unhealable')->once();
        $alert->shouldNotReceive('healed');

        $this->bind($planner, $repairer, $alert);

        $this->expectException(ReserveParentCoexistenceException::class);
        $this->app->make(PromotionRelegationProcessor::class)->process($this->game, $this->data());
    }

    public function test_replan_failure_propagates_without_looping(): void
    {
        $planner = Mockery::mock(CountryPromotionRelegationPlanner::class);
        // Throws on both the initial plan and the single replan — never a 3rd.
        $planner->shouldReceive('planFromSnapshot')->twice()->andThrow($this->coexistenceException());

        $repairer = Mockery::mock(ReserveParentCoexistenceRepairer::class);
        $repairer->shouldReceive('repair')->once()->andReturn(ReserveRepairResult::repaired([], []));

        $alert = Mockery::mock(ReserveCoexistenceHealAlert::class);
        $alert->shouldNotReceive('healed');
        $alert->shouldNotReceive('unhealable');

        $this->bind($planner, $repairer, $alert);

        $this->expectException(ReserveParentCoexistenceException::class);
        $this->app->make(PromotionRelegationProcessor::class)->process($this->game, $this->data());
    }

    private function runProcessor(
        CountryPromotionRelegationPlanner $planner,
        ReserveParentCoexistenceRepairer $repairer,
        ReserveCoexistenceHealAlert $alert,
    ): SeasonTransitionData {
        $this->bind($planner, $repairer, $alert);

        return $this->app->make(PromotionRelegationProcessor::class)->process($this->game, $this->data());
    }

    private function bind(
        CountryPromotionRelegationPlanner $planner,
        ReserveParentCoexistenceRepairer $repairer,
        ReserveCoexistenceHealAlert $alert,
    ): void {
        $this->app->instance(CountryPromotionRelegationPlanner::class, $planner);
        $this->app->instance(ReserveParentCoexistenceRepairer::class, $repairer);
        $this->app->instance(ReserveCoexistenceHealAlert::class, $alert);
    }

    private function coexistenceException(): ReserveParentCoexistenceException
    {
        return ReserveParentCoexistenceException::forViolations([
            ['reserve' => 'reserve-x', 'parent' => 'parent-y', 'competition' => 'ESP2'],
        ]);
    }

    private function emptyPlan(): PromotionRelegationPlan
    {
        return new PromotionRelegationPlan('ES', [], [], []);
    }

    private function data(): SeasonTransitionData
    {
        return new SeasonTransitionData(oldSeason: '2025', newSeason: '2026', competitionId: 'ESP2');
    }
}
