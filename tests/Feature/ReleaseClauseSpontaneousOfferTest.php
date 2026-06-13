<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Models\TransferOffer;
use App\Models\User;
use App\Modules\Transfer\Services\TransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

/**
 * A spontaneous incoming offer (unsolicited or listed) whose desire-driven price
 * reaches the player's release clause is a forced buyout, not a negotiable bid:
 * createOffer must produce it straight at STATUS_AGREED with the clause flag set,
 * so the user can't reject it and it completes through the normal outgoing
 * pipeline — mirroring generateAIReleaseClauseTriggers. Below the clause it stays
 * a normal, rejectable PENDING offer. Guards the bug where such an offer arrived
 * PENDING and could be rejected.
 */
class ReleaseClauseSpontaneousOfferTest extends TestCase
{
    use RefreshDatabase;

    private const MV = 10_000_000_00; // €10M

    private TransferService $transferService;
    private Game $game;
    private Team $userTeam;
    private Team $buyer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->transferService = app(TransferService::class);

        $user = User::factory()->create();
        $this->userTeam = Team::factory()->create();
        $this->buyer = Team::factory()->create();
        Competition::factory()->league()->create(['id' => 'ESP1', 'name' => 'LaLiga']);

        $this->game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $this->userTeam->id,
            'competition_id' => 'ESP1',
            'current_date' => '2025-08-01',
            'release_clauses_enabled' => true,
        ]);

        // A small squad for the buyer so calculateOfferPrice has a roster to read.
        GamePlayer::factory()->forGame($this->game)->forTeam($this->buyer)->count(3)->create([
            'position' => 'Centre-Forward',
            'overall_score' => 60,
        ]);
    }

    public function test_offer_meeting_the_clause_is_a_forced_agreed_sale(): void
    {
        // Clause of €1 — any plausible offer price clears it, so the cap binds and
        // the spontaneous offer becomes a forced sale.
        $clause = 1_00;
        $player = $this->userPlayer(['release_clause' => $clause]);

        $offer = $this->createOffer($player, TransferOffer::TYPE_UNSOLICITED);

        $this->assertSame(TransferOffer::STATUS_AGREED, $offer->status);
        $this->assertTrue((bool) $offer->triggered_release_clause);
        $this->assertSame($clause, (int) $offer->transfer_fee);
        $this->assertSame(TransferOffer::DIRECTION_OUTGOING, $offer->direction);
        $this->assertSame($this->userTeam->id, $offer->selling_team_id);
        $this->assertSame($this->buyer->id, $offer->offering_team_id);
        $this->assertNotNull($offer->resolved_at);
    }

    public function test_offer_below_the_clause_stays_a_normal_rejectable_offer(): void
    {
        // Clause far above any opening price — the cap never binds.
        $player = $this->userPlayer(['release_clause' => 1_000_000_000_00]);

        $offer = $this->createOffer($player, TransferOffer::TYPE_UNSOLICITED);

        $this->assertSame(TransferOffer::STATUS_PENDING, $offer->status);
        $this->assertFalse((bool) $offer->triggered_release_clause);
        $this->assertLessThan((int) $player->release_clause, (int) $offer->transfer_fee);
    }

    public function test_forced_sale_rejects_sibling_pending_offers(): void
    {
        $player = $this->userPlayer(['release_clause' => 1_00]);

        $otherBuyer = Team::factory()->create();
        $stale = TransferOffer::create([
            'game_id' => $this->game->id,
            'game_player_id' => $player->id,
            'offering_team_id' => $otherBuyer->id,
            'selling_team_id' => $this->userTeam->id,
            'offer_type' => TransferOffer::TYPE_UNSOLICITED,
            'direction' => TransferOffer::DIRECTION_OUTGOING,
            'transfer_fee' => self::MV,
            'status' => TransferOffer::STATUS_PENDING,
            'expires_at' => '2025-08-31',
            'game_date' => '2025-08-01',
        ]);

        $this->createOffer($player, TransferOffer::TYPE_UNSOLICITED);

        $this->assertSame(TransferOffer::STATUS_REJECTED, $stale->fresh()->status);
    }

    public function test_feature_disabled_never_forces_a_sale(): void
    {
        $this->game->update(['release_clauses_enabled' => false]);
        $player = $this->userPlayer(['release_clause' => 1_00]);

        $offer = $this->createOffer($player, TransferOffer::TYPE_UNSOLICITED);

        $this->assertSame(TransferOffer::STATUS_PENDING, $offer->status);
        $this->assertFalse((bool) $offer->triggered_release_clause);
    }

    private function userPlayer(array $overrides = []): GamePlayer
    {
        return GamePlayer::factory()->forGame($this->game)->forTeam($this->userTeam)->create(array_merge([
            'position' => 'Central Midfield',
            'overall_score' => 80,
            'market_value_cents' => self::MV,
        ], $overrides));
    }

    private function createOffer(GamePlayer $player, string $offerType): TransferOffer
    {
        $method = new ReflectionMethod($this->transferService, 'createOffer');
        $method->setAccessible(true);

        return $method->invoke($this->transferService, $player, $this->buyer, $offerType);
    }
}
