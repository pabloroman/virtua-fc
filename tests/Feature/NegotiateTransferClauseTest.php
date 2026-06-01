<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GameInvestment;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Models\TransferOffer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The release clause is folded into the normal transfer negotiation: an offer
 * that meets the buyout force-buys the player (non-refusable FEE_AGREED), a
 * sub-clause bid haggles normally, and the club never asks above the clause.
 */
class NegotiateTransferClauseTest extends TestCase
{
    use RefreshDatabase;

    private const MV = 5_000_000_000;       // €50M
    private const CLAUSE = 6_250_000_000;   // €50M × 1.25 = €62.5M
    private const BUDGET = 20_000_000_000;  // €200M

    private User $user;
    private Team $userTeam;
    private Team $sellerTeam;
    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->userTeam = Team::factory()->create(['name' => 'User Team', 'country' => 'ES']);
        $this->sellerTeam = Team::factory()->create(['name' => 'Seller Team', 'country' => 'ES']);

        Competition::factory()->league()->create(['id' => 'ESP1', 'name' => 'LaLiga']);

        $this->game = Game::factory()->create([
            'user_id' => $this->user->id,
            'team_id' => $this->userTeam->id,
            'competition_id' => 'ESP1',
            'country' => 'ES',
            'current_date' => '2025-08-01',
            'release_clauses_enabled' => true,
        ]);

        GameInvestment::create([
            'game_id' => $this->game->id,
            'season' => $this->game->season,
            'transfer_budget' => self::BUDGET,
            'scouting_tier' => 1,
        ]);
    }

    public function test_offer_meeting_the_clause_force_buys_the_player(): void
    {
        $player = $this->aiPlayerWithClause();

        $response = $this->actingAs($this->user)->postJson(
            route('game.negotiate.transfer', [$this->game->id, $player->id]),
            ['action' => 'offer', 'bid' => (int) (self::CLAUSE / 100)]
        );

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'ok',
            'negotiation_status' => 'fee_agreed',
        ]);

        $offer = TransferOffer::where('game_player_id', $player->id)
            ->where('status', TransferOffer::STATUS_FEE_AGREED)
            ->first();

        $this->assertNotNull($offer);
        $this->assertTrue((bool) $offer->triggered_release_clause, 'Meeting the clause is flagged as a buyout.');
        $this->assertSame(self::CLAUSE, (int) $offer->transfer_fee);
    }

    public function test_offer_above_the_clause_still_force_buys_at_the_clause_fee(): void
    {
        $player = $this->aiPlayerWithClause();

        $response = $this->actingAs($this->user)->postJson(
            route('game.negotiate.transfer', [$this->game->id, $player->id]),
            ['action' => 'offer', 'bid' => (int) (self::CLAUSE / 100) + 5_000_000]
        );

        $response->assertStatus(200);
        $response->assertJson(['negotiation_status' => 'fee_agreed']);

        $offer = TransferOffer::where('game_player_id', $player->id)
            ->where('status', TransferOffer::STATUS_FEE_AGREED)
            ->first();

        $this->assertNotNull($offer);
        $this->assertTrue((bool) $offer->triggered_release_clause);
        // The buyout is the clause, never the inflated bid.
        $this->assertSame(self::CLAUSE, (int) $offer->transfer_fee);
    }

    public function test_sub_clause_bid_negotiates_normally_without_triggering_the_clause(): void
    {
        $player = $this->aiPlayerWithClause();
        // The normal negotiation path enforces the seller's squad minimums
        // (unlike the non-refusable clause path), so give the AI club depth.
        $this->fillSellerSquad();

        $response = $this->actingAs($this->user)->postJson(
            route('game.negotiate.transfer', [$this->game->id, $player->id]),
            ['action' => 'offer', 'bid' => 1_000_000] // €1M on a €50M player — won't be accepted
        );

        $response->assertStatus(200);

        // No buyout was triggered: whatever offer exists is a normal negotiation.
        $this->assertSame(
            0,
            TransferOffer::where('game_player_id', $player->id)
                ->where('triggered_release_clause', true)
                ->count(),
        );
        $this->assertSame(
            0,
            TransferOffer::where('game_player_id', $player->id)
                ->where('transfer_fee', self::CLAUSE)
                ->count(),
        );
    }

    public function test_opening_ask_is_clamped_to_the_clause(): void
    {
        // Clause set at market value, below the natural asking premium, so the
        // opening demand must be pulled down to the buyout.
        $player = $this->aiPlayerWithClause(clause: self::MV);

        $response = $this->actingAs($this->user)->postJson(
            route('game.negotiate.transfer', [$this->game->id, $player->id]),
            ['action' => 'start']
        );

        $response->assertStatus(200);
        $fee = $response->json('messages.0.content.fee');
        $this->assertLessThanOrEqual((int) (self::MV / 100), $fee, 'The club never asks above the clause.');
    }

    private function aiPlayerWithClause(int $clause = self::CLAUSE): GamePlayer
    {
        return GamePlayer::factory()->forGame($this->game)->forTeam($this->sellerTeam)->create([
            'date_of_birth' => '1998-01-01',
            'market_value_cents' => self::MV,
            'contract_until' => '2027-06-30',
            'release_clause' => $clause,
        ]);
    }

    /**
     * A full, sellable AI squad so the normal-negotiation path's
     * assertSellerCanPartWith guard passes — the seller must keep the squad and
     * position-group minimums after the sale. Mirrors SellerSquadMinimumGuardTest;
     * the target is a Central Midfield player, so midfielder depth stays above
     * the tolerant floor once he leaves.
     */
    private function fillSellerSquad(): void
    {
        $positions = [
            ['Goalkeeper', 2],
            ['Centre-Back', 7],
            ['Central Midfield', 7],
            ['Centre-Forward', 5],
        ];

        foreach ($positions as [$position, $count]) {
            for ($i = 0; $i < $count; $i++) {
                GamePlayer::factory()->forGame($this->game)->forTeam($this->sellerTeam)->create([
                    'position' => $position,
                    'market_value_cents' => self::MV,
                ]);
            }
        }
    }
}
