<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Models\TransferOffer;
use App\Models\User;
use App\Modules\Transfer\Services\ScoutingService;
use App\Modules\Transfer\Services\TransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Covers the desire-driven AI buyer behaviour for a player the user is selling:
 * a club that needs the player pays a real premium above market; a deep-squad
 * club with no need won't be talked into a premium. Because a club that has
 * tabled a bid has revealed it can afford that bid, willingness is floored at the
 * club's own bid (and at market value when the squad-value ceiling can afford it):
 * the buyer never withdraws rather than negotiate modestly up from its own anchor.
 * Only a genuinely cash-constrained club settles below market.
 */
class CounterOfferDesireTest extends TestCase
{
    use RefreshDatabase;

    private ScoutingService $scoutingService;
    private Game $game;
    private Team $userTeam;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scoutingService = app(ScoutingService::class);

        $user = User::factory()->create();
        $this->userTeam = Team::factory()->create();
        $competition = Competition::factory()->league()->create(['id' => 'ESP1', 'name' => 'LaLiga']);

        $this->game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $this->userTeam->id,
            'competition_id' => $competition->id,
            'current_date' => '2025-08-01',
        ]);
    }

    /** The user's player up for sale — a €10M central midfielder, age 27 (neutral age modifier). */
    private function target(int $overall, int $marketValueCents = 10_000_000_00): GamePlayer
    {
        return GamePlayer::factory()->create([
            'game_id' => $this->game->id,
            'team_id' => $this->userTeam->id,
            'position' => 'Central Midfield',
            'overall_score' => $overall,
            'date_of_birth' => '1998-01-01',
            'market_value_cents' => $marketValueCents,
        ]);
    }

    /** A buyer club with $count players at the given position/ability/value. */
    private function buyer(string $position, int $overall, int $count, int $valueCents): Team
    {
        $team = Team::factory()->create();

        for ($i = 0; $i < $count; $i++) {
            GamePlayer::factory()->create([
                'game_id' => $this->game->id,
                'team_id' => $team->id,
                'position' => $position,
                'overall_score' => $overall,
                'market_value_cents' => $valueCents,
            ]);
        }

        return $team;
    }

    private function offer(GamePlayer $player, Team $buyer, int $transferFeeCents): TransferOffer
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
            'expires_at' => '2025-08-15',
            'game_date' => '2025-08-01',
        ]);
    }

    public function test_low_need_deep_squad_buyer_rejects_above_market_counter(): void
    {
        // Deep at midfield (8) and the target wouldn't improve them → desire ≈ 0.08,
        // no premium. The club is solvent (€80M squad) and bid below market, so its
        // willingness floors at market value (≈ €10M) — but it still refuses to be
        // pushed to a clear premium. A 1.5× MV ask is well beyond the (jittered)
        // walk-away point and is rejected.
        $player = $this->target(overall: 50);
        $buyer = $this->buyer('Central Midfield', 70, 8, 10_000_000_00);
        $offer = $this->offer($player, $buyer, 9_000_000_00); // opened below market

        $result = $this->scoutingService->evaluateCounterOffer($offer, 15_000_000_00, $this->game);

        $this->assertSame('rejected', $result['result']);
    }

    public function test_high_need_thin_squad_buyer_pays_a_premium(): void
    {
        // No midfielders and the target is a clear upgrade → desire ≈ 0.93,
        // willingness ≈ 1.45× MV. A €13M (1.3× MV) ask is accepted.
        $player = $this->target(overall: 85);
        $buyer = $this->buyer('Centre-Forward', 60, 10, 10_000_000_00);
        $offer = $this->offer($player, $buyer, 11_000_000_00);

        $result = $this->scoutingService->evaluateCounterOffer($offer, 13_000_000_00, $this->game);

        $this->assertSame('accepted', $result['result']);
    }

    public function test_affordability_clamp_caps_willingness_for_a_cash_poor_buyer(): void
    {
        // Even at max desire, a club whose entire squad is worth €30M won't pay
        // €10M for one player: the 0.25 squad-value clamp caps willingness at
        // €7.5M. (Such a club would normally be filtered out before bidding;
        // here we drive evaluateCounterOffer directly to exercise the clamp.)
        $player = $this->target(overall: 85);
        $buyer = $this->buyer('Centre-Forward', 60, 3, 10_000_000_00); // €30M squad
        $offer = $this->offer($player, $buyer, 7_000_000_00);

        $result = $this->scoutingService->evaluateCounterOffer($offer, 10_000_000_00, $this->game);

        $this->assertSame('rejected', $result['result']);
    }

    public function test_low_desire_solvent_buyer_that_bid_at_market_counters_modest_over_bid(): void
    {
        // The player-facing complaint: a club bids at market value, the user counters
        // ~10% higher (still below the player's real value), and the AI walks away.
        // Here the buyer is deep at the position with no upgrade (desire ≈ 0.08) and a
        // cheap squad, so its affordability ceiling (0.25 × €20M = €5M) sits below its
        // own €10M bid. Old behaviour: no bid floor for low desire → willingness
        // collapses to €5M → any counter is rejected. New behaviour: the ceiling can't
        // cut below a bid the club already made and willingness floors at the bid, so a
        // modest over-bid is negotiated, not refused.
        $player = $this->target(overall: 60);
        $buyer = $this->buyer('Central Midfield', 75, 8, 2_500_000_00); // €20M squad
        $offer = $this->offer($player, $buyer, 10_000_000_00); // bid == market value

        $result = $this->scoutingService->evaluateCounterOffer($offer, 11_000_000_00, $this->game);

        $this->assertSame('countered', $result['result']);
        $this->assertGreaterThan($offer->transfer_fee, $result['counter_amount']);
    }

    public function test_buyer_never_withdraws_below_its_own_bid(): void
    {
        // A low-desire buyer that opened *above* market (€12M bid vs €10M MV). Its
        // desire-driven willingness (≈ market) and cheap-squad ceiling both sit below
        // the bid, so old behaviour would reject any counter. The bid floor guarantees
        // the club engages on a small over-bid rather than withdrawing from its own
        // anchor — the outcome is accept or counter, never reject.
        $player = $this->target(overall: 60);
        $buyer = $this->buyer('Central Midfield', 75, 8, 2_500_000_00); // €20M squad
        $offer = $this->offer($player, $buyer, 12_000_000_00); // bid above market value

        $result = $this->scoutingService->evaluateCounterOffer($offer, 12_500_000_00, $this->game);

        $this->assertContains($result['result'], ['accepted', 'countered']);
        $this->assertNotSame('rejected', $result['result']);
    }

    public function test_opening_price_is_below_market_for_a_low_desire_buyer(): void
    {
        // Drive calculateOfferPrice directly (reflection) so the assertion is
        // about the price curve, not the unrelated buyer-eligibility pipeline.
        $player = $this->target(overall: 50);
        $buyer = $this->buyer('Central Midfield', 70, 3, 10_000_000_00);

        $price = $this->openingPrice($player, desire: 0.05, buyerTeamId: $buyer->id);

        $this->assertLessThan($player->market_value_cents, $price);
    }

    public function test_opening_price_exceeds_market_for_a_high_desire_buyer(): void
    {
        $player = $this->target(overall: 85);
        $buyer = $this->buyer('Centre-Forward', 60, 3, 10_000_000_00);

        $price = $this->openingPrice($player, desire: 0.95, buyerTeamId: $buyer->id);

        $this->assertGreaterThan($player->market_value_cents, $price);
    }

    private function openingPrice(GamePlayer $player, float $desire, string $buyerTeamId): int
    {
        $transferService = app(TransferService::class);
        $method = new ReflectionMethod($transferService, 'calculateOfferPrice');
        $method->setAccessible(true);

        return $method->invoke($transferService, $player, $desire, $buyerTeamId);
    }
}
