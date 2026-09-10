<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\GameNotification;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Models\TransferOffer;
use App\Models\User;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Season\Processors\ContractExpirationProcessor;
use App\Modules\Transfer\Exceptions\LockedPlayerMovedException;
use App\Modules\Transfer\Services\TransferCompletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Tests\TestCase;

/**
 * "The user has locked this player in" is one predicate,
 * TransferOffer::locksPlayer(), and every path that moves players for a
 * reason other than completing his own deal consults it. These tests pin
 * down what the predicate means, that a mutation site honours it, and that
 * violating it is loud rather than a "transfer fell through" months later.
 */
class PlayerLockTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;
    private Team $userTeam;
    private Team $sellingTeam;
    private Team $otherTeam;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->userTeam = Team::factory()->create();
        $this->sellingTeam = Team::factory()->create();
        $this->otherTeam = Team::factory()->create();

        $this->game = Game::factory()->forTeam($this->userTeam)->create([
            'user_id' => $user->id,
            'season' => '2026',
            'current_date' => '2027-06-30',
        ]);
    }

    public function test_locked_player_ids_names_every_locking_deal_and_nothing_else(): void
    {
        $locking = [
            'agreed incoming pre-contract' => $this->offer($this->playerAt($this->sellingTeam), [
                'offer_type' => TransferOffer::TYPE_PRE_CONTRACT,
                'status' => TransferOffer::STATUS_AGREED,
            ]),
            'agreed outgoing pre-contract' => $this->offer($this->playerAt($this->userTeam), [
                'offer_type' => TransferOffer::TYPE_PRE_CONTRACT,
                'direction' => TransferOffer::DIRECTION_OUTGOING,
                'offering_team_id' => $this->sellingTeam->id,
                'selling_team_id' => $this->userTeam->id,
                'status' => TransferOffer::STATUS_AGREED,
            ]),
            'fee-agreed paid release clause' => $this->offer($this->playerAt($this->sellingTeam), [
                'offer_type' => TransferOffer::TYPE_USER_BID,
                'status' => TransferOffer::STATUS_FEE_AGREED,
                'transfer_fee' => 500_000_000,
                'triggered_release_clause' => true,
            ]),
        ];

        $notLocking = [
            'pending pre-contract' => $this->offer($this->playerAt($this->sellingTeam), [
                'offer_type' => TransferOffer::TYPE_PRE_CONTRACT,
                'status' => TransferOffer::STATUS_PENDING,
            ]),
            'agreed ordinary bid' => $this->offer($this->playerAt($this->sellingTeam), [
                'offer_type' => TransferOffer::TYPE_USER_BID,
                'status' => TransferOffer::STATUS_AGREED,
                'transfer_fee' => 500_000_000,
            ]),
            'rejected pre-contract' => $this->offer($this->playerAt($this->sellingTeam), [
                'offer_type' => TransferOffer::TYPE_PRE_CONTRACT,
                'status' => TransferOffer::STATUS_REJECTED,
            ]),
        ];

        $lockedIds = TransferOffer::lockedPlayerIds($this->game->id);

        foreach ($locking as $label => $offer) {
            $this->assertArrayHasKey($offer->game_player_id, $lockedIds, "A {$label} must lock the player.");
            $this->assertTrue($offer->locksPlayer(), "locksPlayer() must agree with the scope for a {$label}.");
        }

        foreach ($notLocking as $label => $offer) {
            $this->assertArrayNotHasKey($offer->game_player_id, $lockedIds, "A {$label} must not lock the player.");
            $this->assertFalse($offer->locksPlayer(), "locksPlayer() must agree with the scope for a {$label}.");
        }
    }

    public function test_contract_expiry_keeps_a_clause_bought_player_at_his_club(): void
    {
        // Every AI contract runs out this summer, so without the lock this
        // player would be freed (team_id = null) before his deal completes.
        $this->forceAiNonRenewal();

        $player = $this->playerAt($this->sellingTeam);
        $this->offer($player, [
            'offer_type' => TransferOffer::TYPE_USER_BID,
            'status' => TransferOffer::STATUS_AGREED,
            'transfer_fee' => 500_000_000,
            'triggered_release_clause' => true,
        ]);

        app(ContractExpirationProcessor::class)->process($this->game, new SeasonTransitionData(
            oldSeason: $this->game->season,
            newSeason: '2027',
            competitionId: $this->game->competition_id,
        ));

        $this->assertSame(
            $this->sellingTeam->id,
            $player->fresh()->team_id,
            'A player the user has bought via his release clause must not be freed by contract expiry.',
        );
    }

    public function test_completing_a_deal_for_a_locked_player_who_was_moved_fails_loudly(): void
    {
        $player = $this->playerAt($this->sellingTeam);
        $offer = $this->offer($player, [
            'offer_type' => TransferOffer::TYPE_PRE_CONTRACT,
            'status' => TransferOffer::STATUS_AGREED,
        ]);

        // Some path moved him without going through his deal.
        $player->update(['team_id' => $this->otherTeam->id]);

        try {
            app(TransferCompletionService::class)->completeIncomingTransfer($offer, $this->game);
            $this->fail('Moving a locked player must surface as an invariant violation under test.');
        } catch (LockedPlayerMovedException $e) {
            $this->assertStringContainsString($player->id, $e->getMessage());
        }

        // The exception fires before the deal is touched, so nothing is hidden.
        $this->assertSame(TransferOffer::STATUS_AGREED, $offer->fresh()->status);
    }

    public function test_the_user_still_gets_a_graceful_failure_in_production(): void
    {
        // In production the violation is report()ed, not thrown: the game must
        // never get stuck on it. Faking the handler models that.
        Exceptions::fake([LockedPlayerMovedException::class]);

        $player = $this->playerAt($this->sellingTeam);
        $offer = $this->offer($player, [
            'offer_type' => TransferOffer::TYPE_PRE_CONTRACT,
            'status' => TransferOffer::STATUS_AGREED,
        ]);
        $player->update(['team_id' => $this->otherTeam->id]);

        $completed = app(TransferCompletionService::class)->completeIncomingTransfer($offer, $this->game);

        $this->assertFalse($completed);
        $this->assertSame(TransferOffer::STATUS_REJECTED, $offer->fresh()->status);
        $this->assertSame(
            __('notifications.pre_contract_failed_title', ['player' => $player->name]),
            GameNotification::where('game_id', $this->game->id)->sole()->title,
        );
        Exceptions::assertReported(LockedPlayerMovedException::class);
    }

    public function test_an_ordinary_bid_losing_the_race_is_not_an_invariant_violation(): void
    {
        // The market is allowed to beat an unlocked deal to the player; that is
        // the legitimate case the completion guard exists for.
        $player = $this->playerAt($this->sellingTeam);
        $offer = $this->offer($player, [
            'offer_type' => TransferOffer::TYPE_USER_BID,
            'status' => TransferOffer::STATUS_AGREED,
        ]);
        $player->update(['team_id' => $this->otherTeam->id]);

        $completed = app(TransferCompletionService::class)->completeIncomingTransfer($offer, $this->game);

        $this->assertFalse($completed);
        $this->assertSame(TransferOffer::STATUS_REJECTED, $offer->fresh()->status);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function playerAt(Team $team): GamePlayer
    {
        return GamePlayer::factory()->forGame($this->game)->forTeam($team)->create([
            'date_of_birth' => '1996-01-01',
            'contract_until' => '2027-06-30',
            'annual_wage' => 100_000_000,
            'market_value_cents' => 1_000_000_000,
        ]);
    }

    /**
     * An incoming deal from the user for the player's current club, with any
     * column overridden by $attributes.
     */
    private function offer(GamePlayer $player, array $attributes): TransferOffer
    {
        return TransferOffer::create(array_merge([
            'game_id' => $this->game->id,
            'game_player_id' => $player->id,
            'offering_team_id' => $this->userTeam->id,
            'selling_team_id' => $player->team_id,
            'direction' => TransferOffer::DIRECTION_INCOMING,
            'transfer_fee' => 0,
            'offered_wage' => 150_000_000,
            'offered_years' => 3,
            'expires_at' => $this->game->current_date,
            'game_date' => $this->game->current_date,
        ], $attributes));
    }

    /** Make ContractExpirationProcessor's non-renewal roll a certainty. */
    private function forceAiNonRenewal(): void
    {
        config()->set('transfers.ai_contract_renewal.veteran_non_renewal', 1.0);
        config()->set('transfers.ai_contract_renewal.non_veteran_non_renewal_base', 1.0);
        config()->set('transfers.ai_contract_renewal.non_veteran_non_renewal_max', 1.0);
    }
}
