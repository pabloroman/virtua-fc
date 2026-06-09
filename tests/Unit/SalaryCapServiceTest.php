<?php

namespace Tests\Unit;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GameFinances;
use App\Models\GameInvestment;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Models\TransferOffer;
use App\Models\User;
use App\Modules\Finance\Services\SalaryCapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalaryCapServiceTest extends TestCase
{
    use RefreshDatabase;

    private SalaryCapService $service;
    private Competition $competition;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(SalaryCapService::class);
        config()->set('finances.wage_cap_ratio', 0.70);

        $this->competition = Competition::factory()->league()->create([
            'id' => 'ESP1',
            'name' => 'LaLiga',
        ]);
    }

    public function test_cap_is_ratio_times_projected_recurring_revenue(): void
    {
        $game = $this->makeGame(projectedRevenue: 1_000_000_000); // €10M revenue

        // €10M × 0.70 = €7M
        $this->assertSame(700_000_000, $this->service->cap($game));
    }

    public function test_committed_wage_bill_sums_squad_pending_and_agreed_offers(): void
    {
        $game = $this->makeGame(projectedRevenue: 1_000_000_000);

        // Squad: one at €1M, one with a pending renewal of €2.5M (overrides €2M)
        $this->squadPlayer($game, annualWage: 100_000_000);
        $this->squadPlayer($game, annualWage: 200_000_000, pendingWage: 250_000_000);

        // An agreed-but-uncompleted incoming free-agent signing at €0.5M
        $this->agreedIncomingOffer($game, offeredWage: 50_000_000);

        // €1M + €2.5M (pending) + €0.5M = €4M
        $this->assertSame(400_000_000, $this->service->committedWageBill($game));
    }

    public function test_committed_wage_bill_counts_agreed_loan_ins(): void
    {
        $game = $this->makeGame(projectedRevenue: 1_000_000_000);
        $this->squadPlayer($game, annualWage: 100_000_000); // €1M

        // An agreed-but-uncompleted loan-in commits the player's full wage.
        $target = GamePlayer::factory()->create([
            'game_id' => $game->id,
            'team_id' => null,
            'annual_wage' => 80_000_000,
        ]);
        TransferOffer::create([
            'game_id' => $game->id,
            'game_player_id' => $target->id,
            'offering_team_id' => $game->team_id,
            'offer_type' => TransferOffer::TYPE_LOAN_IN,
            'direction' => TransferOffer::DIRECTION_INCOMING,
            'transfer_fee' => 0,
            'offered_wage' => 80_000_000,
            'status' => TransferOffer::STATUS_AGREED,
            'expires_at' => $game->current_date,
            'game_date' => $game->current_date,
            'resolved_at' => $game->current_date,
        ]);

        // €1M squad + €0.8M agreed loan-in = €1.8M
        $this->assertSame(180_000_000, $this->service->committedWageBill($game));
    }

    public function test_remaining_room_is_cap_minus_committed_bill(): void
    {
        $game = $this->makeGame(projectedRevenue: 1_000_000_000); // cap €7M
        $this->squadPlayer($game, annualWage: 500_000_000); // €5M

        $this->assertSame(200_000_000, $this->service->remainingRoom($game)); // €2M
    }

    public function test_can_commit_wage_within_and_over_cap(): void
    {
        $game = $this->makeGame(projectedRevenue: 1_000_000_000); // cap €7M
        $this->squadPlayer($game, annualWage: 500_000_000); // €5M committed, €2M room

        $this->assertTrue($this->service->canCommitWage($game, 200_000_000));  // €2M fits exactly
        $this->assertFalse($this->service->canCommitWage($game, 200_000_001)); // €1 over blocks
    }

    public function test_over_cap_locks_the_market_for_every_move(): void
    {
        $game = $this->makeGame(projectedRevenue: 1_000_000_000); // cap €7M
        $player = $this->squadPlayer($game, annualWage: 800_000_000); // €8M committed → over

        $this->assertTrue($this->service->isOverCap($game));

        // No new commitment passes while over the cap — not a tiny signing…
        $this->assertFalse($this->service->canCommitWage($game, 1));
        // …and not even a wage-cut renewal that lowers the bill (the recovery
        // path is selling, not renewing down).
        $freed = $this->service->effectiveWageFor($player);
        $this->assertFalse($this->service->canCommitWage($game, 100_000_000, $freed));
    }

    public function test_block_message_uses_locked_variant_when_over_cap(): void
    {
        $game = $this->makeGame(projectedRevenue: 1_000_000_000); // cap €7M
        $this->squadPlayer($game, annualWage: 800_000_000); // over

        $this->assertSame(
            __('messages.salary_cap_locked'),
            $this->service->blockMessage($game, 'Some Player', 100_000_000),
        );
    }

    public function test_renewal_freed_wage_charges_only_the_increase(): void
    {
        $game = $this->makeGame(projectedRevenue: 1_000_000_000); // cap €7M
        $player = $this->squadPlayer($game, annualWage: 650_000_000); // €6.5M committed

        $newWage = 680_000_000; // €6.8M
        $freed = $this->service->effectiveWageFor($player);

        // Without crediting the replaced wage the raise would blow past the cap…
        $this->assertFalse($this->service->canCommitWage($game, $newWage));
        // …but a renewal replaces the current wage, so only the increase counts.
        $this->assertTrue($this->service->canCommitWage($game, $newWage, $freed));
    }

    public function test_status_reflects_usage(): void
    {
        $game = $this->makeGame(projectedRevenue: 1_000_000_000); // cap €7M

        $healthy = $this->squadPlayer($game, annualWage: 350_000_000); // 50%
        $this->assertSame('healthy', $this->service->status($game));

        $healthy->update(['annual_wage' => 630_000_000]); // 90% → warning
        $this->assertSame('warning', $this->service->status($game));

        $healthy->update(['annual_wage' => 800_000_000]); // 114% → over
        $this->assertSame('over', $this->service->status($game));
    }

    public function test_hoarded_cash_does_not_raise_the_wage_ceiling(): void
    {
        // The exploit: a club at its cap with huge one-time cash should still be
        // unable to commit a single extra euro of wages.
        $game = $this->makeGame(projectedRevenue: 1_000_000_000, carriedSurplus: 50_000_000_000); // €500M hoarded
        $this->squadPlayer($game, annualWage: 700_000_000); // exactly at the €7M cap

        GameInvestment::create([
            'game_id' => $game->id,
            'season' => $game->season,
            'transfer_budget' => 50_000_000_000, // €500M of transfer cash
        ]);

        $this->assertSame(0, $this->service->remainingRoom($game));
        $this->assertFalse($this->service->canCommitWage($game, 1));
    }

    public function test_cap_includes_the_trailing_trading_allowance(): void
    {
        $game = $this->makeGame(projectedRevenue: 1_000_000_000); // €10M recurring

        // A €4M trailing player-trading allowance ("plusvalías") widens the base.
        GameFinances::where('game_id', $game->id)
            ->update(['projected_trading_allowance' => 400_000_000]);

        // (€10M + €4M) × 0.70 = €9.8M
        $this->assertSame(980_000_000, $this->service->cap($game));

        // The room attributable to plusvalías is €4M × 0.70 = €2.8M.
        $this->assertSame(280_000_000, $this->service->tradingAllowanceRoom($game));
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function makeGame(int $projectedRevenue, int $carriedSurplus = 0): Game
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();

        $game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'competition_id' => $this->competition->id,
            'season' => '2024',
            'current_date' => '2025-01-15',
        ]);

        GameFinances::create([
            'game_id' => $game->id,
            'season' => '2024',
            'projected_total_revenue' => $projectedRevenue,
            'projected_wages' => 0,
            'carried_surplus' => $carriedSurplus,
        ]);

        return $game;
    }

    private function squadPlayer(Game $game, int $annualWage, ?int $pendingWage = null): GamePlayer
    {
        return GamePlayer::factory()->create([
            'game_id' => $game->id,
            'team_id' => $game->team_id,
            'annual_wage' => $annualWage,
            'pending_annual_wage' => $pendingWage,
        ]);
    }

    private function agreedIncomingOffer(Game $game, int $offeredWage): TransferOffer
    {
        $target = GamePlayer::factory()->create([
            'game_id' => $game->id,
            'team_id' => null, // free agent target
        ]);

        return TransferOffer::create([
            'game_id' => $game->id,
            'game_player_id' => $target->id,
            'offering_team_id' => $game->team_id,
            'selling_team_id' => null,
            'offer_type' => TransferOffer::TYPE_USER_BID,
            'direction' => TransferOffer::DIRECTION_INCOMING,
            'transfer_fee' => 0,
            'offered_wage' => $offeredWage,
            'status' => TransferOffer::STATUS_AGREED,
            'expires_at' => $game->current_date,
            'game_date' => $game->current_date,
            'resolved_at' => $game->current_date,
        ]);
    }
}
