<?php

namespace Tests\Unit;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\ScoutReport;
use App\Models\ShortlistedPlayer;
use App\Models\Team;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Season\Processors\ScoutDataResetProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScoutDataResetProcessorTest extends TestCase
{
    use RefreshDatabase;

    private ScoutDataResetProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->processor = new ScoutDataResetProcessor();
    }

    public function test_deletes_all_scout_reports_for_game(): void
    {
        $game = Game::factory()->create();
        $otherGame = Game::factory()->create();

        ScoutReport::create([
            'game_id' => $game->id,
            'status' => ScoutReport::STATUS_COMPLETED,
            'filters' => ['position' => 'CB'],
            'weeks_total' => 2,
            'weeks_remaining' => 0,
            'player_ids' => [],
            'game_date' => '2025-01-01',
        ]);

        ScoutReport::create([
            'game_id' => $game->id,
            'status' => ScoutReport::STATUS_SEARCHING,
            'filters' => ['position' => 'CF'],
            'weeks_total' => 3,
            'weeks_remaining' => 1,
            'game_date' => '2025-03-01',
        ]);

        ScoutReport::create([
            'game_id' => $otherGame->id,
            'status' => ScoutReport::STATUS_COMPLETED,
            'filters' => ['position' => 'GK'],
            'weeks_total' => 2,
            'weeks_remaining' => 0,
            'player_ids' => [],
            'game_date' => '2025-01-01',
        ]);

        $data = new SeasonTransitionData(oldSeason: '2025', newSeason: '2026', competitionId: $game->competition_id);

        $this->processor->process($game, $data);

        $this->assertSame(0, ScoutReport::where('game_id', $game->id)->count());
        $this->assertSame(1, ScoutReport::where('game_id', $otherGame->id)->count());
    }

    public function test_deletes_all_shortlisted_players_for_game(): void
    {
        $game = Game::factory()->create();
        $otherGame = Game::factory()->create();
        $team = Team::factory()->create();

        $player1 = GamePlayer::factory()->forGame($game)->forTeam($team)->create();
        $player2 = GamePlayer::factory()->forGame($game)->forTeam($team)->create();
        $player3 = GamePlayer::factory()->forGame($otherGame)->forTeam($team)->create();

        ShortlistedPlayer::create([
            'game_id' => $game->id,
            'game_player_id' => $player1->id,
            'added_at' => '2025-01-15',
        ]);

        ShortlistedPlayer::create([
            'game_id' => $game->id,
            'game_player_id' => $player2->id,
            'added_at' => '2025-02-20',
        ]);

        ShortlistedPlayer::create([
            'game_id' => $otherGame->id,
            'game_player_id' => $player3->id,
            'added_at' => '2025-01-10',
        ]);

        $data = new SeasonTransitionData(oldSeason: '2025', newSeason: '2026', competitionId: $game->competition_id);

        $this->processor->process($game, $data);

        $this->assertSame(0, ShortlistedPlayer::where('game_id', $game->id)->count());
        $this->assertSame(1, ShortlistedPlayer::where('game_id', $otherGame->id)->count());
    }

    public function test_has_priority_20(): void
    {
        $this->assertSame(20, $this->processor->priority());
    }
}
