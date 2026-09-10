<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\GameTransfer;
use App\Models\Team;
use App\Models\TransferOffer;
use App\Models\User;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Season\Processors\AgreedTransferCompletionProcessor;
use App\Modules\Season\Processors\PreContractTransferProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * In a filial, a user-owned player can sit at the reserve team. His deals
 * must complete from there like anyone else's: the outgoing completion
 * queries look players up with departingFrom(Game::userTeamIds()), not the
 * first team alone, and the transfer record names the club he actually left.
 */
class ReservePlayerDealCompletionTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;
    private Team $firstTeam;
    private Team $reserveTeam;
    private Team $buyer;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->firstTeam = Team::factory()->create();
        $this->reserveTeam = Team::factory()->create(['parent_team_id' => $this->firstTeam->id]);
        $this->buyer = Team::factory()->create();

        Competition::factory()->league()->create(['id' => 'ESP1']);

        $this->game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $this->firstTeam->id,
            'reserve_team_id' => $this->reserveTeam->id,
            'competition_id' => 'ESP1',
            'season' => '2026',
            'current_date' => '2027-06-30',
        ]);
    }

    public function test_an_agreed_pre_contract_from_the_reserve_completes(): void
    {
        $player = $this->reservePlayer();
        $this->outgoingDeal($player, TransferOffer::TYPE_PRE_CONTRACT, fee: 0);

        $data = $this->runProcessor(PreContractTransferProcessor::class);

        $this->assertSame($this->buyer->id, $player->fresh()->team_id, 'A reserve player\'s pre-contract must deliver him.');
        $this->assertSame(
            $this->reserveTeam->id,
            $data->getMetadata('preContractTransfers')[0]['fromTeamId'],
            'The season summary names the club he actually left.',
        );
    }

    public function test_an_agreed_sale_from_the_reserve_completes(): void
    {
        $player = $this->reservePlayer();
        $this->outgoingDeal($player, TransferOffer::TYPE_LISTED, fee: 500_000_000);

        $this->runProcessor(AgreedTransferCompletionProcessor::class);

        $this->assertSame($this->buyer->id, $player->fresh()->team_id, 'A reserve player\'s agreed sale must complete.');
        $this->assertSame(
            $this->reserveTeam->id,
            GameTransfer::where('game_player_id', $player->id)->value('from_team_id'),
            'The transfer record names the club he actually left.',
        );
    }

    public function test_the_departures_queries_see_a_reserve_player_who_is_leaving(): void
    {
        // Every departures surface (outgoing page, header badge, narrative)
        // is built on departingFrom(userTeamIds()), so this is what they see.
        $player = $this->reservePlayer();
        $deal = $this->outgoingDeal($player, TransferOffer::TYPE_PRE_CONTRACT, fee: 0);

        $departing = TransferOffer::where('game_id', $this->game->id)
            ->agreedPreContract()
            ->departingFrom($this->game->userTeamIds())
            ->pluck('id')
            ->all();

        $this->assertSame([$deal->id], $departing);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function runProcessor(string $processor): SeasonTransitionData
    {
        return app($processor)->process($this->game, new SeasonTransitionData(
            oldSeason: $this->game->season,
            newSeason: '2027',
            competitionId: $this->game->competition_id,
        ));
    }

    private function reservePlayer(): GamePlayer
    {
        return GamePlayer::factory()->forGame($this->game)->forTeam($this->reserveTeam)->create([
            'date_of_birth' => '2004-01-01',
            'contract_until' => '2027-06-30',
            'annual_wage' => 20_000_000,
            'market_value_cents' => 200_000_000,
        ]);
    }

    private function outgoingDeal(GamePlayer $player, string $type, int $fee): TransferOffer
    {
        return TransferOffer::create([
            'game_id' => $this->game->id,
            'game_player_id' => $player->id,
            'offering_team_id' => $this->buyer->id,
            'selling_team_id' => $this->reserveTeam->id,
            'offer_type' => $type,
            'direction' => TransferOffer::DIRECTION_OUTGOING,
            'transfer_fee' => $fee,
            'offered_wage' => 30_000_000,
            'offered_years' => 3,
            'status' => TransferOffer::STATUS_AGREED,
            'expires_at' => $this->game->current_date,
            'game_date' => $this->game->current_date,
            'resolved_at' => $this->game->current_date,
        ]);
    }
}
