<?php

namespace Tests\Unit;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Models\TransferOffer;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Season\Processors\AgreedTransferCompletionProcessor;
use App\Modules\Season\Processors\TransferMarketResetProcessor;
use App\Modules\Season\Services\SeasonClosingPipeline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgreedTransferCompletionProcessorTest extends TestCase
{
    use RefreshDatabase;

    public function test_completes_agreed_outgoing_transfers_at_season_end(): void
    {
        $game = Game::factory()->create(['current_date' => '2025-06-15']);
        $userTeam = $game->team;
        $buyerTeam = Team::factory()->create();

        $player = GamePlayer::factory()->forGame($game)->forTeam($userTeam)->create();

        TransferOffer::create([
            'game_id' => $game->id,
            'game_player_id' => $player->id,
            'offering_team_id' => $buyerTeam->id,
            'offer_type' => TransferOffer::TYPE_LISTED,
            'transfer_fee' => 5_000_000_00,
            'status' => TransferOffer::STATUS_AGREED,
            'direction' => TransferOffer::DIRECTION_OUTGOING,
            'expires_at' => '2025-07-01',
            'game_date' => '2025-05-15',
            'resolved_at' => '2025-05-15',
        ]);

        $processor = app(AgreedTransferCompletionProcessor::class);
        $data = new SeasonTransitionData(oldSeason: '2024-25', newSeason: '2025-26', competitionId: $game->competition_id);

        $result = $processor->process($game, $data);

        $player->refresh();
        $this->assertSame($buyerTeam->id, $player->team_id);
        $this->assertNotEmpty($result->getMetadata('agreedTransfersCompleted'));
    }

    public function test_completes_agreed_incoming_transfers_at_season_end(): void
    {
        $game = Game::factory()->create(['current_date' => '2025-06-15']);
        $userTeam = $game->team;
        $sellerTeam = Team::factory()->create();

        $player = GamePlayer::factory()->forGame($game)->forTeam($sellerTeam)->create();

        TransferOffer::create([
            'game_id' => $game->id,
            'game_player_id' => $player->id,
            'offering_team_id' => $userTeam->id,
            'selling_team_id' => $sellerTeam->id,
            'offer_type' => TransferOffer::TYPE_USER_BID,
            'transfer_fee' => 3_000_000_00,
            'status' => TransferOffer::STATUS_AGREED,
            'direction' => TransferOffer::DIRECTION_INCOMING,
            'expires_at' => '2025-07-01',
            'game_date' => '2025-04-10',
            'resolved_at' => '2025-04-10',
        ]);

        $processor = app(AgreedTransferCompletionProcessor::class);
        $data = new SeasonTransitionData(oldSeason: '2024-25', newSeason: '2025-26', competitionId: $game->competition_id);

        $processor->process($game, $data);

        $player->refresh();
        $this->assertSame($userTeam->id, $player->team_id);
    }

    public function test_does_not_touch_pre_contract_offers(): void
    {
        $game = Game::factory()->create(['current_date' => '2025-06-15']);
        $userTeam = $game->team;
        $otherTeam = Team::factory()->create();

        $player = GamePlayer::factory()->forGame($game)->forTeam($userTeam)->create();

        $offer = TransferOffer::create([
            'game_id' => $game->id,
            'game_player_id' => $player->id,
            'offering_team_id' => $otherTeam->id,
            'offer_type' => TransferOffer::TYPE_PRE_CONTRACT,
            'transfer_fee' => 0,
            'status' => TransferOffer::STATUS_AGREED,
            'direction' => TransferOffer::DIRECTION_OUTGOING,
            'expires_at' => '2025-07-01',
            'game_date' => '2025-03-01',
            'resolved_at' => '2025-03-01',
        ]);

        $processor = app(AgreedTransferCompletionProcessor::class);
        $data = new SeasonTransitionData(oldSeason: '2024-25', newSeason: '2025-26', competitionId: $game->competition_id);

        $processor->process($game, $data);

        // Pre-contract should remain untouched (handled by PreContractTransferProcessor)
        $offer->refresh();
        $this->assertSame(TransferOffer::STATUS_AGREED, $offer->status);
        $player->refresh();
        $this->assertSame($userTeam->id, $player->team_id);
    }

    public function test_runs_before_transfer_market_reset(): void
    {
        $pipeline = app(SeasonClosingPipeline::class);
        $processors = $pipeline->getProcessors();

        $agreedIndex = null;
        $resetIndex = null;

        foreach ($processors as $index => $processor) {
            if ($processor instanceof AgreedTransferCompletionProcessor) {
                $agreedIndex = $index;
            }
            if ($processor instanceof TransferMarketResetProcessor) {
                $resetIndex = $index;
            }
        }

        $this->assertNotNull($agreedIndex, 'AgreedTransferCompletionProcessor not found in pipeline');
        $this->assertNotNull($resetIndex, 'TransferMarketResetProcessor not found in pipeline');
        $this->assertLessThan(
            $resetIndex,
            $agreedIndex,
            'AgreedTransferCompletionProcessor must run before TransferMarketResetProcessor'
        );
    }

    public function test_has_priority_35(): void
    {
        $processor = app(AgreedTransferCompletionProcessor::class);
        $this->assertSame(35, $processor->priority());
    }
}
