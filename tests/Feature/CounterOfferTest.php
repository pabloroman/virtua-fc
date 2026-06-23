<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GamePlayer;
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

        // Position defaults to Central Midfield (Midfielder group). overall_score
        // is set high so the player reads as a clear upgrade for the buyer below.
        $this->gamePlayer = GamePlayer::factory()->create([
            'game_id' => $this->game->id,
            'team_id' => $this->userTeam->id,
            'date_of_birth' => '1998-01-01',
            'market_value_cents' => 10_000_000_00, // €10M
            'overall_score' => 85,
            'contract_until' => '2027-06-30',
        ]);

        // Pad the user roster so SquadMinimumService lets the negotiated
        // sale proceed. Without this, acceptOffer() would refuse because
        // the squad would drop below MIN_SQUAD_SIZE / per-position floors.
        // The main player above (default Central Midfield) counts as the
        // seventh midfielder.
        $userSquadFiller = [
            ['Goalkeeper', 4],
            ['Centre-Back', 7],
            ['Central Midfield', 6],
            ['Centre-Forward', 5],
        ];
        foreach ($userSquadFiller as [$position, $count]) {
            for ($i = 0; $i < $count; $i++) {
                GamePlayer::factory()->create([
                    'game_id' => $this->game->id,
                    'team_id' => $this->userTeam->id,
                    'position' => $position,
                ]);
            }
        }

        // Buyer squad: 10 forwards (€100M total), ZERO midfielders. For the
        // midfield target above this is a high-need, clear-upgrade signing, so
        // SquadNeedService::desireScore ≈ 0.93 and the buyer's max willingness
        // lands near 1.55× market value (≈ €15.5M, within the ±10% premium jitter
        // band [€14.5M, €16.5M]). The €25M squad-value clamp (0.25 × €100M) does
        // not bind.
        for ($i = 0; $i < 10; $i++) {
            GamePlayer::factory()->create([
                'game_id' => $this->game->id,
                'team_id' => $this->buyerTeam->id,
                'position' => 'Centre-Forward',
                'overall_score' => 60,
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
            'expires_at' => '2025-08-15',
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
        // High-need buyer: max willingness ≈ €14.5–16.5M. 95% of the low end
        // (€14.5M) = €13.8M, so an above-market ask of €12M is comfortably
        // accepted regardless of the premium jitter.
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
        // Buyer max willingness ≈ €14.5–16.5M. Even at the highest jittered
        // walk-away point (1.25 × €16.5M ≈ €20.6M), an ask of €30M is far above
        // any plausible willingness → rejected.
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
        // Max willingness ≈ €14.5–16.5M. €16M sits robustly in the counter band for
        // the whole jitter range: above 95% of the high end (≈ €15.7M) so it is not
        // auto-accepted, and at/below the lowest walk-away point (1.15 × €14.5M ≈
        // €16.7M) so it is not rejected → the buyer counters with a raised bid.
        $this->actingAs($this->user)->postJson(
            route('game.negotiate.counter-offer', [$this->game->id, $this->offer->id]),
            ['action' => 'start']
        );

        $response = $this->actingAs($this->user)->postJson(
            route('game.negotiate.counter-offer', [$this->game->id, $this->offer->id]),
            ['action' => 'counter', 'bid' => 16_000_000] // €16M
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
        // Deals reached during an open window are parked as STATUS_AGREED
        // and completed after the next match (CompleteAgreedTransfersOnMatchPlayed).
        $this->assertEquals(TransferOffer::STATUS_AGREED, $this->offer->status);
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
        // Should be extended to 14 days from current_date
        $this->assertEquals('2025-08-15', $this->offer->expires_at->format('Y-m-d'));
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
