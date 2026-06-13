<?php

namespace Tests\Unit;

use App\Models\ClubProfile;
use App\Modules\Transfer\Services\AITeamBudgetCalculator;
use Illuminate\Support\Collection;
use Tests\TestCase;

class AITeamBudgetCalculatorTest extends TestCase
{
    private AITeamBudgetCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new AITeamBudgetCalculator();
    }

    public function test_elite_teams_get_higher_budgets_than_local_teams(): void
    {
        $teamRosters = collect([
            'elite-team' => $this->makeRoster(25, 500_000_00), // 25 players, €500K wage each
            'local-team' => $this->makeRoster(22, 100_000_00), // 22 players, €100K wage each
        ]);

        $reputationLevels = collect([
            'elite-team' => ClubProfile::REPUTATION_ELITE,
            'local-team' => ClubProfile::REPUTATION_LOCAL,
        ]);

        $budgets = $this->calculator->computeBudgets(
            $teamRosters, $reputationLevels, collect(), '2025', 'summer',
        );

        $this->assertGreaterThan(
            $budgets->get('local-team')['spending_limit'],
            $budgets->get('elite-team')['spending_limit'],
        );
    }

    public function test_winter_window_has_smaller_budget_than_summer(): void
    {
        $teamRosters = collect([
            'team-a' => $this->makeRoster(22, 300_000_00),
        ]);

        $reputationLevels = collect([
            'team-a' => ClubProfile::REPUTATION_ESTABLISHED,
        ]);

        $summerBudgets = $this->calculator->computeBudgets(
            $teamRosters, $reputationLevels, collect(), '2025', 'summer',
        );

        $winterBudgets = $this->calculator->computeBudgets(
            $teamRosters, $reputationLevels, collect(), '2025', 'winter',
        );

        $this->assertGreaterThan(
            $winterBudgets->get('team-a')['spending_limit'],
            $summerBudgets->get('team-a')['spending_limit'],
        );
    }

    public function test_earned_income_increases_available_budget(): void
    {
        $teamRosters = collect([
            'team-a' => $this->makeRoster(22, 300_000_00),
        ]);

        $reputationLevels = collect([
            'team-a' => ClubProfile::REPUTATION_ESTABLISHED,
        ]);

        $noSales = $this->calculator->computeBudgets(
            $teamRosters, $reputationLevels, collect(), '2025', 'summer',
        );

        $withSales = $this->calculator->computeBudgets(
            $teamRosters, $reputationLevels,
            collect(['team-a' => ['sells' => 1, 'buys' => 0, 'spent' => 0, 'earned' => 50_000_000_00]]),
            '2025', 'summer',
        );

        $this->assertGreaterThan(
            $noSales->get('team-a')['available'],
            $withSales->get('team-a')['available'],
        );
    }

    public function test_spending_reduces_available_budget(): void
    {
        $teamRosters = collect([
            'team-a' => $this->makeRoster(22, 300_000_00),
        ]);

        $reputationLevels = collect([
            'team-a' => ClubProfile::REPUTATION_ESTABLISHED,
        ]);

        $noSpending = $this->calculator->computeBudgets(
            $teamRosters, $reputationLevels, collect(), '2025', 'summer',
        );

        $withSpending = $this->calculator->computeBudgets(
            $teamRosters, $reputationLevels,
            collect(['team-a' => ['sells' => 0, 'buys' => 1, 'spent' => 10_000_000_00, 'earned' => 0]]),
            '2025', 'summer',
        );

        $this->assertLessThan(
            $noSpending->get('team-a')['available'],
            $withSpending->get('team-a')['available'],
        );
    }

    public function test_financial_pressure_zero_for_low_wage_teams(): void
    {
        // 10% wage-to-revenue ratio → well below the 40% threshold where pressure starts
        $players = $this->rosterAtWageRatio(ClubProfile::REPUTATION_ELITE, 0.10);

        $pressure = $this->calculator->financialPressure($players, ClubProfile::REPUTATION_ELITE);

        $this->assertEquals(0.0, $pressure);
    }

    public function test_financial_pressure_high_for_expensive_squads(): void
    {
        // 60% wage-to-revenue ratio → moderate pressure (inside the 40%–80% band)
        $players = $this->rosterAtWageRatio(ClubProfile::REPUTATION_CONTINENTAL, 0.60);

        $pressure = $this->calculator->financialPressure($players, ClubProfile::REPUTATION_CONTINENTAL);

        $this->assertGreaterThan(0.0, $pressure);
        $this->assertLessThan(1.0, $pressure);
    }

    public function test_financial_pressure_maxes_at_one(): void
    {
        // 200% wage-to-revenue ratio → far past the 80% ceiling → capped at 1.0
        $players = $this->rosterAtWageRatio(ClubProfile::REPUTATION_LOCAL, 2.00);

        $pressure = $this->calculator->financialPressure($players, ClubProfile::REPUTATION_LOCAL);

        $this->assertEquals(1.0, $pressure);
    }

    public function test_high_pressure_reduces_spending_limit(): void
    {
        $lowWageRoster = $this->makeRoster(22, 200_000_00); // Low wages
        $highWageRoster = $this->makeRoster(22, 5_000_000_00); // Very high wages

        $reputationLevels = collect([
            'low-wage' => ClubProfile::REPUTATION_CONTINENTAL,
            'high-wage' => ClubProfile::REPUTATION_CONTINENTAL,
        ]);

        $budgets = $this->calculator->computeBudgets(
            collect(['low-wage' => $lowWageRoster, 'high-wage' => $highWageRoster]),
            $reputationLevels, collect(), '2025', 'summer',
        );

        $this->assertGreaterThan(
            $budgets->get('high-wage')['spending_limit'],
            $budgets->get('low-wage')['spending_limit'],
        );
    }

    public function test_budget_tracks_transfer_counts(): void
    {
        $teamRosters = collect([
            'team-a' => $this->makeRoster(22, 300_000_00),
        ]);

        $reputationLevels = collect([
            'team-a' => ClubProfile::REPUTATION_ESTABLISHED,
        ]);

        $budgets = $this->calculator->computeBudgets(
            $teamRosters, $reputationLevels,
            collect(['team-a' => ['sells' => 2, 'buys' => 1, 'spent' => 5_000_000_00, 'earned' => 10_000_000_00]]),
            '2025', 'summer',
        );

        $budget = $budgets->get('team-a');
        $this->assertEquals(2, $budget['sells']);
        $this->assertEquals(1, $budget['buys']);
    }

    /**
     * Create a mock roster collection with uniform wages.
     */
    private function makeRoster(int $count, int $annualWage): Collection
    {
        return collect(range(1, $count))->map(fn () => (object) [
            'annual_wage' => $annualWage,
        ]);
    }

    /**
     * Build a roster whose total wage bill is a target fraction of the
     * reputation tier's estimated revenue. Derives the wage from config so the
     * test asserts the wage-to-revenue band, not a frozen revenue figure.
     */
    private function rosterAtWageRatio(string $reputationLevel, float $ratio, int $count = 20): Collection
    {
        $revenue = $this->calculator->estimatedRevenue($reputationLevel);

        return $this->makeRoster($count, (int) ($revenue * $ratio / $count));
    }
}
