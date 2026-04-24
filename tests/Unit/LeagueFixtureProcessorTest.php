<?php

namespace Tests\Unit;

use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\Team;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Season\Processors\LeagueFixtureProcessor;
use App\Modules\Season\Services\SeasonInitializationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class LeagueFixtureProcessorTest extends TestCase
{
    use RefreshDatabase;

    public function test_deletes_old_matches_and_cup_ties_and_generates_new_fixtures(): void
    {
        $game = Game::factory()->create();
        $otherGame = Game::factory()->create();
        [$home, $away] = Team::factory()->count(2)->create();

        // Old matches + cup ties for this game (should be deleted)
        $oldMatch = GameMatch::create([
            'game_id' => $game->id,
            'competition_id' => $game->competition_id,
            'round_number' => 1,
            'home_team_id' => $home->id,
            'away_team_id' => $away->id,
            'scheduled_date' => '2024-08-15',
            'played' => true,
        ]);

        $oldTie = CupTie::create([
            'game_id' => $game->id,
            'competition_id' => $game->competition_id,
            'round' => 1,
            'home_team_id' => $home->id,
            'away_team_id' => $away->id,
            'season' => '2025',
        ]);

        // Same shapes for another game (should NOT be touched)
        $otherMatch = GameMatch::create([
            'game_id' => $otherGame->id,
            'competition_id' => $otherGame->competition_id,
            'round_number' => 1,
            'home_team_id' => $home->id,
            'away_team_id' => $away->id,
            'scheduled_date' => '2024-08-15',
            'played' => true,
        ]);

        $service = Mockery::mock(SeasonInitializationService::class);
        $service->shouldReceive('generateLeagueFixtures')
            ->once()
            ->with($game->id, $game->competition_id, '2026');

        $processor = new LeagueFixtureProcessor($service);

        $data = new SeasonTransitionData(
            oldSeason: '2025',
            newSeason: '2026',
            competitionId: $game->competition_id,
        );

        $processor->process($game, $data);

        $this->assertNull(GameMatch::find($oldMatch->id));
        $this->assertNull(CupTie::find($oldTie->id));
        $this->assertNotNull(GameMatch::find($otherMatch->id));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
