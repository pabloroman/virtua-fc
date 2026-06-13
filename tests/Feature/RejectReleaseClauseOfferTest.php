<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Models\TransferOffer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Defensive guard on RejectTransferOffer: a pending offer that meets the player's
 * release clause is a forced buyout the user can't veto. New clause-meeting offers
 * are created as forced sales upstream, but this protects any pre-existing pending
 * offer sitting at the clause value.
 */
class RejectReleaseClauseOfferTest extends TestCase
{
    use RefreshDatabase;

    private const CLAUSE = 50_000_000_00; // €50M

    private User $user;
    private Game $game;
    private Team $userTeam;
    private Team $buyer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->userTeam = Team::factory()->create();
        $this->buyer = Team::factory()->create();
        Competition::factory()->league()->create(['id' => 'ESP1', 'name' => 'LaLiga']);

        $this->game = Game::factory()->create([
            'user_id' => $this->user->id,
            'team_id' => $this->userTeam->id,
            'competition_id' => 'ESP1',
            'current_date' => '2025-08-01',
            'release_clauses_enabled' => true,
        ]);
    }

    public function test_cannot_reject_a_pending_offer_that_meets_the_clause(): void
    {
        $offer = $this->offer(self::CLAUSE);

        $response = $this->actingAs($this->user)->post(
            route('game.transfers.reject', [$this->game->id, $offer->id]),
        );

        $response->assertRedirect(route('game.transfers.outgoing', $this->game->id));
        $response->assertSessionHas('error');
        $this->assertSame(TransferOffer::STATUS_PENDING, $offer->fresh()->status);
    }

    public function test_can_still_reject_an_offer_below_the_clause(): void
    {
        $offer = $this->offer(self::CLAUSE - 1_00);

        $this->actingAs($this->user)->post(
            route('game.transfers.reject', [$this->game->id, $offer->id]),
        );

        $this->assertSame(TransferOffer::STATUS_REJECTED, $offer->fresh()->status);
    }

    private function offer(int $transferFeeCents): TransferOffer
    {
        $player = GamePlayer::factory()->forGame($this->game)->forTeam($this->userTeam)->create([
            'release_clause' => self::CLAUSE,
            'market_value_cents' => self::CLAUSE,
        ]);

        return TransferOffer::create([
            'game_id' => $this->game->id,
            'game_player_id' => $player->id,
            'offering_team_id' => $this->buyer->id,
            'selling_team_id' => $this->userTeam->id,
            'offer_type' => TransferOffer::TYPE_UNSOLICITED,
            'direction' => TransferOffer::DIRECTION_OUTGOING,
            'transfer_fee' => $transferFeeCents,
            'status' => TransferOffer::STATUS_PENDING,
            'expires_at' => '2025-08-31',
            'game_date' => '2025-08-01',
        ]);
    }
}
