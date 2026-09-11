<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Loan;
use App\Models\Team;
use App\Models\TransferOffer;
use App\Models\User;
use App\Modules\Transfer\Services\LoanService;
use App\Modules\Transfer\Services\TransferCompletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GamePlayer::team_id says where a player plays; owningTeamId() says who holds
 * his contract. A loan separates the two, and the codebase has repeatedly read
 * one as the other — three of the six defects in #1364 were this confusion.
 *
 * These cases pin the distinction at the points where getting it wrong loses a
 * deal or hides a player from the club that owns him.
 */
class PlayerOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;
    private Team $userTeam;
    private Team $otherTeam;
    private Team $thirdTeam;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->userTeam = Team::factory()->create();
        $this->otherTeam = Team::factory()->create();
        $this->thirdTeam = Team::factory()->create();

        $this->game = Game::factory()->forTeam($this->userTeam)->create([
            'user_id' => $user->id,
            'season' => '2026',
            'current_date' => '2027-02-15',
        ]);
    }

    // ── Ownership resolves through the loan ───────────────────────────────

    public function test_a_loan_moves_location_but_not_ownership(): void
    {
        $loanedIn = $this->playerOnLoan(borrower: $this->userTeam, parent: $this->otherTeam);

        $this->assertSame($this->userTeam->id, $loanedIn->team_id);
        $this->assertSame($this->otherTeam->id, $loanedIn->owningTeamId());
        $this->assertFalse($loanedIn->isUserOwned($this->game));
    }

    public function test_a_loan_whose_parent_club_left_the_game_is_owned_by_nobody(): void
    {
        // loans.parent_team_id is nullable: the owning club isn't in this save,
        // so the player is freed rather than returned when the loan ends.
        // Resolving that to team_id would name his borrower as his owner.
        $orphan = $this->playerOnLoan(borrower: $this->userTeam, parent: null);

        $this->assertSame($this->userTeam->id, $orphan->team_id);
        $this->assertNull($orphan->owningTeamId());
        $this->assertFalse($orphan->isUserOwned($this->game));
    }

    // ── Completion no longer depends on processor ordering ────────────────

    public function test_buying_a_player_the_club_holds_on_loan_completes_mid_season(): void
    {
        // No pipeline at all: the loan is still live, exactly as it is when a
        // deal completes mid-season. The guard used to compare the player's
        // location to the selling club and rejected this as "fell through",
        // and only LoanReturnProcessor running first ever made it work.
        $player = $this->playerOnLoan(borrower: $this->userTeam, parent: $this->otherTeam);
        $offer = $this->agreedIncomingBid($player, $this->otherTeam);

        $completed = app(TransferCompletionService::class)
            ->completeIncomingTransfer($offer->fresh(), $this->game);

        $this->assertTrue($completed, 'Buying a player the club already has on loan must complete.');
        $this->assertSame($this->userTeam->id, $player->fresh()->team_id);
    }

    public function test_buying_a_loanee_retires_his_loan_rather_than_leaving_it_active(): void
    {
        // Ownership has passed, so the loan is over. Left active, he reads as
        // loaned-in to the club that now owns him outright.
        $player = $this->playerOnLoan(borrower: $this->userTeam, parent: $this->otherTeam);
        $offer = $this->agreedIncomingBid($player, $this->otherTeam);

        app(TransferCompletionService::class)->completeIncomingTransfer($offer->fresh(), $this->game);

        $this->assertSame(
            Loan::STATUS_COMPLETED,
            Loan::where('game_player_id', $player->id)->value('status'),
        );
        $this->assertNull($player->fresh()->activeLoan()->first());
        $this->assertSame($this->userTeam->id, $player->fresh()->owningTeamId());
    }

    public function test_completion_still_rejects_a_deal_whose_seller_no_longer_owns_the_player(): void
    {
        // The guard must keep doing its job: an AI-to-AI move between agreement
        // and completion is a race the market is allowed to win.
        $player = GamePlayer::factory()->forGame($this->game)->forTeam($this->otherTeam)->create();
        $offer = $this->agreedIncomingBid($player, $this->otherTeam);

        $player->update(['team_id' => $this->thirdTeam->id]);

        $completed = app(TransferCompletionService::class)
            ->completeIncomingTransfer($offer->fresh(), $this->game);

        $this->assertFalse($completed);
        $this->assertSame(TransferOffer::STATUS_REJECTED, $offer->fresh()->status);
    }

    // ── Offers record the owner, not the location ─────────────────────────

    public function test_a_loan_in_request_names_the_owning_club_as_lender(): void
    {
        // He is already out on loan at a third club. That club cannot lend him
        // on; his owner can. completeLoanIn reads selling_team_id straight back
        // as the new loan's parent_team_id, so naming the borrower here would
        // reparent him to a club that never owned him.
        $player = $this->playerOnLoan(borrower: $this->thirdTeam, parent: $this->otherTeam);

        $offer = app(LoanService::class)->requestLoanIn($this->game, $player);

        $this->assertSame($this->otherTeam->id, $offer->selling_team_id);
    }

    // ── Departures are seen by the club that owns him ─────────────────────

    public function test_a_player_loaned_out_is_still_a_departure_for_his_owner(): void
    {
        // The user owns him; he is away at another club; a third club signs him
        // on a free. Matched by location he is nobody's departure — least of all
        // the one club actually losing him.
        $player = $this->playerOnLoan(borrower: $this->otherTeam, parent: $this->userTeam);
        $poaching = $this->offer($player, [
            'offering_team_id' => $this->thirdTeam->id,
            'selling_team_id' => $this->userTeam->id,
            'offer_type' => TransferOffer::TYPE_PRE_CONTRACT,
            'direction' => TransferOffer::DIRECTION_OUTGOING,
            'status' => TransferOffer::STATUS_AGREED,
        ]);

        $departing = TransferOffer::where('game_id', $this->game->id)
            ->departingFrom($this->game->userTeamIds())
            ->pluck('id')
            ->all();

        $this->assertContains($poaching->id, $departing);
    }

    public function test_a_loanee_signed_permanently_by_his_borrower_is_not_a_departure(): void
    {
        // The #1364 case, from the other side: the club he sits at is the one
        // signing him, so he is arriving, not leaving.
        $player = $this->playerOnLoan(borrower: $this->userTeam, parent: $this->otherTeam);
        $this->offer($player, [
            'offering_team_id' => $this->userTeam->id,
            'selling_team_id' => $this->otherTeam->id,
            'offer_type' => TransferOffer::TYPE_PRE_CONTRACT,
            'direction' => TransferOffer::DIRECTION_INCOMING,
            'status' => TransferOffer::STATUS_AGREED,
        ]);

        $departing = TransferOffer::where('game_id', $this->game->id)
            ->departingFrom($this->game->userTeamIds())
            ->pluck('id')
            ->all();

        $this->assertSame([], $departing);

        // And the squad-page badge agrees: he is arriving, not leaving. This is
        // the half hasAgreedPreContractDeparture() answers, keyed on where he
        // sits rather than who owns him — see its docblock.
        $this->assertFalse($player->fresh()->hasAgreedPreContractDeparture());
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function playerOnLoan(Team $borrower, ?Team $parent): GamePlayer
    {
        return GamePlayer::factory()
            ->onLoan($this->game, $borrower, $parent)
            ->create([
                'contract_until' => '2028-06-30',
                'annual_wage' => 100_000_000,
                'market_value_cents' => 1_000_000_000,
            ]);
    }

    private function agreedIncomingBid(GamePlayer $player, Team $seller): TransferOffer
    {
        return $this->offer($player, [
            'offering_team_id' => $this->userTeam->id,
            'selling_team_id' => $seller->id,
            'offer_type' => TransferOffer::TYPE_USER_BID,
            'direction' => TransferOffer::DIRECTION_INCOMING,
            'status' => TransferOffer::STATUS_AGREED,
            'offered_wage' => 150_000_000,
            'offered_years' => 3,
        ]);
    }

    private function offer(GamePlayer $player, array $attributes): TransferOffer
    {
        return TransferOffer::create($attributes + [
            'game_id' => $this->game->id,
            'game_player_id' => $player->id,
            'transfer_fee' => 0,
            'expires_at' => $this->game->current_date,
            'game_date' => $this->game->current_date,
        ]);
    }
}
