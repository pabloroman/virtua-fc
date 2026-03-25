<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Player;
use App\Models\Team;
use App\Models\TransferOffer;
use App\Models\User;
use App\Modules\Transfer\Services\ContractService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CounterOfferTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Team $userTeam;
    private Team $buyerTeam;
    private Competition $competition;
    private Game $game;
    private GamePlayer $gamePlayer;
    private TransferOffer $offer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->userTeam = Team::factory()->create(['name' => 'User Team']);
        $this->buyerTeam = Team::factory()->create(['name' => 'Buyer Team']);

        $this->competition = Competition::factory()->league()->create([
            'id' => 'ESP1',
            'name' => 'LaLiga',
        ]);

        // Summer window (August) so transfers complete immediately
        $this->game = Game::factory()->create([
            'user_id' => $this->user->id,
            'team_id' => $this->userTeam->id,
            'competition_id' => $this->competition->id,
            'current_date' => '2025-08-01',
        ]);

        $player = Player::factory()->create(['date_of_birth' => '1998-01-01']);

        $this->gamePlayer = GamePlayer::factory()->create([
            'game_id' => $this->game->id,
            'player_id' => $player->id,
            'team_id' => $this->userTeam->id,
            'market_value_cents' => 10_000_000_00, // €10M
            'contract_until' => '2027-06-30',
        ]);

        // Create buyer team players to give them squad value (€100M total)
        for ($i = 0; $i < 10; $i++) {
            GamePlayer::factory()->create([
                'game_id' => $this->game->id,
                'team_id' => $this->buyerTeam->id,
                'market_value_cents' => 10_000_000_00, // €10M each = €100M total
            ]);
        }

        // Create the unsolicited offer at €11M (1.1x market value)
        $this->offer = TransferOffer::create([
            'game_id' => $this->game->id,
            'game_player_id' => $this->gamePlayer->id,
            'offering_team_id' => $this->buyerTeam->id,
            'offer_type' => TransferOffer::TYPE_UNSOLICITED,
            'direction' => TransferOffer::DIRECTION_OUTGOING,
            'transfer_fee' => 11_000_000_00, // €11M
            'status' => TransferOffer::STATUS_PENDING,
            'expires_at' => '2025-08-06',
            'game_date' => '2025-08-01',
        ]);
    }

    public function test_start_returns_buyer_opening_message(): void
    {
        $response = $this->actingAs($this->user)->postJson(
            route('game.negotiate.counter-offer', [$this->game->id, $this->offer->id]),
            ['action' => 'start']
        );

        $response->assertOk();
        $response->assertJsonPath('negotiation_status', 'open');
        $response->assertJsonPath('round', 0);
        $this->assertNotEmpty($response->json('messages'));
    }

    public function test_counter_accepted_when_asking_within_willingness(): void
    {
        // Buyer squad value = €100M, so max willingness = min(€25M, €13M) = €13M
        // 95% of €13M = €12.35M. Ask for €12M → should be accepted
        $this->actingAs($this->user)->postJson(
            route('game.negotiate.counter-offer', [$this->game->id, $this->offer->id]),
            ['action' => 'start']
        );

        $response = $this->actingAs($this->user)->postJson(
            route('game.negotiate.counter-offer', [$this->game->id, $this->offer->id]),
            ['action' => 'counter', 'bid' => 12_000_000] // €12M in euros
        );

        $response->assertOk();
        $response->assertJsonPath('negotiation_status', 'completed');
    }

    public function test_counter_rejected_when_asking_far_above_willingness(): void
    {
        // Buyer max willingness = €13M. 115% of €13M = €14.95M. Ask for €30M → rejected
        $this->actingAs($this->user)->postJson(
            route('game.negotiate.counter-offer', [$this->game->id, $this->offer->id]),
            ['action' => 'start']
        );

        $response = $this->actingAs($this->user)->postJson(
            route('game.negotiate.counter-offer', [$this->game->id, $this->offer->id]),
            ['action' => 'counter', 'bid' => 30_000_000] // €30M in euros
        );

        $response->assertOk();
        $response->assertJsonPath('negotiation_status', 'rejected');

        $this->offer->refresh();
        $this->assertEquals(TransferOffer::STATUS_REJECTED, $this->offer->status);
    }

    public function test_counter_results_in_ai_counter_when_moderately_above(): void
    {
        // Max willingness = €13M. Ask for €14M (between 95% and 115% of €13M) → countered
        $this->actingAs($this->user)->postJson(
            route('game.negotiate.counter-offer', [$this->game->id, $this->offer->id]),
            ['action' => 'start']
        );

        $response = $this->actingAs($this->user)->postJson(
            route('game.negotiate.counter-offer', [$this->game->id, $this->offer->id]),
            ['action' => 'counter', 'bid' => 14_000_000] // €14M
        );

        $response->assertOk();
        $response->assertJsonPath('negotiation_status', 'open');

        $this->offer->refresh();
        $this->assertEquals(1, $this->offer->negotiation_round);
        $this->assertGreaterThan(11_000_000_00, $this->offer->transfer_fee); // AI raised their bid
    }

    public function test_accept_counter_completes_sale(): void
    {
        // Start and get a counter, then accept it
        $this->actingAs($this->user)->postJson(
            route('game.negotiate.counter-offer', [$this->game->id, $this->offer->id]),
            ['action' => 'start']
        );

        // Force a counter state
        $this->offer->update([
            'negotiation_round' => 1,
            'transfer_fee' => 12_500_000_00,
            'asking_price' => 14_000_000_00,
        ]);

        $response = $this->actingAs($this->user)->postJson(
            route('game.negotiate.counter-offer', [$this->game->id, $this->offer->id]),
            ['action' => 'accept_counter']
        );

        $response->assertOk();
        $response->assertJsonPath('negotiation_status', 'completed');

        $this->offer->refresh();
        // Should be completed (window is open in August)
        $this->assertEquals(TransferOffer::STATUS_COMPLETED, $this->offer->status);
    }

    public function test_counter_must_be_higher_than_current_bid(): void
    {
        $this->actingAs($this->user)->postJson(
            route('game.negotiate.counter-offer', [$this->game->id, $this->offer->id]),
            ['action' => 'start']
        );

        $response = $this->actingAs($this->user)->postJson(
            route('game.negotiate.counter-offer', [$this->game->id, $this->offer->id]),
            ['action' => 'counter', 'bid' => 10_000_000] // €10M < €11M current offer
        );

        $response->assertStatus(422);
    }

    public function test_cannot_counter_non_unsolicited_offer(): void
    {
        $this->offer->update(['offer_type' => TransferOffer::TYPE_USER_BID]);

        $response = $this->actingAs($this->user)->postJson(
            route('game.negotiate.counter-offer', [$this->game->id, $this->offer->id]),
            ['action' => 'start']
        );

        $response->assertStatus(404);
    }

    public function test_cannot_counter_expired_offer(): void
    {
        $this->offer->update(['status' => TransferOffer::STATUS_EXPIRED]);

        $response = $this->actingAs($this->user)->postJson(
            route('game.negotiate.counter-offer', [$this->game->id, $this->offer->id]),
            ['action' => 'start']
        );

        $response->assertStatus(404);
    }

    public function test_resume_mid_negotiation(): void
    {
        // Set up a mid-negotiation state
        $this->offer->update([
            'negotiation_round' => 1,
            'transfer_fee' => 12_000_000_00,
            'asking_price' => 14_000_000_00,
        ]);

        $response = $this->actingAs($this->user)->postJson(
            route('game.negotiate.counter-offer', [$this->game->id, $this->offer->id]),
            ['action' => 'start']
        );

        $response->assertOk();
        $response->assertJsonPath('negotiation_status', 'open');
        $response->assertJsonPath('round', 1);

        // Should show the AI's current bid in the resume message
        $messages = $response->json('messages');
        $this->assertNotEmpty($messages);
        $this->assertEquals('counter', $messages[0]['type']);
    }

    public function test_negotiation_capped_at_max_rounds(): void
    {
        $maxRounds = ContractService::MAX_NEGOTIATION_ROUNDS;

        // Set up at max rounds - 1
        $this->offer->update([
            'negotiation_round' => $maxRounds - 1,
            'transfer_fee' => 12_000_000_00,
            'asking_price' => 14_000_000_00,
        ]);

        // One more counter should trigger rejection if not accepted
        $response = $this->actingAs($this->user)->postJson(
            route('game.negotiate.counter-offer', [$this->game->id, $this->offer->id]),
            ['action' => 'counter', 'bid' => 14_000_000]
        );

        $response->assertOk();
        // At max rounds, even a counter-eligible bid should be rejected
        $this->assertContains($response->json('negotiation_status'), ['completed', 'rejected']);
    }

    public function test_expiry_extended_on_start(): void
    {
        // Set expiry to tomorrow
        $this->offer->update(['expires_at' => '2025-08-02']);

        $this->actingAs($this->user)->postJson(
            route('game.negotiate.counter-offer', [$this->game->id, $this->offer->id]),
            ['action' => 'start']
        );

        $this->offer->refresh();
        // Should be extended to 5 days from current_date
        $this->assertEquals('2025-08-06', $this->offer->expires_at->format('Y-m-d'));
    }

    public function test_cannot_counter_other_users_player(): void
    {
        $otherUser = User::factory()->create();
        $otherTeam = Team::factory()->create();
        $otherGame = Game::factory()->create([
            'user_id' => $otherUser->id,
            'team_id' => $otherTeam->id,
            'competition_id' => $this->competition->id,
        ]);

        $response = $this->actingAs($otherUser)->postJson(
            route('game.negotiate.counter-offer', [$this->game->id, $this->offer->id]),
            ['action' => 'start']
        );

        // Should fail because the player doesn't belong to the other user's team
        $this->assertContains($response->status(), [403, 404]);
    }
}
