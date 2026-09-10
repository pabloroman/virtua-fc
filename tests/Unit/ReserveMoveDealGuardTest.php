<?php

namespace Tests\Unit;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Models\TransferOffer;
use App\Modules\ReserveTeam\Exceptions\PlayerHasCommittedDealException;
use App\Modules\ReserveTeam\Services\ReserveTeamService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A player with a committed deal completes it from wherever he was when it
 * was agreed, so ReserveTeamService refuses to shuffle him between the first
 * team and the reserve in the meantime. The send-down side of this lives in
 * SendDownToReserveTest; this covers the other two moves.
 */
class ReserveMoveDealGuardTest extends TestCase
{
    use RefreshDatabase;

    private ReserveTeamService $service;
    private Team $firstTeam;
    private Team $reserveTeam;
    private Team $otherClub;
    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ReserveTeamService::class);

        $this->firstTeam = Team::factory()->create();
        $this->reserveTeam = Team::factory()->create(['parent_team_id' => $this->firstTeam->id]);
        $this->otherClub = Team::factory()->create();

        Competition::factory()->league()->create(['id' => 'ESP1']);

        $this->game = Game::factory()->create([
            'team_id' => $this->firstTeam->id,
            'reserve_team_id' => $this->reserveTeam->id,
            'competition_id' => 'ESP1',
            'season' => '2025',
            'current_date' => '2025-08-15',
        ]);
    }

    public function test_call_up_refuses_a_reserve_player_with_a_committed_deal(): void
    {
        $player = $this->playerAt($this->reserveTeam);
        $this->agreedSaleOf($player);

        $this->expectException(PlayerHasCommittedDealException::class);

        $this->service->callUpToFirstTeam($player, $this->game);
    }

    public function test_send_back_refuses_a_called_up_player_with_a_committed_deal(): void
    {
        $player = $this->playerAt($this->firstTeam);
        $this->agreedSaleOf($player);

        $this->expectException(PlayerHasCommittedDealException::class);

        $this->service->sendBackToReserve($player, $this->game);
    }

    public function test_a_pending_offer_does_not_block_a_move(): void
    {
        // Nothing is agreed yet, so nothing depends on where he sits.
        $player = $this->playerAt($this->reserveTeam);
        $this->agreedSaleOf($player, TransferOffer::STATUS_PENDING);

        $this->assertFalse($player->hasCommittedDeal());
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function playerAt(Team $team): GamePlayer
    {
        return GamePlayer::factory()->forGame($this->game)->forTeam($team)->create([
            'date_of_birth' => '2003-06-15',
            'contract_until' => '2027-06-30',
        ]);
    }

    private function agreedSaleOf(GamePlayer $player, string $status = TransferOffer::STATUS_AGREED): TransferOffer
    {
        return TransferOffer::create([
            'game_id' => $this->game->id,
            'game_player_id' => $player->id,
            'offering_team_id' => $this->otherClub->id,
            'selling_team_id' => $player->team_id,
            'offer_type' => TransferOffer::TYPE_LISTED,
            'direction' => TransferOffer::DIRECTION_OUTGOING,
            'transfer_fee' => 500_000_000,
            'status' => $status,
            'expires_at' => $this->game->current_date->copy()->addDays(14),
            'game_date' => $this->game->current_date,
        ]);
    }
}
