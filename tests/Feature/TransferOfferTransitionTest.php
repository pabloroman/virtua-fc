<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Models\TransferOffer;
use App\Models\User;
use App\Modules\Transfer\Exceptions\IllegalOfferTransitionException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Offer status has one write path: TransferOffer::transitionTo() for a row,
 * transitionAll() for a sweep. Both consult the transition table, so a dead
 * offer cannot be resurrected and a finished one cannot be re-finished, and
 * both stamp resolved_at, which is what every non-pending status means.
 */
class TransferOfferTransitionTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;
    private Team $otherTeam;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->otherTeam = Team::factory()->create();

        $this->game = Game::factory()->forTeam(Team::factory()->create())->create([
            'user_id' => $user->id,
            'season' => '2026',
            'current_date' => '2027-02-15',
        ]);
    }

    public function test_the_transition_table_allows_only_forward_moves(): void
    {
        $legal = [
            [TransferOffer::STATUS_PENDING, TransferOffer::STATUS_FEE_AGREED],
            [TransferOffer::STATUS_PENDING, TransferOffer::STATUS_AGREED],
            [TransferOffer::STATUS_PENDING, TransferOffer::STATUS_REJECTED],
            [TransferOffer::STATUS_PENDING, TransferOffer::STATUS_EXPIRED],
            [TransferOffer::STATUS_FEE_AGREED, TransferOffer::STATUS_AGREED],
            [TransferOffer::STATUS_FEE_AGREED, TransferOffer::STATUS_REJECTED],
            [TransferOffer::STATUS_FEE_AGREED, TransferOffer::STATUS_EXPIRED],
            [TransferOffer::STATUS_AGREED, TransferOffer::STATUS_COMPLETED],
            [TransferOffer::STATUS_AGREED, TransferOffer::STATUS_REJECTED],
            [TransferOffer::STATUS_AGREED, TransferOffer::STATUS_EXPIRED],
        ];
        $illegal = [
            [TransferOffer::STATUS_PENDING, TransferOffer::STATUS_COMPLETED],
            [TransferOffer::STATUS_PENDING, TransferOffer::STATUS_PENDING],
            [TransferOffer::STATUS_FEE_AGREED, TransferOffer::STATUS_PENDING],
            [TransferOffer::STATUS_AGREED, TransferOffer::STATUS_AGREED],
            [TransferOffer::STATUS_AGREED, TransferOffer::STATUS_FEE_AGREED],
            [TransferOffer::STATUS_REJECTED, TransferOffer::STATUS_PENDING],
            [TransferOffer::STATUS_REJECTED, TransferOffer::STATUS_AGREED],
            [TransferOffer::STATUS_EXPIRED, TransferOffer::STATUS_AGREED],
            [TransferOffer::STATUS_COMPLETED, TransferOffer::STATUS_REJECTED],
            [TransferOffer::STATUS_COMPLETED, TransferOffer::STATUS_COMPLETED],
        ];

        foreach ($legal as [$from, $to]) {
            $this->assertTrue(TransferOffer::canTransition($from, $to), "{$from} → {$to} must be legal.");
        }
        foreach ($illegal as [$from, $to]) {
            $this->assertFalse(TransferOffer::canTransition($from, $to), "{$from} → {$to} must be illegal.");
        }
    }

    public function test_transition_to_stamps_resolved_at_and_merges_extra_columns(): void
    {
        $offer = $this->pendingOffer();
        $this->assertNull($offer->resolved_at);

        $on = $this->game->current_date->copy()->addDays(3);
        $offer->transitionTo(TransferOffer::STATUS_FEE_AGREED, $on, ['asking_price' => 250_000_000]);

        $offer->refresh();
        $this->assertSame(TransferOffer::STATUS_FEE_AGREED, $offer->status);
        $this->assertTrue($offer->resolved_at->isSameDay($on));
        $this->assertSame(250_000_000, $offer->asking_price);
    }

    public function test_an_illegal_move_throws_and_leaves_the_row_untouched(): void
    {
        $offer = $this->pendingOffer();
        $offer->transitionTo(TransferOffer::STATUS_REJECTED, $this->game->current_date);
        $resolvedAt = $offer->fresh()->resolved_at;

        try {
            $offer->transitionTo(TransferOffer::STATUS_AGREED, $this->game->current_date->copy()->addMonth(), ['asking_price' => 1]);
            $this->fail('Resurrecting a rejected offer must throw.');
        } catch (IllegalOfferTransitionException $e) {
            $this->assertStringContainsString('rejected to agreed', $e->getMessage());
        }

        $offer->refresh();
        $this->assertSame(TransferOffer::STATUS_REJECTED, $offer->status);
        $this->assertTrue($offer->resolved_at->isSameDay($resolvedAt));
        $this->assertNull($offer->asking_price);
    }

    public function test_transition_all_moves_only_rows_that_can_reach_the_target(): void
    {
        $player = $this->player();
        $pendingA = $this->pendingOffer($player);
        $pendingB = $this->pendingOffer($player);
        $completed = $this->pendingOffer($player);
        $completed->transitionTo(TransferOffer::STATUS_AGREED, $this->game->current_date);
        $completed->transitionTo(TransferOffer::STATUS_COMPLETED, $this->game->current_date);
        $completedOn = $completed->fresh()->resolved_at;

        // A sibling-rejection sweep over everything the player has.
        $moved = TransferOffer::transitionAll(
            TransferOffer::where('game_player_id', $player->id),
            TransferOffer::STATUS_REJECTED,
            $this->game->current_date->copy()->addDay(),
        );

        $this->assertSame(2, $moved);
        $this->assertSame(TransferOffer::STATUS_REJECTED, $pendingA->fresh()->status);
        $this->assertSame(TransferOffer::STATUS_REJECTED, $pendingB->fresh()->status);
        $this->assertSame(TransferOffer::STATUS_COMPLETED, $completed->fresh()->status, 'A completed deal is not the sweep\'s business.');
        $this->assertTrue($completed->fresh()->resolved_at->isSameDay($completedOn));
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function player(): GamePlayer
    {
        return GamePlayer::factory()->forGame($this->game)->forTeam($this->otherTeam)->create([
            'contract_until' => '2028-06-30',
        ]);
    }

    private function pendingOffer(?GamePlayer $player = null): TransferOffer
    {
        $player ??= $this->player();

        return TransferOffer::create([
            'game_id' => $this->game->id,
            'game_player_id' => $player->id,
            'offering_team_id' => $this->game->team_id,
            'selling_team_id' => $player->team_id,
            'offer_type' => TransferOffer::TYPE_USER_BID,
            'direction' => TransferOffer::DIRECTION_INCOMING,
            'transfer_fee' => 100_000_000,
            'status' => TransferOffer::STATUS_PENDING,
            'expires_at' => $this->game->current_date->copy()->addDays(14),
            'game_date' => $this->game->current_date,
        ]);
    }
}
