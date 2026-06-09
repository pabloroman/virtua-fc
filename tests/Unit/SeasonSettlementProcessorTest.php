<?php

namespace Tests\Unit;

use App\Models\FinancialTransaction;
use App\Models\Game;
use App\Models\GameFinances;
use App\Models\GameStanding;
use App\Models\Team;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Season\Processors\SeasonSettlementProcessor;
use App\Modules\Stadium\Services\MatchAttendanceService;
use App\Modules\Stadium\Services\NamingRightsService;
use App\Modules\Stadium\Services\SeasonTicketPricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SeasonSettlementProcessorTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_a_noop_when_game_has_no_finances_record(): void
    {
        $team = Team::factory()->create();
        $game = Game::factory()->forTeam($team)->create();

        $attendanceService = Mockery::mock(MatchAttendanceService::class);
        // No attendance lookups should happen when there are no finances to settle.
        $attendanceService->shouldNotReceive('resolveForMatch');

        $seasonTicketPricingService = Mockery::mock(SeasonTicketPricingService::class);
        $seasonTicketPricingService->shouldNotReceive('soldSeasonTicketsForGame');

        $namingRightsService = Mockery::mock(NamingRightsService::class);
        $namingRightsService->shouldNotReceive('settledRevenueForGame');

        $processor = new SeasonSettlementProcessor($attendanceService, $seasonTicketPricingService, $namingRightsService);

        $data = new SeasonTransitionData(
            oldSeason: '2025',
            newSeason: '2026',
            competitionId: $game->competition_id,
        );

        $result = $processor->process($game, $data);

        // Pass-through: metadata untouched, no exception thrown.
        $this->assertSame($data, $result);
        $this->assertNull($result->getMetadata('finances'));
    }

    public function test_stores_net_player_trading_result_windowed_to_the_season(): void
    {
        $team = Team::factory()->create();
        $game = Game::factory()->forTeam($team)->create(['season' => '2025']);

        GameFinances::create([
            'game_id' => $game->id,
            'season' => '2025',
            'projected_position' => 10,
            'projected_total_revenue' => 0,
            'projected_wages' => 0,
            'projected_operating_expenses' => 0,
            'projected_surplus' => 0,
            'projected_commercial_revenue' => 0,
            'projected_subsidy_revenue' => 0,
            'projected_solidarity_funds_revenue' => 0,
            'projected_season_ticket_revenue' => 0,
        ]);

        GameStanding::create([
            'game_id' => $game->id,
            'competition_id' => $game->competition_id,
            'team_id' => $game->team_id,
            'position' => 10,
            'played' => 38,
            'won' => 10,
            'drawn' => 8,
            'lost' => 20,
            'goals_for' => 40,
            'goals_against' => 60,
            'points' => 38,
        ]);

        // Season window: 2025-07-01 → 2026-06-30. A €40M sale and a €15M
        // purchase inside it → net +€25M. A €99M sale dated before the window
        // belongs to the prior season and must be excluded.
        FinancialTransaction::create([
            'game_id' => $game->id,
            'category' => FinancialTransaction::CATEGORY_TRANSFER_IN,
            'type' => FinancialTransaction::TYPE_INCOME,
            'amount' => 40_000_000_00,
            'transaction_date' => '2025-08-10',
        ]);
        FinancialTransaction::create([
            'game_id' => $game->id,
            'category' => FinancialTransaction::CATEGORY_TRANSFER_OUT,
            'type' => FinancialTransaction::TYPE_EXPENSE,
            'amount' => 15_000_000_00,
            'transaction_date' => '2026-01-20',
        ]);
        FinancialTransaction::create([
            'game_id' => $game->id,
            'category' => FinancialTransaction::CATEGORY_TRANSFER_IN,
            'type' => FinancialTransaction::TYPE_INCOME,
            'amount' => 99_000_000_00,
            'transaction_date' => '2025-05-01', // previous season — excluded
        ]);

        $attendanceService = Mockery::mock(MatchAttendanceService::class);
        $seasonTicketPricingService = Mockery::mock(SeasonTicketPricingService::class);
        $seasonTicketPricingService->shouldReceive('soldSeasonTicketsForGame')->andReturn(0);
        $namingRightsService = Mockery::mock(NamingRightsService::class);
        $namingRightsService->shouldReceive('settledRevenueForGame')->andReturn(0);

        $processor = new SeasonSettlementProcessor($attendanceService, $seasonTicketPricingService, $namingRightsService);
        $data = new SeasonTransitionData(oldSeason: '2025', newSeason: '2026', competitionId: $game->competition_id);

        $processor->process($game, $data);

        $this->assertSame(25_000_000_00, $game->currentFinances->net_transfer_result);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
