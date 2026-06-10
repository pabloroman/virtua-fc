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
use Tests\TestCase;

/**
 * A buying club would never open above the buyout — it would just pay the
 * release clause and force the sale. These tests drive the shared offer chokepoint
 * (TransferService::createOffer, used by both unsolicited and listed offers) and
 * prove the opening fee is capped at the clause when clauses are active, and left
 * untouched when the feature is off.
 *
 * Determinism: calculateOfferPrice multiplies market value by 0.77..1.13 (floor−jitter
 * to ceil+jitter) and an age modifier that bottoms out at 0.5, so the uncapped price is
 * always ≥ MV × 0.385. With MV €30M and a €5M clause, the cap therefore always binds.
 */
class ClubOfferReleaseClauseCapTest extends TestCase
{
    use RefreshDatabase;

    private const MV = 3_000_000_000;    // €30M
    private const CLAUSE = 500_000_000;  // €5M — far below any uncapped offer

    private TransferService $transferService;
    private Game $game;
    private Team $buyer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->transferService = app(TransferService::class);

        Competition::factory()->league()->create(['id' => 'ESP1', 'name' => 'LaLiga', 'country' => 'ES']);

        $user = User::factory()->create();
        $userTeam = Team::factory()->create(['name' => 'User Team', 'country' => 'ES']);
        $this->buyer = Team::factory()->create(['name' => 'Buyer', 'country' => 'ES']);

        $this->game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $userTeam->id,
            'competition_id' => 'ESP1',
            'country' => 'ES',
            'current_date' => '2025-08-01',
            'release_clauses_enabled' => true,
        ]);

        // Give the buyer a small roster so desireScore has something to work with.
        GamePlayer::factory()->count(3)->create([
            'game_id' => $this->game->id,
            'team_id' => $this->buyer->id,
        ]);
    }

    public function test_unsolicited_offer_is_capped_at_the_release_clause(): void
    {
        $offer = $this->createOffer($this->playerWithClause(), TransferOffer::TYPE_UNSOLICITED);

        $this->assertSame(self::CLAUSE, $offer->transfer_fee, 'Unsolicited offer must not exceed the clause.');
    }

    public function test_listed_offer_is_capped_at_the_release_clause(): void
    {
        $offer = $this->createOffer($this->playerWithClause(), TransferOffer::TYPE_LISTED);

        $this->assertSame(self::CLAUSE, $offer->transfer_fee, 'Listed-player offer must not exceed the clause.');
    }

    public function test_offer_is_not_capped_when_the_feature_is_disabled(): void
    {
        $this->game->update(['release_clauses_enabled' => false]);

        $offer = $this->createOffer($this->playerWithClause(), TransferOffer::TYPE_UNSOLICITED);

        $this->assertGreaterThan(self::CLAUSE, $offer->transfer_fee, 'Flag-off offers price off market value, ignoring the clause.');
    }

    public function test_offer_is_not_capped_when_the_player_has_no_clause(): void
    {
        $offer = $this->createOffer($this->playerWithClause(clause: null), TransferOffer::TYPE_UNSOLICITED);

        $this->assertGreaterThan(self::CLAUSE, $offer->transfer_fee, 'A clause-less player is priced off market value.');
    }

    private function playerWithClause(?int $clause = self::CLAUSE): GamePlayer
    {
        return GamePlayer::factory()->create([
            'game_id' => $this->game->id,
            'team_id' => $this->game->team_id,
            'market_value_cents' => self::MV,
            'release_clause' => $clause,
        ]);
    }

    private function createOffer(GamePlayer $player, string $offerType): TransferOffer
    {
        $method = new \ReflectionMethod(TransferService::class, 'createOffer');
        $method->setAccessible(true);

        return $method->invoke($this->transferService, $player, $this->buyer, $offerType);
    }
}
