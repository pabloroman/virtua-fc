<?php

namespace Tests\Unit;

use App\Models\Game;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Season\Processors\AIFreeAgentSigningProcessor;
use App\Modules\Transfer\Services\AITransferMarketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AIFreeAgentSigningProcessorTest extends TestCase
{
    use RefreshDatabase;

    public function test_delegates_to_service_and_stores_signings_metadata(): void
    {
        $game = Game::factory()->create();

        $signings = [
            'totalSigned' => 12,
            'byTeam' => ['team-a' => 3, 'team-b' => 9],
        ];

        $service = Mockery::mock(AITransferMarketService::class);
        $service->shouldReceive('processSeasonFreeAgentSignings')
            ->once()
            ->with(Mockery::on(fn (Game $g) => $g->id === $game->id), '2026')
            ->andReturn($signings);

        $processor = new AIFreeAgentSigningProcessor($service);

        $data = new SeasonTransitionData(
            oldSeason: '2025',
            newSeason: '2026',
            competitionId: $game->competition_id,
        );

        $result = $processor->process($game, $data);

        $this->assertSame($signings, $result->getMetadata('freeAgentSignings'));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
