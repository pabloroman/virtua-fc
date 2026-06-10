<?php

namespace Tests\Unit;

use App\Models\ClubProfile;
use App\Models\Competition;
use App\Models\Game;
use App\Models\GameFinances;
use App\Models\GameInvestment;
use App\Models\GameStadium;
use App\Models\GameStadiumProject;
use App\Models\StadiumLoan;
use App\Models\Team;
use App\Models\TeamReputation;
use App\Modules\Finance\Services\StadiumLoanService;
use App\Modules\Stadium\Services\StadiumUpgradeService;
use App\Modules\Match\Events\GameDateAdvanced;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Season\Processors\StadiumLoanBillingProcessor;
use App\Modules\Stadium\Enums\StadiumLoanStatus;
use App\Modules\Stadium\Enums\StadiumProjectFinancing;
use App\Modules\Stadium\Enums\StadiumProjectStatus;
use App\Modules\Stadium\Enums\StadiumProjectType;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Mockery;
use Tests\TestCase;

class StadiumUpgradeTest extends TestCase
{
    use RefreshDatabase;

    public function test_flat_principal_loan_schedule_declines_each_year(): void
    {
        $loan = new StadiumLoan([
            'principal_cents' => 100_000_000_00, // €100M
            'term_years' => 10,
            'interest_rate_bps' => 400,         // 4%
            'remaining_principal_cents' => 100_000_000_00,
            'status' => StadiumLoanStatus::Active,
        ]);

        // Year 1: €10M principal + 4% of €100M = €4M → €14M
        $year1 = $loan->next_payment_cents;
        $this->assertSame(14_000_000_00, $year1);

        // After paying year 1: remaining = €90M
        $loan->remaining_principal_cents = 90_000_000_00;
        // Year 2: €10M principal + 4% of €90M = €3.6M → €13.6M
        $this->assertSame(13_600_000_00, $loan->next_payment_cents);

        // Final year: remaining = €10M → €10M principal + 4% of €10M = €0.4M → €10.4M
        $loan->remaining_principal_cents = 10_000_000_00;
        $this->assertSame(10_400_000_00, $loan->next_payment_cents);
    }

    public function test_reputation_cap_and_affordability_cap_bind_independently(): void
    {
        [$game, $team] = $this->setupGame(reputation: ClubProfile::REPUTATION_LOCAL);

        // Revenue €100M → 25% × €100M / 0.14 = ~€178.5M → rounded down to €100M (per €1M).
        // Reputation cap for LOCAL: €100M.
        $finances = GameFinances::create([
            'game_id' => $game->id,
            'season' => (int) $game->season,
            'projected_total_revenue' => 100_000_000_00,
        ]);

        $service = app(StadiumLoanService::class);
        $cap = $service->maxLoanCap($game);

        // Both caps land around €100M; min should not exceed reputation ceiling.
        $this->assertLessThanOrEqual(100_00_000_000, $cap);
    }

    public function test_supplementary_commit_creates_project_and_deducts_cash(): void
    {
        [$game, $team] = $this->setupGame();
        $this->seedInvestment($game, transferBudget: 200_000_000_00); // €200M

        $service = app(StadiumUpgradeService::class);
        $project = $service->commitSupplementary($game, 2_000);

        $this->assertSame(StadiumProjectType::Supplementary, $project->type);
        $this->assertSame(StadiumProjectStatus::InProgress, $project->status);
        $this->assertSame(2_000, $project->target_capacity);
        $this->assertSame(StadiumProjectFinancing::Cash, $project->financing);
        // 2,000 × €4k = €8M
        $this->assertSame(8_000_000_00, $project->total_cost_cents);
        $this->assertSame(8_000_000_00, $project->paid_cents);

        $investment = GameInvestment::where('game_id', $game->id)->first();
        $this->assertSame(200_000_000_00 - 8_000_000_00, $investment->transfer_budget);
    }

