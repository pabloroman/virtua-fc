<?php

namespace Tests\Unit;

use App\Models\BudgetLoan;
use App\Models\Competition;
use App\Models\Game;
use App\Models\GameFinances;
use App\Models\ManagerJobHistory;
use App\Models\Team;
use App\Modules\Finance\Services\BudgetProjectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Regression tests for issue #1220 — Pro Manager financial carry-overs
 * must stay with the previous club after a team switch.
 *
 * GameFinances / BudgetLoan are keyed by (game_id, season) only, so the
 * service relies on ManagerJobHistory to detect when the previous-season
 * row belongs to a different club.
 */
class BudgetProjectionServiceTest extends TestCase
{
    use RefreshDatabase;

    private const PREV_SEASON = 2025;
    private const CUR_SEASON = '2026';
    private const ACTUAL_SURPLUS = 10_000_000_00;        // €100K
    private const CARRIED_SURPLUS = 2_000_000_00;        // €20K
    private const ACTUAL_COMMERCIAL_REVENUE = 50_000_000_00;
    private const ACTUAL_TOTAL_REVENUE = 200_000_000_00;
    private const LOAN_REPAYMENT = 5_000_000_00;

    public function test_pro_manager_switch_to_new_club_resets_carry_overs(): void
    {
        $oldClub = Team::factory()->create();
        $newClub = Team::factory()->create();
        $competition = Competition::factory()->league()->create();

        $game = Game::factory()
            ->forTeam($newClub)
            ->inCompetition($competition->id)
            ->create([
                'season' => self::CUR_SEASON,
                'game_mode' => Game::MODE_CAREER_PRO,
            ]);

        $this->seedPreviousSeasonFinances($game);
        $this->seedPreviousSeasonRepaidLoan($game);

        // Manager was at the old club for season 2025, then switched to the
        // new club for season 2026.
        ManagerJobHistory::create([
            'game_id' => $game->id,
            'user_id' => $game->user_id,
            'team_id' => $oldClub->id,
            'competition_id' => $competition->id,
            'season_start' => (string) self::PREV_SEASON,
            'season_end' => (string) self::PREV_SEASON,
            'end_reason' => ManagerJobHistory::REASON_LEFT_VOLUNTARILY,
        ]);
        ManagerJobHistory::create([
            'game_id' => $game->id,
            'user_id' => $game->user_id,
            'team_id' => $newClub->id,
            'competition_id' => $competition->id,
            'season_start' => self::CUR_SEASON,
            'season_end' => null,
            'end_reason' => ManagerJobHistory::REASON_STILL_ACTIVE,
        ]);

        $service = $this->makeService();

        $this->assertSame(0, $service->getCarriedSurplus($game));
        $this->assertSame(0, $service->getCarriedDebt($game));
        $this->assertSame(0, $service->getPreviousSeasonLoanRepayment($game));

        // AC #3: commercial revenue must fall back to the first-season
        // stadium-based calc, NOT inherit the old club's actual figure.
        $commercial = $this->invokeBaseCommercialRevenue($service, $game, $newClub, $competition);
        $this->assertNotSame(self::ACTUAL_COMMERCIAL_REVENUE, $commercial);
        $this->assertGreaterThan(0, $commercial);
    }

    public function test_pro_manager_continuous_tenure_keeps_carry_overs(): void
    {
        $club = Team::factory()->create();
        $competition = Competition::factory()->league()->create();

        $game = Game::factory()
            ->forTeam($club)
            ->inCompetition($competition->id)
            ->create([
                'season' => self::CUR_SEASON,
                'game_mode' => Game::MODE_CAREER_PRO,
            ]);

        $this->seedPreviousSeasonFinances($game);
        $this->seedPreviousSeasonRepaidLoan($game);

        // Manager has been at this club since season 2025, no switch.
        ManagerJobHistory::create([
            'game_id' => $game->id,
            'user_id' => $game->user_id,
            'team_id' => $club->id,
            'competition_id' => $competition->id,
            'season_start' => (string) self::PREV_SEASON,
            'season_end' => null,
            'end_reason' => ManagerJobHistory::REASON_STILL_ACTIVE,
        ]);

        $service = $this->makeService();

        // Net position = actual_surplus + carried_surplus = 120K → positive
        $expectedSurplus = self::ACTUAL_SURPLUS + self::CARRIED_SURPLUS;
        $this->assertSame($expectedSurplus, $service->getCarriedSurplus($game));
        $this->assertSame(0, $service->getCarriedDebt($game));
        $this->assertSame(self::LOAN_REPAYMENT, $service->getPreviousSeasonLoanRepayment($game));

        $commercial = $this->invokeBaseCommercialRevenue($service, $game, $club, $competition);
        $this->assertSame(self::ACTUAL_COMMERCIAL_REVENUE, $commercial);
    }

    public function test_club_manager_mode_carry_overs_flow_through(): void
    {
        $club = Team::factory()->create();
        $competition = Competition::factory()->league()->create();

        $game = Game::factory()
            ->forTeam($club)
            ->inCompetition($competition->id)
            ->create([
                'season' => self::CUR_SEASON,
                'game_mode' => Game::MODE_CAREER,
            ]);

        $this->seedPreviousSeasonFinances($game);
        $this->seedPreviousSeasonRepaidLoan($game);

        // No ManagerJobHistory rows — Club Manager mode doesn't write them,
        // and the helper must not require them for non-pro-manager games.

        $service = $this->makeService();

        $expectedSurplus = self::ACTUAL_SURPLUS + self::CARRIED_SURPLUS;
        $this->assertSame($expectedSurplus, $service->getCarriedSurplus($game));
        $this->assertSame(self::LOAN_REPAYMENT, $service->getPreviousSeasonLoanRepayment($game));

        $commercial = $this->invokeBaseCommercialRevenue($service, $game, $club, $competition);
        $this->assertSame(self::ACTUAL_COMMERCIAL_REVENUE, $commercial);
    }

    private function makeService(): BudgetProjectionService
    {
        return app(BudgetProjectionService::class);
    }

    private function seedPreviousSeasonFinances(Game $game): void
    {
        GameFinances::create([
            'game_id' => $game->id,
            'season' => self::PREV_SEASON,
            'actual_surplus' => self::ACTUAL_SURPLUS,
            'carried_surplus' => self::CARRIED_SURPLUS,
            'carried_debt' => 0,
            'actual_commercial_revenue' => self::ACTUAL_COMMERCIAL_REVENUE,
            'actual_total_revenue' => self::ACTUAL_TOTAL_REVENUE,
        ]);
    }

    private function seedPreviousSeasonRepaidLoan(Game $game): void
    {
        BudgetLoan::create([
            'game_id' => $game->id,
            'season' => self::PREV_SEASON,
            'amount' => self::LOAN_REPAYMENT,
            'interest_rate' => 0,
            'repayment_amount' => self::LOAN_REPAYMENT,
            'status' => BudgetLoan::STATUS_REPAID,
        ]);
    }

    /**
     * getBaseCommercialRevenue is private; invoke via reflection so we can
     * directly assert AC #3 (first-season fallback after a team switch).
     */
    private function invokeBaseCommercialRevenue(
        BudgetProjectionService $service,
        Game $game,
        Team $team,
        Competition $competition,
    ): int|float {
        $method = new ReflectionMethod($service, 'getBaseCommercialRevenue');

        return $method->invoke($service, $game, $team, $competition);
    }
}
