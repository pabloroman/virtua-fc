<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GameInvestment;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Models\TransferOffer;
use App\Models\User;
use App\Modules\Transfer\Services\ScoutingService;
use App\Modules\Transfer\Services\TransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression for a budget-leak during sync fee negotiation. The seller's
 * counter is reserved at asking_price (see TransferOffer::committedBudget),
 * but the code paths that re-open or replace the active offer were adding
 * back only transfer_fee. The delta (asking_price - transfer_fee) stayed
 * reserved, so a single counter shrunk the user's apparent budget — and on
 * a bid that ate the whole budget, the user could no longer accept the
 * counter they themselves had triggered.
 */
class CounterOfferBudgetReservationTest extends TestCase
{
    use RefreshDatabase;

    private TransferService $transferService;
    private ScoutingService $scoutingService;
    private Game $game;
    private Team $userTeam;
    private Team $aiTeam;
    private GamePlayer $target;

    protected function setUp(): void
    {
        parent::setUp();

        $this->transferService = app(TransferService::class);
        $this->scoutingService = app(ScoutingService::class);

        $user = User::factory()->create();
        $this->userTeam = Team::factory()->create(['name' => 'User Team']);
        $this->aiTeam = Team::factory()->create(['name' => 'AI Team']);

        $competition = Competition::factory()->league()->create([
            'id' => 'ESP1',
            'name' => 'LaLiga',
        ]);

        // Summer window so accept paths don't get held back by the
        // closed-window branch — we only care about the budget guard here.
        $this->game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $this->userTeam->id,
            'competition_id' => $competition->id,
            'current_date' => '2025-08-01',
        ]);

        // The exact scenario the user reported: 325k budget, 300k bid,
        // 325k counter. With the bug, only 300k of the 325k was being
        // "given back" when the user looked at their available budget or
        // tried to accept the counter.
        GameInvestment::create([
            'game_id' => $this->game->id,
            'season' => $this->game->season,
            'transfer_budget' => 325_000_00,
            'scouting_tier' => 1,
        ]);

        // Tolerant-floor-safe roster on the AI side so a sale is allowed.
        $this->seedRoster($this->aiTeam);

        $this->target = GamePlayer::where('game_id', $this->game->id)
            ->where('team_id', $this->aiTeam->id)
            ->where('position', 'Centre-Forward')
            ->first();
    }

    public function test_committed_amount_reflects_asking_price_after_counter(): void
    {
        $offer = $this->makeCounteredOffer(transferFee: 300_000_00, askingPrice: 325_000_00);

        $this->assertSame(325_000_00, $offer->committedAmount(),
            'A countered offer reserves the asking_price, not the original bid.');

        $this->assertSame(325_000_00, TransferOffer::committedBudget($this->game->id),
            'Total committed budget matches the per-offer commitment.');

        $this->assertSame(0, $this->transferService->availableBudget($this->game),
            'With a 325k bid countered at 325k against a 325k budget, nothing is free.');
    }

    public function test_user_can_accept_counter_that_eats_the_entire_budget(): void
    {
        // Exactly the user's complaint: bid 300, countered at 325, budget 325.
        // Without the fix, acceptTransferFeeCounter throws bid_exceeds_budget
        // because availableBudget(0) + transfer_fee(300k) < asking_price(325k).
        $offer = $this->makeCounteredOffer(transferFee: 300_000_00, askingPrice: 325_000_00);

        $accepted = $this->transferService->acceptTransferFeeCounter($this->game, $offer);

        $this->assertSame(TransferOffer::STATUS_FEE_AGREED, $accepted->status);
        $this->assertSame(325_000_00, $accepted->transfer_fee,
            'Accepted counter promotes asking_price into transfer_fee.');
    }

    public function test_user_can_match_counter_with_a_new_bid_at_full_budget(): void
    {
        // Same negotiation, but the user re-bids 325k instead of clicking
        // accept. The guard inside negotiateTransferFeeSync used the same
        // wrong "available + transfer_fee" sum, so a matching bid was
        // rejected as over-budget.
        $this->makeCounteredOffer(transferFee: 300_000_00, askingPrice: 325_000_00);

        $result = $this->transferService->negotiateTransferFeeSync(
            $this->game, $this->target, 325_000_00, $this->scoutingService,
        );

        $this->assertContains($result['result'], ['accepted', 'countered', 'rejected'],
            'Bid at full budget must reach the AI evaluator, not get short-circuited as over-budget.');
    }

    private function makeCounteredOffer(int $transferFee, int $askingPrice): TransferOffer
    {
        return TransferOffer::create([
            'game_id' => $this->game->id,
            'game_player_id' => $this->target->id,
            'offering_team_id' => $this->userTeam->id,
            'selling_team_id' => $this->aiTeam->id,
            'offer_type' => TransferOffer::TYPE_USER_BID,
            'direction' => TransferOffer::DIRECTION_INCOMING,
            'transfer_fee' => $transferFee,
            'asking_price' => $askingPrice,
            'status' => TransferOffer::STATUS_PENDING,
            'negotiation_round' => 1,
            'expires_at' => '2025-08-31',
            'game_date' => '2025-08-01',
        ]);
    }

    private function seedRoster(Team $team): void
    {
        $positions = [
            ['Goalkeeper', 3],
            ['Centre-Back', 7],
            ['Central Midfield', 7],
            ['Centre-Forward', 5],
        ];

        foreach ($positions as [$position, $count]) {
            for ($i = 0; $i < $count; $i++) {
                GamePlayer::factory()->create([
                    'game_id' => $this->game->id,
                    'team_id' => $team->id,
                    'position' => $position,
                    'market_value_cents' => 50_000_00,
                ]);
            }
        }
    }
}