    public function test_supplementary_rejects_when_over_cap(): void
    {
        [$game, $team] = $this->setupGame();
        $this->seedInvestment($game);

        // Pre-fill supplementary_seats near the cap.
        GameStadium::where('game_id', $game->id)->update(['supplementary_seats' => 4_500]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('messages.stadium_supplementary_exceeds_cap');

        app(StadiumUpgradeService::class)->commitSupplementary($game, 1_000);
    }

    public function test_concurrency_lock_blocks_second_project(): void
    {
        [$game, $team] = $this->setupGame();
        $this->seedInvestment($game);

        app(StadiumUpgradeService::class)->commitSupplementary($game, 1_000);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('messages.stadium_active_project_exists');

        app(StadiumUpgradeService::class)->commitSupplementary($game, 500);
    }

    public function test_rebuild_requires_minimum_reputation(): void
    {
        [$game, $team] = $this->setupGame(reputation: ClubProfile::REPUTATION_LOCAL);
        $this->seedInvestment($game, transferBudget: 2_000_000_000_00);
        $this->seedFinances($game, revenue: 200_000_000_00);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('messages.stadium_rebuild_reputation_too_low');

        app(StadiumUpgradeService::class)->commitRebuild(
            $game,
            25_000,
            StadiumProjectFinancing::Cash,
        );
    }

    public function test_rebuild_with_loan_creates_stadium_loan_and_no_cash_deduction(): void
    {
        [$game, $team] = $this->setupGame(reputation: ClubProfile::REPUTATION_ESTABLISHED);
        $startingBudget = 100_000_000_00;
        $this->seedInvestment($game, transferBudget: $startingBudget);
        $this->seedFinances($game, revenue: 400_000_000_00); // generous affordability

        $project = app(StadiumUpgradeService::class)->commitRebuild(
            $game,
            25_000,
            StadiumProjectFinancing::Loan,
        );

        $this->assertSame(StadiumProjectType::Rebuild, $project->type);
        $this->assertSame(StadiumProjectStatus::InProgress, $project->status);
        $this->assertSame(StadiumProjectFinancing::Loan, $project->financing);
        $this->assertNotNull($project->completion_date);
        $this->assertNull($project->completion_season);
        $this->assertSame(0, $project->paid_cents); // loan funds the project, club cash untouched
        $this->assertNotNull($project->stadium_loan_id);

        $loan = StadiumLoan::find($project->stadium_loan_id);
        $this->assertNotNull($loan);
        $this->assertSame($project->total_cost_cents, $loan->principal_cents);
        $this->assertSame($project->total_cost_cents, $loan->remaining_principal_cents);

        // Transfer budget unchanged with loan financing.
        $investment = GameInvestment::where('game_id', $game->id)->first();
        $this->assertSame($startingBudget, $investment->transfer_budget);
    }

    public function test_listener_completes_rebuild_and_folds_supletorias(): void
    {
        [$game, $team] = $this->setupGame();

        $stadium = GameStadium::where('game_id', $game->id)->first();
        $stadium->update(['supplementary_seats' => 3_000]);

        $project = GameStadiumProject::create([
            'game_id' => $game->id,
            'team_id' => $team->id,
            'type' => StadiumProjectType::Rebuild,
            'status' => StadiumProjectStatus::InProgress,
            'target_capacity' => 40_000,
            'committed_season' => (int) $game->season,
            'committed_date' => Carbon::parse('2025-01-01'),
            'completion_date' => Carbon::parse('2026-06-25'),
            'total_cost_cents' => 600_000_000_00,
            'financing' => StadiumProjectFinancing::Cash,
            'paid_cents' => 600_000_000_00,
        ]);

        $listener = app(\App\Modules\Finance\Listeners\ActivateCompletedStadiumProjects::class);
        $listener->handle(new GameDateAdvanced(
            $game,
            Carbon::parse('2026-06-24'),
            Carbon::parse('2026-06-30'),
        ));

        $project->refresh();
        $stadium->refresh();

        $this->assertSame(StadiumProjectStatus::Completed, $project->status);
        // Supletorias folded into the new base capacity.
        $this->assertSame(43_000, $stadium->rebuilt_capacity);
        $this->assertSame(0, $stadium->supplementary_seats);
        $this->assertSame(43_000, $stadium->effective_capacity);
    }

    public function test_loan_billing_processor_bills_active_loan_and_decrements_principal(): void
    {
        [$game, $team] = $this->setupGame();

        $project = GameStadiumProject::create([
            'game_id' => $game->id,
            'team_id' => $team->id,
            'type' => StadiumProjectType::Rebuild,
            'status' => StadiumProjectStatus::Completed,
            'target_capacity' => 30_000,
            'committed_season' => (int) $game->season - 5,
            'committed_date' => $game->current_date,
            'completion_date' => $game->current_date,
            'total_cost_cents' => 450_000_000_00,
            'financing' => StadiumProjectFinancing::Loan,
            'paid_cents' => 0,
        ]);

        $loan = StadiumLoan::create([
            'game_id' => $game->id,
            'stadium_project_id' => $project->id,
            'principal_cents' => 100_000_000_00,
            'term_years' => 10,
            'interest_rate_bps' => 400,
            'remaining_principal_cents' => 100_000_000_00,
            'season_started' => (int) $game->season - 4,
            'status' => StadiumLoanStatus::Active,
        ]);

        app(StadiumLoanBillingProcessor::class)->process($game, new SeasonTransitionData(
            oldSeason: (string) $game->season,
            newSeason: (string) ((int) $game->season + 1),
            competitionId: $game->competition_id,
        ));

        $loan->refresh();
        // €10M principal slice paid this year → €90M remaining.
        $this->assertSame(90_000_000_00, $loan->remaining_principal_cents);
        $this->assertSame(StadiumLoanStatus::Active, $loan->status);
    }

    public function test_listener_activates_supplementary_when_completion_date_reached(): void
    {
        [$game, $team] = $this->setupGame();

        $project = GameStadiumProject::create([
            'game_id' => $game->id,
            'team_id' => $team->id,
            'type' => StadiumProjectType::Supplementary,
            'status' => StadiumProjectStatus::InProgress,
            'target_capacity' => 2_000,
            'committed_season' => (int) $game->season,
            'committed_date' => Carbon::parse('2025-01-01'),
            'completion_date' => Carbon::parse('2025-01-31'),
            'total_cost_cents' => 16_000_000_00,
            'financing' => StadiumProjectFinancing::Cash,
            'paid_cents' => 16_000_000_00,
        ]);

        $listener = app(\App\Modules\Finance\Listeners\ActivateCompletedStadiumProjects::class);
        $listener->handle(new GameDateAdvanced(
            $game,
            Carbon::parse('2025-01-30'),
            Carbon::parse('2025-02-15'),
        ));

        $project->refresh();
        $stadium = GameStadium::where('game_id', $game->id)->first();

        $this->assertSame(StadiumProjectStatus::Completed, $project->status);
        $this->assertSame(2_000, $stadium->supplementary_seats);
    }

    public function test_listener_does_not_activate_supplementary_before_completion_date(): void
    {
        [$game, $team] = $this->setupGame();

        $project = GameStadiumProject::create([
            'game_id' => $game->id,
            'team_id' => $team->id,
            'type' => StadiumProjectType::Supplementary,
            'status' => StadiumProjectStatus::InProgress,
            'target_capacity' => 2_000,
            'committed_season' => (int) $game->season,
            'committed_date' => Carbon::parse('2025-01-01'),
            'completion_date' => Carbon::parse('2025-01-31'),
            'total_cost_cents' => 16_000_000_00,
            'financing' => StadiumProjectFinancing::Cash,
            'paid_cents' => 16_000_000_00,
        ]);

        $listener = app(\App\Modules\Finance\Listeners\ActivateCompletedStadiumProjects::class);
        $listener->handle(new GameDateAdvanced(
            $game,
            Carbon::parse('2025-01-15'),
            Carbon::parse('2025-01-20'),
        ));

        $project->refresh();
        $this->assertSame(StadiumProjectStatus::InProgress, $project->status);
    }

    public function test_rebuild_cost_uses_cumulative_bracket_pricing(): void
    {
        $service = app(StadiumUpgradeService::class);

        // Single-band example: 8,000 seats entirely in the first band
        // (≤10k @ €3k/seat) = €24M.
        $this->assertSame(24_000_000_00, $service->rebuildCostFor(8_000));

        // 45,000 seats crosses three bands:
        //   10,000 × €3k  = €30M
        // + 20,000 × €5k  = €100M
        // + 15,000 × €7k  = €105M
        //                = €235M
        $this->assertSame(235_000_000_00, $service->rebuildCostFor(45_000));

        // Elite-size example: 90,000 seats spans every band.
        //   10,000 × €3k  = €30M
        // + 20,000 × €5k  = €100M
        // + 20,000 × €7k  = €140M
        // + 30,000 × €10k = €300M
        // + 10,000 × €15k = €150M
        //                = €720M
        $this->assertSame(720_000_000_00, $service->rebuildCostFor(90_000));
    }

    public function test_max_rebuild_capacity_inverts_bracket_pricing(): void
    {
        $service = app(StadiumUpgradeService::class);

        // €15M budget: stays inside the first band (€3k/seat) → 5,000 seats.
        $this->assertSame(5_000, $service->maxRebuildCapacityForBudget(15_000_000_00));

        // €235M budget should be exactly the inversion of the 45k anchor above.
        $this->assertSame(45_000, $service->maxRebuildCapacityForBudget(235_000_000_00));

        // €720M budget → 90,000 seats per the elite-size anchor.
        $this->assertSame(90_000, $service->maxRebuildCapacityForBudget(720_000_000_00));

        // Zero / negative budget → no capacity.
        $this->assertSame(0, $service->maxRebuildCapacityForBudget(0));
    }

    public function test_stand_expansion_commit_with_cash_creates_project_and_deducts_cash(): void
    {
        [$game, $team] = $this->setupGame();
        $this->seedInvestment($game, transferBudget: 200_000_000_00); // €200M

        $project = app(StadiumUpgradeService::class)->commitStandExpansion(
            $game,
            5_000,
            StadiumProjectFinancing::Cash,
        );

        $this->assertSame(StadiumProjectType::StandExpansion, $project->type);
        $this->assertSame(StadiumProjectStatus::InProgress, $project->status);
        $this->assertSame(5_000, $project->target_capacity);
        // 5,000 × €8k = €40M
        $this->assertSame(40_000_000_00, $project->total_cost_cents);
        $this->assertSame(40_000_000_00, $project->paid_cents);
        $this->assertNotNull($project->completion_date);
        $this->assertNull($project->completion_season);

        $investment = GameInvestment::where('game_id', $game->id)->first();
        $this->assertSame(200_000_000_00 - 40_000_000_00, $investment->transfer_budget);
    }

    public function test_stand_expansion_with_loan_creates_stadium_loan_and_no_cash_deduction(): void
    {
        [$game, $team] = $this->setupGame(reputation: ClubProfile::REPUTATION_ESTABLISHED);
        $startingBudget = 5_000_000_00; // tiny cash on hand
        $this->seedInvestment($game, transferBudget: $startingBudget);
        $this->seedFinances($game, revenue: 200_000_000_00);

        $project = app(StadiumUpgradeService::class)->commitStandExpansion(
            $game,
            6_000,
            StadiumProjectFinancing::Loan,
        );

        $this->assertSame(StadiumProjectFinancing::Loan, $project->financing);
        $this->assertSame(0, $project->paid_cents);
        $this->assertNotNull($project->stadium_loan_id);

        // Transfer budget unchanged with loan financing.
        $investment = GameInvestment::where('game_id', $game->id)->first();
        $this->assertSame($startingBudget, $investment->transfer_budget);
    }

    public function test_stand_expansion_rejects_below_minimum_seats(): void
    {
        [$game, $team] = $this->setupGame();
        $this->seedInvestment($game, transferBudget: 200_000_000_00);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('messages.stadium_stand_expansion_too_few_seats');

        app(StadiumUpgradeService::class)->commitStandExpansion(
            $game,
            1_000, // below the 3,000-seat min
            StadiumProjectFinancing::Cash,
        );
    }

    public function test_stand_expansion_rejects_above_design_cap(): void
    {
        [$game, $team] = $this->setupGame();
        $this->seedInvestment($game, transferBudget: 200_000_000_00);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('messages.stadium_stand_expansion_exceeds_cap');

        app(StadiumUpgradeService::class)->commitStandExpansion(
            $game,
            15_000, // above the 12,000-seat cap
            StadiumProjectFinancing::Cash,
        );
    }

    public function test_stand_expansion_blocked_when_active_project_exists(): void
    {
        [$game, $team] = $this->setupGame();
        $this->seedInvestment($game, transferBudget: 200_000_000_00);

        app(StadiumUpgradeService::class)->commitSupplementary($game, 1_000);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('messages.stadium_active_project_exists');

        app(StadiumUpgradeService::class)->commitStandExpansion(
            $game,
            5_000,
            StadiumProjectFinancing::Cash,
        );
    }

    public function test_listener_completes_stand_expansion_on_completion_date(): void
    {
        [$game, $team] = $this->setupGame();

        $project = GameStadiumProject::create([
            'game_id' => $game->id,
            'team_id' => $team->id,
            'type' => StadiumProjectType::StandExpansion,
            'status' => StadiumProjectStatus::InProgress,
            'target_capacity' => 6_000,
            'committed_season' => (int) $game->season,
            'committed_date' => Carbon::parse('2025-01-01'),
            'completion_date' => Carbon::parse('2025-09-28'),
            'total_cost_cents' => 48_000_000_00,
            'financing' => StadiumProjectFinancing::Cash,
            'paid_cents' => 48_000_000_00,
        ]);

        $stadiumBefore = GameStadium::where('game_id', $game->id)->first();
        $baseBefore = $stadiumBefore->rebuilt_capacity ?? $stadiumBefore->base_capacity;

        $listener = app(\App\Modules\Finance\Listeners\ActivateCompletedStadiumProjects::class);
        $listener->handle(new GameDateAdvanced(
            $game,
            Carbon::parse('2025-09-27'),
            Carbon::parse('2025-10-02'),
        ));

        $project->refresh();
        $stadium = GameStadium::where('game_id', $game->id)->first();

        $this->assertSame(StadiumProjectStatus::Completed, $project->status);
        // Permanent capacity bumped by the project's target_capacity;
        // supplementary stands left untouched.
        $this->assertSame($baseBefore + 6_000, $stadium->rebuilt_capacity);
        $this->assertSame($stadiumBefore->supplementary_seats, $stadium->supplementary_seats);
    }

    /**
     * Helper: create a game with a team, reputation, stadium row, and
     * realistic defaults. Returns [Game, Team].
     */
    private function setupGame(string $reputation = ClubProfile::REPUTATION_ESTABLISHED): array
    {
        $team = Team::factory()->create(['stadium_seats' => 20_000]);
        $competition = Competition::factory()->league()->create();
        $game = Game::factory()
            ->forTeam($team)
            ->inCompetition($competition->id)
            ->create();

        TeamReputation::create([
            'game_id' => $game->id,
            'team_id' => $team->id,
            'reputation_level' => $reputation,
            'base_reputation_level' => $reputation,
            'reputation_points' => TeamReputation::pointsForTier($reputation),
        ]);

        GameStadium::create([
            'game_id' => $game->id,
            'team_id' => $team->id,
            'base_capacity' => $team->stadium_seats,
        ]);

        return [$game, $team];
    }

    private function seedInvestment(Game $game, int $transferBudget = 100_000_000_00): GameInvestment
    {
        return GameInvestment::create([
            'game_id' => $game->id,
            'season' => (int) $game->season,
            'transfer_budget' => $transferBudget,
        ]);
    }

    private function seedFinances(Game $game, int $revenue = 200_000_000_00): GameFinances
    {
        return GameFinances::create([
            'game_id' => $game->id,
            'season' => (int) $game->season,
            'projected_total_revenue' => $revenue,
        ]);
    }
}
