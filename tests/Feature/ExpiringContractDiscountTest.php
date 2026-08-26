<?php

namespace Tests\Feature;

use App\Models\ClubProfile;
use App\Models\Competition;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Models\TransferOffer;
use App\Models\User;
use App\Modules\Transfer\Services\ScoutingService;
use App\Modules\Transfer\Services\TransferService;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use ReflectionMethod;
use Tests\TestCase;

/**
 * A contract running down costs the selling club its leverage, and a player
 * pushing for the move costs it the rest. Both sides of the market move on the
 * same curve: the price an AI club asks the user, and the price an AI club will
 * pay for one of the user's own players.
 *
 * The motivating case is the real-world signing of a Ballon d'Or-calibre
 * midfielder in his final year for roughly half his market value. Before this,
 * the asking-price model computed ~0.40× market value for exactly that player
 * and then discarded it: a flat `importance >= 0.5 ? 1.0` floor clamped every
 * key player back to full market value, so the better the player, the more
 * certainly the contract discount was thrown away.
 *
 * Fixtures pin `contract_until` relative to the game's current_date rather than
 * using the factory default, which is relative to wall-clock now() and would
 * otherwise make these multipliers drift from year to year.
 */
class ExpiringContractDiscountTest extends TestCase
{
    use RefreshDatabase;

    /** €50M — a star's market value. */
    private const MV = 5_000_000_000;

    /** Config default: the price floor at zero leverage. */
    private const EXPIRING_FLOOR = 0.65;

    private ScoutingService $scoutingService;
    private TransferService $transferService;
    private Competition $competition;
    private Game $game;
    private Team $userTeam;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scoutingService = app(ScoutingService::class);
        $this->transferService = app(TransferService::class);

        $this->competition = Competition::factory()->league()->create(['id' => 'ESP1', 'name' => 'LaLiga']);

        // The user's club is elite, so a player joining it is taking a clear step
        // up — the reputation half of the keenness signal.
        $this->userTeam = Team::factory()->create();
        ClubProfile::create(['team_id' => $this->userTeam->id, 'reputation_level' => ClubProfile::REPUTATION_ELITE]);

