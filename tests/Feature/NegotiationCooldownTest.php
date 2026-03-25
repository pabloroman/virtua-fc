<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Player;
use App\Models\Team;
use App\Models\TransferOffer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NegotiationCooldownTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Team $userTeam;
    private Team $sellerTeam;
    private Competition $competition;
    private Game $game;
    private GamePlayer $targetPlayer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->userTeam = Team::factory()->create(['name' => 'User Team']);
        $this->sellerTeam = Team::factory()->create(['name' => 'Seller Team']);

        $this->competition = Competition::factory()->league()->create([
            'id' => 'ESP1',
            'name' => 'LaLiga',
        ]);

        $this->game = Game::factory()->create([
            'user_id' => $this->user->id,
            'team_id' => $this->userTeam->id,
            'competition_id' => $this->competition->id,
            'current_date' => '2025-08-01',
        ]);

        $player = Player::factory()->create(['date_of_birth' => '1998-01-01']);

        $this->targetPlayer = GamePlayer::factory()->create([
            'game_id' => $this->game->id,
            'player_id' => $player->id,
            'team_id' => $this->sellerTeam->id,
            'market_value_cents' => 10_000_000_00,
            'contract_until' => '2027-06-30',
        ]);
    }

    public function test_cooldown_blocks_negotiation_after_rejection_on_same_matchday(): void
    {
        // Create a rejected offer resolved on the current game date
        TransferOffer::create([
            'game_id' => $this->game->id,
            'game_player_id' => $this->targetPlayer->id,
            'offering_team_id' => $this->userTeam->id,
            'selling_team_id' => $this->sellerTeam->id,
            'offer_type' => TransferOffer::TYPE_USER_BID,
            'direction' => TransferOffer::DIRECTION_INCOMING,
            'transfer_fee' => 5_000_000_00,
            'status' => TransferOffer::STATUS_REJECTED,
            'resolved_at' => '2025-08-01',
            'expires_at' => '2025-08-08',
            'game_date' => '2025-08-01',
            'negotiation_round' => 1,
        ]);

        $response = $this->actingAs($this->user)->postJson(
            route('game.negotiate.transfer', [$this->game->id, $this->targetPlayer->id]),
            ['action' => 'start']
        );

        $response->assertStatus(422);
        $response->assertJson([
            'status' => 'error',
            'message' => __('transfers.negotiation_cooldown'),
        ]);
    }

    public function test_cooldown_expires_after_matchday_advances(): void
    {
        // Create a rejected offer resolved on a past game date
        TransferOffer::create([
            'game_id' => $this->game->id,
            'game_player_id' => $this->targetPlayer->id,
            'offering_team_id' => $this->userTeam->id,
            'selling_team_id' => $this->sellerTeam->id,
            'offer_type' => TransferOffer::TYPE_USER_BID,
            'direction' => TransferOffer::DIRECTION_INCOMING,
            'transfer_fee' => 5_000_000_00,
            'status' => TransferOffer::STATUS_REJECTED,
            'resolved_at' => '2025-07-30',
            'expires_at' => '2025-08-06',
            'game_date' => '2025-07-30',
            'negotiation_round' => 1,
        ]);

        // Game date is 2025-08-01, resolved_at is 2025-07-30 — cooldown expired
        $response = $this->actingAs($this->user)->postJson(
            route('game.negotiate.transfer', [$this->game->id, $this->targetPlayer->id]),
            ['action' => 'start']
        );

        $response->assertStatus(200);
        $response->assertJson(['status' => 'ok']);
    }

    public function test_cooldown_is_cross_type_rejected_transfer_blocks_loan(): void
    {
        // Create a rejected transfer offer
        TransferOffer::create([
            'game_id' => $this->game->id,
            'game_player_id' => $this->targetPlayer->id,
            'offering_team_id' => $this->userTeam->id,
            'selling_team_id' => $this->sellerTeam->id,
            'offer_type' => TransferOffer::TYPE_USER_BID,
            'direction' => TransferOffer::DIRECTION_INCOMING,
            'transfer_fee' => 5_000_000_00,
            'status' => TransferOffer::STATUS_REJECTED,
            'resolved_at' => '2025-08-01',
            'expires_at' => '2025-08-08',
            'game_date' => '2025-08-01',
            'negotiation_round' => 1,
        ]);

        // Try to start a loan negotiation for the same player
        $response = $this->actingAs($this->user)->postJson(
            route('game.negotiate.loan', [$this->game->id, $this->targetPlayer->id]),
            ['action' => 'start']
        );

        $response->assertStatus(422);
        $response->assertJson([
            'status' => 'error',
            'message' => __('transfers.negotiation_cooldown'),
        ]);
    }

    public function test_cooldown_does_not_affect_different_players(): void
    {
        // Create a rejected offer for the target player
        TransferOffer::create([
            'game_id' => $this->game->id,
            'game_player_id' => $this->targetPlayer->id,
            'offering_team_id' => $this->userTeam->id,
            'selling_team_id' => $this->sellerTeam->id,
            'offer_type' => TransferOffer::TYPE_USER_BID,
            'direction' => TransferOffer::DIRECTION_INCOMING,
            'transfer_fee' => 5_000_000_00,
            'status' => TransferOffer::STATUS_REJECTED,
            'resolved_at' => '2025-08-01',
            'expires_at' => '2025-08-08',
            'game_date' => '2025-08-01',
            'negotiation_round' => 1,
        ]);

        // Create a different player on the seller team
        $otherPlayer = GamePlayer::factory()->create([
            'game_id' => $this->game->id,
            'team_id' => $this->sellerTeam->id,
            'market_value_cents' => 5_000_000_00,
            'contract_until' => '2027-06-30',
        ]);

        // Should be able to negotiate for the other player
        $response = $this->actingAs($this->user)->postJson(
            route('game.negotiate.transfer', [$this->game->id, $otherPlayer->id]),
            ['action' => 'start']
        );

        $response->assertStatus(200);
        $response->assertJson(['status' => 'ok']);
    }

    public function test_has_negotiation_cooldown_static_method(): void
    {
        // No rejected offers — no cooldown
        $this->assertFalse(
            TransferOffer::hasNegotiationCooldown(
                $this->game->id, $this->targetPlayer->id, $this->userTeam->id, $this->game->current_date
            )
        );

        // Create a rejected offer on the current date
        TransferOffer::create([
            'game_id' => $this->game->id,
            'game_player_id' => $this->targetPlayer->id,
            'offering_team_id' => $this->userTeam->id,
            'selling_team_id' => $this->sellerTeam->id,
            'offer_type' => TransferOffer::TYPE_USER_BID,
            'direction' => TransferOffer::DIRECTION_INCOMING,
            'transfer_fee' => 5_000_000_00,
            'status' => TransferOffer::STATUS_REJECTED,
            'resolved_at' => '2025-08-01',
            'expires_at' => '2025-08-08',
            'game_date' => '2025-08-01',
            'negotiation_round' => 1,
        ]);

        // Now cooldown should be active
        $this->assertTrue(
            TransferOffer::hasNegotiationCooldown(
                $this->game->id, $this->targetPlayer->id, $this->userTeam->id, $this->game->current_date
            )
        );

        // After advancing the game date, cooldown should be gone
        $this->game->update(['current_date' => '2025-08-03']);
        $this->assertFalse(
            TransferOffer::hasNegotiationCooldown(
                $this->game->id, $this->targetPlayer->id, $this->userTeam->id, $this->game->current_date
            )
        );
    }

    public function test_get_offer_statuses_includes_cooldown_state(): void
    {
        $playerIds = [$this->targetPlayer->id];

        // No offers — empty result
        $statuses = TransferOffer::getOfferStatusesForPlayers($this->game->id, $playerIds, $this->game->current_date);
        $this->assertEmpty($statuses);

        // Create a rejected offer on the current date
        TransferOffer::create([
            'game_id' => $this->game->id,
            'game_player_id' => $this->targetPlayer->id,
            'offering_team_id' => $this->userTeam->id,
            'selling_team_id' => $this->sellerTeam->id,
            'offer_type' => TransferOffer::TYPE_USER_BID,
            'direction' => TransferOffer::DIRECTION_INCOMING,
            'transfer_fee' => 5_000_000_00,
            'status' => TransferOffer::STATUS_REJECTED,
            'resolved_at' => '2025-08-01',
            'expires_at' => '2025-08-08',
            'game_date' => '2025-08-01',
            'negotiation_round' => 1,
        ]);

        // Should show cooldown state
        $statuses = TransferOffer::getOfferStatusesForPlayers($this->game->id, $playerIds, $this->game->current_date);
        $this->assertArrayHasKey($this->targetPlayer->id, $statuses);
        $this->assertTrue($statuses[$this->targetPlayer->id]['onCooldown']);
        $this->assertNull($statuses[$this->targetPlayer->id]['status']);
    }
}
