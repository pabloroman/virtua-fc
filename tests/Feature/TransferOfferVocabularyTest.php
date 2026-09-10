<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Loan;
use App\Models\Team;
use App\Models\TransferOffer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The scopes on TransferOffer are the single definition of each offer-state
 * concept. These tests fix the meaning of the ones whose definition has
 * drifted between call sites before, so a future "helpful" tweak to one
 * scope is caught here rather than at the twelve sites that use it.
 */
class TransferOfferVocabularyTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;
    private Team $userTeam;
    private Team $otherTeam;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->userTeam = Team::factory()->create();
        $this->otherTeam = Team::factory()->create();

        $this->game = Game::factory()->forTeam($this->userTeam)->create([
            'user_id' => $user->id,
            'season' => '2026',
            'current_date' => '2027-02-15',
        ]);
    }

    public function test_departing_from_excludes_the_users_own_deal_for_a_player_he_holds_on_loan(): void
    {
        // A loaned-in player sits at the user's team_id, so without the
        // offering_team_id guard the user's own incoming pre-contract for him
        // would read as one of his players leaving on a free.
        $loanee = $this->playerAt($this->userTeam);
        Loan::create([
            'game_id' => $this->game->id,
            'game_player_id' => $loanee->id,
            'parent_team_id' => $this->otherTeam->id,
            'loan_team_id' => $this->userTeam->id,
            'started_at' => '2026-08-01',
            'return_at' => '2027-06-30',
            'status' => Loan::STATUS_ACTIVE,
        ]);
        $ownDeal = $this->offer($loanee, [
            'offering_team_id' => $this->userTeam->id,
            'selling_team_id' => $this->otherTeam->id,
            'direction' => TransferOffer::DIRECTION_INCOMING,
        ]);

        $poached = $this->playerAt($this->userTeam);
        $poaching = $this->offer($poached, [
            'offering_team_id' => $this->otherTeam->id,
            'selling_team_id' => $this->userTeam->id,
            'direction' => TransferOffer::DIRECTION_OUTGOING,
        ]);

        $departing = TransferOffer::where('game_id', $this->game->id)
            ->departingFrom($this->userTeam->id)
            ->pluck('id')
            ->all();

        $this->assertContains($poaching->id, $departing);
        $this->assertNotContains($ownDeal->id, $departing);
    }

    public function test_incoming_for_is_scoped_to_the_buying_club(): void
    {
        $player = $this->playerAt($this->otherTeam);
        $mine = $this->offer($player, [
            'offering_team_id' => $this->userTeam->id,
            'direction' => TransferOffer::DIRECTION_INCOMING,
        ]);
        $rival = Team::factory()->create();
        $theirs = $this->offer($player, [
            'offering_team_id' => $rival->id,
            'direction' => TransferOffer::DIRECTION_OUTGOING,
        ]);

        $ids = TransferOffer::where('game_id', $this->game->id)
            ->incomingFor($this->userTeam->id)
            ->pluck('id')
            ->all();

        $this->assertSame([$mine->id], $ids);
        $this->assertNotContains($theirs->id, $ids);
    }

    public function test_status_scopes_and_predicates_agree(): void
    {
        $player = $this->playerAt($this->otherTeam);
        $byStatus = [];
        foreach ([
            TransferOffer::STATUS_PENDING,
            TransferOffer::STATUS_FEE_AGREED,
            TransferOffer::STATUS_AGREED,
            TransferOffer::STATUS_REJECTED,
            TransferOffer::STATUS_EXPIRED,
            TransferOffer::STATUS_COMPLETED,
        ] as $status) {
            $byStatus[$status] = $this->offer($player, ['status' => $status]);
        }

        $committed = TransferOffer::where('game_id', $this->game->id)->committed()->pluck('status')->sort()->values()->all();
        $active = TransferOffer::where('game_id', $this->game->id)->active()->pluck('status')->sort()->values()->all();

        $this->assertSame([TransferOffer::STATUS_AGREED, TransferOffer::STATUS_FEE_AGREED], $committed);
        $this->assertSame([TransferOffer::STATUS_AGREED, TransferOffer::STATUS_FEE_AGREED, TransferOffer::STATUS_PENDING], $active);

        foreach ($byStatus as $status => $offer) {
            $this->assertSame(in_array($status, $committed, true), $offer->isCommitted(), "isCommitted() for {$status}");
            $this->assertSame(in_array($status, $active, true), $offer->isActive(), "isActive() for {$status}");
        }
    }

    public function test_agreed_pre_contract_is_direction_blind(): void
    {
        $incoming = $this->offer($this->playerAt($this->otherTeam), [
            'direction' => TransferOffer::DIRECTION_INCOMING,
            'status' => TransferOffer::STATUS_AGREED,
        ]);
        $outgoing = $this->offer($this->playerAt($this->userTeam), [
            'offering_team_id' => $this->otherTeam->id,
            'selling_team_id' => $this->userTeam->id,
            'direction' => TransferOffer::DIRECTION_OUTGOING,
            'status' => TransferOffer::STATUS_AGREED,
        ]);
        $pending = $this->offer($this->playerAt($this->otherTeam), [
            'status' => TransferOffer::STATUS_PENDING,
        ]);
        $agreedBid = $this->offer($this->playerAt($this->otherTeam), [
            'offer_type' => TransferOffer::TYPE_USER_BID,
            'status' => TransferOffer::STATUS_AGREED,
        ]);

        $ids = TransferOffer::where('game_id', $this->game->id)->agreedPreContract()->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$incoming->id, $outgoing->id], $ids);
        $this->assertFalse($pending->isAgreedPreContract());
        $this->assertFalse($agreedBid->isAgreedPreContract());
        $this->assertTrue($outgoing->isAgreedPreContract());
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function playerAt(Team $team): GamePlayer
    {
        return GamePlayer::factory()->forGame($this->game)->forTeam($team)->create([
            'contract_until' => '2027-06-30',
        ]);
    }

    /** A pending incoming pre-contract from the user unless overridden. */
    private function offer(GamePlayer $player, array $attributes): TransferOffer
    {
        return TransferOffer::create(array_merge([
            'game_id' => $this->game->id,
            'game_player_id' => $player->id,
            'offering_team_id' => $this->userTeam->id,
            'selling_team_id' => $player->team_id,
            'offer_type' => TransferOffer::TYPE_PRE_CONTRACT,
            'direction' => TransferOffer::DIRECTION_INCOMING,
            'status' => TransferOffer::STATUS_PENDING,
            'transfer_fee' => 0,
            'offered_wage' => 100_000_000,
            'expires_at' => $this->game->current_date,
            'game_date' => $this->game->current_date,
        ], $attributes));
    }
}