        $this->game = Game::factory()->create([
            'user_id' => User::factory()->create()->id,
            'team_id' => $this->userTeam->id,
            'competition_id' => $this->competition->id,
            'current_date' => '2026-08-01',
        ]);
    }

    // =========================================
    // SELL SIDE — what an AI club asks the user
    // =========================================

    public function test_expiring_key_player_is_discounted_to_the_expiring_floor(): void
    {
        // Final year (11 months left), best player at his club, age 30 (neutral
        // age modifier). Leverage is zero, so the raw model wants 0.8 × 0.5 × 1.0
        // = 0.40× MV and the decayed floor catches it at 0.65× MV. Before the
        // change this player was pinned at a flat 1.0× MV.
        $player = $this->starAt(ClubProfile::REPUTATION_CONTINENTAL, contractUntil: '2027-06-30');

        $price = $this->askingPrice($player);

        $this->assertSame(Money::roundPrice((int) (self::MV * self::EXPIRING_FLOOR)), $price);
        $this->assertLessThan(self::MV, $price, 'An expiring star must cost less than market value.');
    }

    public function test_key_player_on_a_long_contract_still_commands_a_premium(): void
    {
        // The guard against the discount leaking into normal valuations: with five
        // years left, leverage is full, the importance premium survives intact and
        // the club asks 1.2 (importance) × 1.2 (contract) = 1.44× MV, exactly as
        // it did before.
        $player = $this->starAt(ClubProfile::REPUTATION_CONTINENTAL, contractUntil: '2031-06-30');

        $price = $this->askingPrice($player);

        $this->assertSame(Money::roundPrice((int) (self::MV * 1.44)), $price);
        $this->assertGreaterThan(self::MV, $price);
    }

    public function test_a_player_keen_on_the_move_is_cheaper_than_one_who_is_not(): void
    {
        // Just under two years left: enough leverage remaining for keenness to
        // erode (an expiring contract is already at zero, where keenness has
        // nothing left to take). The same player is priced for two different
        // buyers — an elite club (a clear step up, so he wants it) and a
        // same-tier club (no step, so he is indifferent). Nothing but the
        // willingness label differs between the two calls.
        $player = $this->midSquadPlayerAt(ClubProfile::REPUTATION_MODEST, contractUntil: '2028-06-30');

        $keenBuyer = $this->game;                                        // elite
        $indifferentBuyer = $this->rivalGame(ClubProfile::REPUTATION_MODEST); // same tier

        $keenPrice = $this->askingPrice($player, $keenBuyer);
        $indifferentPrice = $this->askingPrice($player, $indifferentBuyer);

        $this->assertLessThan(
            $indifferentPrice,
            $keenPrice,
            'A player angling for the move should cost his club its remaining leverage.'
        );
    }

    public function test_keenness_is_neutral_when_there_is_no_buying_club_in_context(): void
    {
        // Keenness is relative to who is asking, so a call with no buyer must
        // behave exactly as an indifferent buyer does — no silent discount.
        $player = $this->midSquadPlayerAt(ClubProfile::REPUTATION_MODEST, contractUntil: '2028-06-30');

        $withoutBuyer = $this->scoutingService->calculateAskingPrice($player, $this->game->current_date);
        $indifferentPrice = $this->askingPrice($player, $this->rivalGame(ClubProfile::REPUTATION_MODEST));

        $this->assertSame($indifferentPrice, $withoutBuyer);
    }

    public function test_the_deal_actually_closes_below_market_value(): void
    {
        // End to end: the discounted ask is not just displayed, it is accepted.
        // A key player needs to clear 1.05× the ask to close (evaluateBid's
        // key-player threshold — 1.06 here so float division can't land a hair
        // under it), and even then the fee is still well under market value.
        $player = $this->starAt(ClubProfile::REPUTATION_CONTINENTAL, contractUntil: '2027-06-30');
        $bid = (int) ceil($this->askingPrice($player) * 1.06);

        $result = $this->scoutingService->evaluateBid($player, $bid, $this->game);

        $this->assertSame('accepted', $result['result']);
        $this->assertLessThan(self::MV, $bid, 'The closing fee must still be below market value.');
    }

    // =========================================
    // BUY SIDE — what an AI club pays the user
    // =========================================

    public function test_ai_clubs_open_lower_for_an_expiring_player(): void
    {
        // Same player, same buyer, same desire — only the contract differs. The
        // ±0.03 opening jitter is an order of magnitude smaller than the 35% gap
        // between full and zero leverage, so this comparison is stable.
        $expiring = $this->userPlayer(contractUntil: '2027-06-30');
        $secured = $this->userPlayer(contractUntil: '2031-06-30');
        $buyer = $this->buyerTeam('Central Midfield', overall: 60, count: 4, valueCents: self::MV);

        $expiringPrice = $this->openingPrice($expiring, 0.5, $buyer->id);
        $securedPrice = $this->openingPrice($secured, 0.5, $buyer->id);

        $this->assertLessThan($securedPrice, $expiringPrice);
    }

    public function test_ai_buyer_is_not_forced_up_to_market_value_for_an_expiring_player(): void
    {
        // A solvent, low-desire buyer facing the same 0.9× MV counter. On a long
        // contract its willingness floors at market value and it accepts; on an
        // expiring one it can afford to wait, so the same ask is refused. This is
        // the user feeling the pressure from the other side.
        $buyer = $this->buyerTeam('Central Midfield', overall: 90, count: 8, valueCents: self::MV);
        $ask = (int) (self::MV * 0.85);
        $bid = (int) (self::MV * 0.5);

        $secured = $this->userPlayer(contractUntil: '2031-06-30');
        $securedResult = $this->scoutingService->evaluateCounterOffer(
            $this->offerFor($secured, $buyer, $bid),
            $ask,
            $this->game,
        );

        $expiring = $this->userPlayer(contractUntil: '2027-06-30');
        $expiringResult = $this->scoutingService->evaluateCounterOffer(
            $this->offerFor($expiring, $buyer, $bid),
            $ask,
            $this->game,
        );

        // Secured: willingness floors at market value, so 0.85x MV is comfortably
        // inside it. Expiring: willingness floors at 0.65x MV instead, putting the
        // same ask out of reach. Asserted as "not accepted" rather than a specific
        // refusal, because the walk-away point carries a deliberate per-offer wobble.
        $this->assertSame('accepted', $securedResult['result']);
        $this->assertNotSame('accepted', $expiringResult['result']);
    }

    public function test_ai_buyer_never_withdraws_below_its_own_bid_even_when_expiring(): void
    {
        // The pre-existing guarantee must survive the decay: a club that has
        // tabled a bid has revealed it can afford that bid, so it negotiates up
        // from its own anchor rather than walking away.
        $buyer = $this->buyerTeam('Central Midfield', overall: 90, count: 8, valueCents: 50_000_000);
        $expiring = $this->userPlayer(contractUntil: '2027-06-30');
        $bid = (int) (self::MV * 1.2); // opened above market despite the short deal

        $result = $this->scoutingService->evaluateCounterOffer(
            $this->offerFor($expiring, $buyer, $bid),
            (int) ($bid * 1.04),
            $this->game,
        );

        $this->assertNotSame('rejected', $result['result']);
    }

    // =========================================
    // FIXTURES
    // =========================================

    private function askingPrice(GamePlayer $player, ?Game $buyingClubGame = null): int
    {
        return $this->scoutingService->calculateAskingPrice(
            $player,
            $this->game->current_date,
            null,
            $buyingClubGame ?? $this->game,
        );
    }

    /** Drive the private opening-price curve directly, as CounterOfferDesireTest does. */
    private function openingPrice(GamePlayer $player, float $desire, string $buyerTeamId): int
    {
        $method = new ReflectionMethod(TransferService::class, 'calculateOfferPrice');
        $method->setAccessible(true);

        return $method->invoke($this->transferService, $player, $desire, $buyerTeamId);
    }

    /** A second manager's club, used as an alternative buyer to vary keenness. */
    private function rivalGame(string $reputation): Game
    {
        $team = Team::factory()->create();
        ClubProfile::create(['team_id' => $team->id, 'reputation_level' => $reputation]);

        $game = Game::factory()->create([
            'user_id' => User::factory()->create()->id,
            'team_id' => $team->id,
            'competition_id' => $this->competition->id,
            'current_date' => '2026-08-01',
        ]);

        return $game->load('team.clubProfile');
    }

    /** An AI-owned club with a squad, so importance and reputation both resolve. */
    private function sellingTeam(string $reputation): Team
    {
        $team = Team::factory()->create();
        ClubProfile::create(['team_id' => $team->id, 'reputation_level' => $reputation]);

        return $team;
    }

    /** The best player in his squad — importance 1.0, so the key-player floor applies. */
    private function starAt(string $reputation, string $contractUntil): GamePlayer
    {
        $team = $this->sellingTeam($reputation);
        $this->squadmates($team, count: 10, overall: 60);

        return $this->player($team, overall: 90, contractUntil: $contractUntil);
    }

    /** A mid-squad player — importance 0.5, enough willingness headroom to show keenness. */
    private function midSquadPlayerAt(string $reputation, string $contractUntil): GamePlayer
    {
        $team = $this->sellingTeam($reputation);

        // Five better, five worse → rank 5 of 11 → importance 0.5.
        $this->squadmates($team, count: 5, overall: 90);
        $this->squadmates($team, count: 5, overall: 60);

        return $this->player($team, overall: 75, contractUntil: $contractUntil);
    }

    private function userPlayer(string $contractUntil): GamePlayer
    {
        return $this->player($this->userTeam, overall: 80, contractUntil: $contractUntil);
    }

    private function player(Team $team, int $overall, string $contractUntil): GamePlayer
    {
        $player = GamePlayer::factory()->create([
            'game_id' => $this->game->id,
            'team_id' => $team->id,
            'position' => 'Central Midfield',
            'overall_score' => $overall,
            'date_of_birth' => '1996-01-01', // age 30 → neutral age modifier
            'market_value_cents' => self::MV,
            'contract_until' => $contractUntil,
        ]);

        return $player->load('team.clubProfile', 'game');
    }

    private function squadmates(Team $team, int $count, int $overall): void
    {
        for ($i = 0; $i < $count; $i++) {
            GamePlayer::factory()->create([
                'game_id' => $this->game->id,
                'team_id' => $team->id,
                'position' => 'Central Midfield',
                'overall_score' => $overall,
                'market_value_cents' => self::MV,
                'contract_until' => '2031-06-30',
            ]);
        }
    }

    /** A buyer deep at the position with no upgrade on offer → low desire. */
    private function buyerTeam(string $position, int $overall, int $count, int $valueCents): Team
    {
        $team = Team::factory()->create();
        $this->squadmatesFor($team, $position, $overall, $count, $valueCents);

        return $team;
    }

    private function squadmatesFor(Team $team, string $position, int $overall, int $count, int $valueCents): void
    {
        for ($i = 0; $i < $count; $i++) {
            GamePlayer::factory()->create([
                'game_id' => $this->game->id,
                'team_id' => $team->id,
                'position' => $position,
                'overall_score' => $overall,
                'market_value_cents' => $valueCents,
                'contract_until' => '2031-06-30',
            ]);
        }
    }

    private function offerFor(GamePlayer $player, Team $buyer, int $transferFeeCents): TransferOffer
    {
        return TransferOffer::create([
            'id' => Str::uuid()->toString(),
            'game_id' => $this->game->id,
            'game_player_id' => $player->id,
            'offering_team_id' => $buyer->id,
            'offer_type' => TransferOffer::TYPE_UNSOLICITED,
            'direction' => TransferOffer::DIRECTION_OUTGOING,
            'transfer_fee' => $transferFeeCents,
            'status' => TransferOffer::STATUS_PENDING,
            'expires_at' => '2026-08-15',
            'game_date' => '2026-08-01',
        ]);
    }
}
