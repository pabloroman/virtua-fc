<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GameInvestment;
use App\Models\GameNotification;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Models\TransferOffer;
use App\Models\User;
use App\Modules\Transfer\Services\TransferCompletionService;
use App\Modules\Transfer\Services\TransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase-2 behaviour: the user pays an AI player's release clause. The trigger
 * creates a forced, fee-agreed incoming offer that escrows the fee; the guards
 * reject ineligible targets; and completion re-asserts ownership so a player
 * churned away mid-deal can't be conjured out of his new club.
 */
class ReleaseClausePhase2Test extends TestCase
{
    use RefreshDatabase;

    private const MV = 5_000_000_000;       // €50M
    private const CLAUSE = 6_250_000_000;   // €50M × 1.25
    private const BUDGET = 10_000_000_000;  // €100M

    private TransferService $transferService;
    private Game $game;
    private Team $userTeam;
    private Team $aiTeam;

    protected function setUp(): void
    {
        parent::setUp();

        $this->transferService = app(TransferService::class);

        $user = User::factory()->create();
        $this->userTeam = Team::factory()->create(['name' => 'User Team', 'country' => 'ES']);
        $this->aiTeam = Team::factory()->create(['name' => 'AI Team', 'country' => 'ES']);

        Competition::factory()->league()->create(['id' => 'ESP1', 'name' => 'LaLiga']);

        $this->game = Game::factory()->create([
            'user_id' => $user->id,
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

    public function test_trigger_creates_a_fee_agreed_offer_that_escrows_the_clause(): void
    {
        $player = $this->aiPlayerWithClause();

        $offer = $this->transferService->triggerReleaseClause($this->game, $player);

        $this->assertSame(TransferOffer::STATUS_FEE_AGREED, $offer->status);
        $this->assertSame(TransferOffer::TYPE_USER_BID, $offer->offer_type);
        $this->assertSame(TransferOffer::DIRECTION_INCOMING, $offer->direction);
        $this->assertTrue((bool) $offer->triggered_release_clause);
        $this->assertSame(self::CLAUSE, (int) $offer->transfer_fee);
        $this->assertSame($this->userTeam->id, $offer->offering_team_id);
        $this->assertSame($this->aiTeam->id, $offer->selling_team_id);
        $this->assertGreaterThan(0, (int) $offer->offered_wage, 'Personal terms are bootstrapped from the wage demand.');

        // The fee is reserved the instant the FEE_AGREED offer exists.
        $this->assertSame(self::CLAUSE, TransferOffer::committedBudget($this->game->id));
        $this->assertSame(self::BUDGET - self::CLAUSE, $this->transferService->availableBudget($this->game));
    }

    public function test_trigger_is_blocked_when_feature_disabled(): void
    {
        $this->game->update(['release_clauses_enabled' => false]);
        $player = $this->aiPlayerWithClause();

        $this->expectException(\InvalidArgumentException::class);
        $this->transferService->triggerReleaseClause($this->game, $player);
    }

    public function test_trigger_is_blocked_for_a_player_without_a_clause(): void
    {
        $player = $this->aiPlayerWithClause(clause: null);

        $this->expectException(\InvalidArgumentException::class);
        $this->transferService->triggerReleaseClause($this->game, $player);
    }

    public function test_trigger_is_blocked_for_a_user_owned_player(): void
    {
        $player = GamePlayer::factory()->forGame($this->game)->forTeam($this->userTeam)->create([
            'market_value_cents' => self::MV,
            'release_clause' => self::CLAUSE,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->transferService->triggerReleaseClause($this->game, $player);
    }

    public function test_trigger_is_blocked_when_the_clause_exceeds_the_budget(): void
    {
        $this->game->currentInvestment->update(['transfer_budget' => self::CLAUSE - 1]);
        $player = $this->aiPlayerWithClause();

        $this->expectException(\InvalidArgumentException::class);
        $this->transferService->triggerReleaseClause($this->game, $player);
    }

    public function test_retrigger_replaces_the_offer_without_double_reserving(): void
    {
        $player = $this->aiPlayerWithClause();

        $first = $this->transferService->triggerReleaseClause($this->game, $player);
        $second = $this->transferService->triggerReleaseClause($this->game, $player);

        $this->assertSame(TransferOffer::STATUS_EXPIRED, $first->fresh()->status, 'The prior offer is expired.');
        $this->assertSame(TransferOffer::STATUS_FEE_AGREED, $second->fresh()->status);

        $live = TransferOffer::where('game_player_id', $player->id)
            ->whereIn('status', [TransferOffer::STATUS_FEE_AGREED, TransferOffer::STATUS_AGREED])
            ->count();
        $this->assertSame(1, $live, 'Only one live clause offer ever exists.');
        $this->assertSame(self::CLAUSE, TransferOffer::committedBudget($this->game->id), 'Escrow is not double-counted.');
    }

    public function test_trigger_expires_a_prior_normal_bid_for_the_same_player(): void
    {
        $player = $this->aiPlayerWithClause();

        $bid = TransferOffer::create([
            'game_id' => $this->game->id,
            'game_player_id' => $player->id,
            'offering_team_id' => $this->userTeam->id,
            'selling_team_id' => $this->aiTeam->id,
            'offer_type' => TransferOffer::TYPE_USER_BID,
            'direction' => TransferOffer::DIRECTION_INCOMING,
            'transfer_fee' => 1_000_000_00,
            'status' => TransferOffer::STATUS_PENDING,
            'negotiation_round' => 1,
            'expires_at' => '2025-08-31',
            'game_date' => '2025-08-01',
        ]);

        $this->transferService->triggerReleaseClause($this->game, $player);

        $this->assertSame(TransferOffer::STATUS_EXPIRED, $bid->fresh()->status);
    }

    public function test_completion_rejects_when_the_seller_no_longer_owns_the_player(): void
    {
        $player = $this->aiPlayerWithClause();
        $otherTeam = Team::factory()->create(['name' => 'Third Club']);

        $offer = TransferOffer::create([
            'game_id' => $this->game->id,
            'game_player_id' => $player->id,
            'offering_team_id' => $this->userTeam->id,
            'selling_team_id' => $this->aiTeam->id,
            'offer_type' => TransferOffer::TYPE_USER_BID,
            'direction' => TransferOffer::DIRECTION_INCOMING,
            'transfer_fee' => self::CLAUSE,
            'offered_wage' => 5_000_000_00,
            'offered_years' => 4,
            'status' => TransferOffer::STATUS_AGREED,
            'triggered_release_clause' => true,
            'expires_at' => '2025-08-31',
            'game_date' => '2025-08-01',
        ]);

        // AI-to-AI churn relocated the player before the deal completed.
        $player->update(['team_id' => $otherTeam->id]);

        $completed = app(TransferCompletionService::class)->completeIncomingTransfer($offer, $this->game);

        $this->assertFalse($completed);
        $this->assertSame(TransferOffer::STATUS_REJECTED, $offer->fresh()->status);
        $this->assertSame($otherTeam->id, $player->fresh()->team_id, 'The player is not conjured onto the user team.');
        $this->assertSame(self::BUDGET, (int) $this->game->currentInvestment->fresh()->transfer_budget, 'No fee is charged.');
        $this->assertDatabaseHas('game_notifications', [
            'game_id' => $this->game->id,
            'type' => GameNotification::TYPE_TRANSFER_FAILED,
        ]);
    }

    private function aiPlayerWithClause(?int $clause = self::CLAUSE): GamePlayer
    {
        return GamePlayer::factory()->forGame($this->game)->forTeam($this->aiTeam)->create([
            'market_value_cents' => self::MV,
            'release_clause' => $clause,
        ]);
    }
}
