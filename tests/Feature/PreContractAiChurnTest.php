<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GameNotification;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Models\TransferOffer;
use App\Models\User;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Transfer\Services\AITransferMarketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A player the user has signed on a pre-contract must not be sold out from
 * under him by AI churn.
 *
 * The deal only completes while the player is still at the club that agreed to
 * sell him — TransferCompletionService re-asserts that at season end — so an AI
 * club buying him mid-season silently kills a signing the user has already
 * committed wages to. It is not a rare case either: a pre-contract can only
 * target a player in his final contract year, and scoreClearingCandidate ranks
 * exactly those highest, so the AI is most motivated to sell precisely the
 * players the user has locked in.
 *
 * The lock rides on $alreadyTransferredSet, which every AI sell path already
 * honours, the same way a paid release clause is protected. These tests drive
 * loadTransferContext directly (as ReleaseClauseAITransferTest does for the
 * clause recompute) so the assertion is deterministic rather than riding on the
 * market's random candidate selection.
 */
class PreContractAiChurnTest extends TestCase
{
    use RefreshDatabase;

    private AITransferMarketService $service;
    private Game $game;
    private Team $sellingClub;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(AITransferMarketService::class);

        $user = User::factory()->create();
        $userTeam = Team::factory()->create(['name' => 'User Team']);
        $this->sellingClub = Team::factory()->create(['name' => 'Selling Club']);

        Competition::factory()->league()->create(['id' => 'ESP1', 'name' => 'LaLiga']);

        $this->game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $userTeam->id,
            'competition_id' => 'ESP1',
            'season' => '2026',
            'current_date' => '2027-02-15', // inside the pre-contract window
        ]);
    }

    public function test_a_pre_contracted_player_is_locked_against_ai_churn(): void
    {
        $target = $this->finalYearPlayerAtSellingClub();
        $this->preContract($target, TransferOffer::STATUS_AGREED);

        $this->assertTrue(
            $this->isLocked($target),
            'An agreed pre-contract must exclude the player from every AI sell path.',
        );
    }

    public function test_the_lock_is_specific_to_players_the_user_has_locked_in(): void
    {
        // Same club, same final-year contract, no pre-contract: the AI must
        // still be free to sell him, or this would freeze the whole market.
        $untouched = $this->finalYearPlayerAtSellingClub();

        $this->assertFalse(
            $this->isLocked($untouched),
            'A player with no agreed deal stays available to AI churn.',
        );
    }

    public function test_a_pending_pre_contract_does_not_lock_the_player(): void
    {
        // Nothing is agreed yet — the player has not accepted, so the user has
        // committed nothing and the AI is entitled to sell him.
        $target = $this->finalYearPlayerAtSellingClub();
        $this->preContract($target, TransferOffer::STATUS_PENDING);

        $this->assertFalse(
            $this->isLocked($target),
            'Only an agreed pre-contract locks a player.',
        );
    }

    public function test_a_failed_pre_contract_is_reported_as_a_pre_contract(): void
    {
        // The generic "agreed move" wording lands months after the deal was
        // struck and never names the mechanic, so a failed pre-contract read as
        // an unrelated transfer and the player just appeared never to arrive.
        $target = $this->finalYearPlayerAtSellingClub();

        app(NotificationService::class)->notifyTransferFellThrough(
            $this->game,
            $target,
            $this->sellingClub,
            wasPreContract: true,
        );

        // Notifications are UUID-keyed with no wall-clock timestamps, so there
        // is no meaningful ordering to take a "latest" from — this test creates
        // exactly one.
        $notification = GameNotification::where('game_id', $this->game->id)->sole();

        $this->assertNotNull($notification);
        $this->assertSame(
            __('notifications.pre_contract_failed_title', ['player' => $target->name]),
            $notification->title,
        );
        $this->assertStringContainsString($this->sellingClub->name, $notification->message);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    /**
     * Whether the AI market treats the player as untouchable, i.e. his id is in
     * the $alreadyTransferredSet that buildSellOffers filters against.
     */
    private function isLocked(GamePlayer $player): bool
    {
        $load = new \ReflectionMethod(AITransferMarketService::class, 'loadTransferContext');
        $load->setAccessible(true);
        $context = $load->invoke($this->service, $this->game, 'winter');

        return isset($context['alreadyTransferredSet'][$player->id]);
    }

    private function finalYearPlayerAtSellingClub(): GamePlayer
    {
        return GamePlayer::factory()->forGame($this->game)->forTeam($this->sellingClub)->create([
            'date_of_birth' => '1996-01-01',
            'contract_until' => '2027-06-30',
            'annual_wage' => 100_000_000,
            'market_value_cents' => 1_000_000_000,
        ]);
    }

    private function preContract(GamePlayer $player, string $status): TransferOffer
    {
        return TransferOffer::create([
            'game_id' => $this->game->id,
            'game_player_id' => $player->id,
            'offering_team_id' => $this->game->team_id,
            'selling_team_id' => $player->owningTeamId(),
            'offer_type' => TransferOffer::TYPE_PRE_CONTRACT,
            'direction' => TransferOffer::DIRECTION_INCOMING,
            'transfer_fee' => 0,
            'offered_wage' => 150_000_000,
            'offered_years' => 3,
            'status' => $status,
            'expires_at' => $this->game->current_date,
            'game_date' => $this->game->current_date,
        ]);
    }
}
