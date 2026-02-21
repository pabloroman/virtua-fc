<?php

namespace Tests\Unit;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\ScoutReport;
use App\Models\ShortlistedPlayer;
use App\Models\Team;
use App\Models\TransferOffer;
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

    public function test_deletes_all_transfer_offers_for_game(): void
    {
        $game = Game::factory()->create();
        $otherGame = Game::factory()->create();
        $team = Team::factory()->create();

        $player = GamePlayer::factory()->forGame($game)->forTeam($team)->create();
        $otherPlayer = GamePlayer::factory()->forGame($otherGame)->forTeam($team)->create();

        TransferOffer::create([
            'game_id' => $game->id,
            'game_player_id' => $player->id,
            'offering_team_id' => $team->id,
            'offer_type' => TransferOffer::TYPE_LISTED,
            'transfer_fee' => 5_000_000_00,
            'status' => TransferOffer::STATUS_COMPLETED,
            'direction' => TransferOffer::DIRECTION_OUTGOING,
            'expires_at' => '2025-02-01',
            'game_date' => '2025-01-15',
            'resolved_at' => '2025-01-20',
        ]);

        TransferOffer::create([
            'game_id' => $game->id,
            'game_player_id' => $player->id,
            'offering_team_id' => $team->id,
            'offer_type' => TransferOffer::TYPE_USER_BID,
            'transfer_fee' => 3_000_000_00,
            'status' => TransferOffer::STATUS_PENDING,
            'direction' => TransferOffer::DIRECTION_INCOMING,
            'expires_at' => '2025-06-01',
            'game_date' => '2025-05-20',
        ]);

        TransferOffer::create([
            'game_id' => $otherGame->id,
            'game_player_id' => $otherPlayer->id,
            'offering_team_id' => $team->id,
            'offer_type' => TransferOffer::TYPE_LISTED,
            'transfer_fee' => 1_000_000_00,
            'status' => TransferOffer::STATUS_COMPLETED,
            'direction' => TransferOffer::DIRECTION_OUTGOING,
            'expires_at' => '2025-03-01',
            'game_date' => '2025-02-15',
            'resolved_at' => '2025-02-20',
        ]);

        $data = new SeasonTransitionData(oldSeason: '2025', newSeason: '2026', competitionId: $game->competition_id);

        $this->processor->process($game, $data);

        $this->assertSame(0, TransferOffer::where('game_id', $game->id)->count());
        $this->assertSame(1, TransferOffer::where('game_id', $otherGame->id)->count());
    }

    public function test_clears_transfer_listed_status_from_players(): void
    {
        $game = Game::factory()->create();
        $otherGame = Game::factory()->create();
        $team = Team::factory()->create();

        $listedPlayer = GamePlayer::factory()->forGame($game)->forTeam($team)->create([
            'transfer_status' => 'listed',
            'transfer_listed_at' => '2025-01-10',
        ]);

        $normalPlayer = GamePlayer::factory()->forGame($game)->forTeam($team)->create([
            'transfer_status' => null,
            'transfer_listed_at' => null,
        ]);

        $otherGameListed = GamePlayer::factory()->forGame($otherGame)->forTeam($team)->create([
            'transfer_status' => 'listed',
            'transfer_listed_at' => '2025-02-01',
        ]);

        $data = new SeasonTransitionData(oldSeason: '2025', newSeason: '2026', competitionId: $game->competition_id);

        $this->processor->process($game, $data);

        $listedPlayer->refresh();
        $normalPlayer->refresh();
        $otherGameListed->refresh();

        $this->assertNull($listedPlayer->transfer_status);
        $this->assertNull($listedPlayer->transfer_listed_at);
        $this->assertNull($normalPlayer->transfer_status);
        $this->assertSame('listed', $otherGameListed->transfer_status);
        $this->assertNotNull($otherGameListed->transfer_listed_at);
    }

    public function test_has_priority_20(): void
    {
        $this->assertSame(20, $this->processor->priority());
    }
}
