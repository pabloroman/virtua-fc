<?php

namespace Tests\Unit;

use App\Models\Game;
use App\Models\Team;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Season\Processors\SeasonSettlementProcessor;
use App\Modules\Stadium\Services\MatchAttendanceService;
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

        $processor = new SeasonSettlementProcessor($attendanceService, $seasonTicketPricingService);

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

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
