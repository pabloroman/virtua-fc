<?php

namespace Tests\Unit;

use App\Models\Game;
use App\Models\GameFinances;
use App\Models\GameInvestment;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Models\TransferOffer;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Season\Services\SeasonClosingPipeline;
use App\Modules\Transfer\Services\TransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Proves that transfers agreed outside the transfer window are lost
 * at season end. The root cause is two-fold:
 *
 * 1. CareerActionProcessor only completes agreed transfers when
 *    isTransferWindowOpen() is true. With the forward-looking
 *    current_date, the last January match jumps to a February date,
 *    so winter-window agreed transfers never execute.
 *
 * 2. TransferMarketResetProcessor deletes ALL TransferOffer records
 *    during season close, but no preceding processor completes
 *    non-pre-contract agreed transfers first.
 */
class AgreedTransferLostAtSeasonEndTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Scenario: user sells a player during the winter window, but the
     * last January match advances current_date to February. The agreed
     * outgoing transfer is never completed and is deleted at season end.
     */
    public function test_agreed_outgoing_transfer_is_lost_at_season_end(): void
    {
        $game = Game::factory()->create([
            'current_date' => '2025-06-10',
            'season' => '2024',
        ]);
        $userTeam = $game->team;
        $buyerTeam = Team::factory()->create();

        GameInvestment::create([
            'game_id' => $game->id,
            'season' => '2024',
            'transfer_budget' => 50_000_000_00,
            'scouting_tier' => 1,
        ]);
        GameFinances::create([
            'game_id' => $game->id,
            'season' => '2024',
            'projected_revenue' => 100_000_000_00,
            'projected_wages' => 50_000_000_00,
        ]);

        // Player on user's team, sold to AI club outside transfer window
        $player = GamePlayer::factory()->forGame($game)->forTeam($userTeam)->create([
            'contract_until' => '2027-06-30',
        ]);

        // This offer was agreed in February (outside the window)
        TransferOffer::create([
            'game_id' => $game->id,
            'game_player_id' => $player->id,
            'offering_team_id' => $buyerTeam->id,
            'offer_type' => TransferOffer::TYPE_LISTED,
            'transfer_fee' => 10_000_000_00,
            'status' => TransferOffer::STATUS_AGREED,
            'direction' => TransferOffer::DIRECTION_OUTGOING,
            'expires_at' => '2025-07-01',
            'game_date' => '2025-02-05',
            'resolved_at' => '2025-02-05',
        ]);

        // Run the season closing pipeline
        $pipeline = app(SeasonClosingPipeline::class);
        $data = new SeasonTransitionData(
            oldSeason: '2024',
            newSeason: '2025',
            competitionId: $game->competition_id,
        );
        $pipeline->run($game);

        // BUG: player should have moved to the buyer team, but they didn't
        $player->refresh();
        $this->assertSame(
            $buyerTeam->id,
            $player->team_id,
            'Agreed outgoing transfer should complete at season end, but player is still on the user team'
        );
    }

    /**
     * Scenario: user buys a player outside the transfer window. The
     * agreed incoming transfer is never completed and is deleted at
     * season end.
     */
    public function test_agreed_incoming_transfer_is_lost_at_season_end(): void
    {
        $game = Game::factory()->create([
            'current_date' => '2025-06-10',
            'season' => '2024',
        ]);
        $userTeam = $game->team;
        $sellerTeam = Team::factory()->create();

        GameInvestment::create([
            'game_id' => $game->id,
            'season' => '2024',
            'transfer_budget' => 50_000_000_00,
            'scouting_tier' => 1,
        ]);
        GameFinances::create([
            'game_id' => $game->id,
            'season' => '2024',
            'projected_revenue' => 100_000_000_00,
            'projected_wages' => 50_000_000_00,
        ]);

        // Player on AI team, bought by user outside transfer window
        $player = GamePlayer::factory()->forGame($game)->forTeam($sellerTeam)->create([
            'contract_until' => '2027-06-30',
        ]);

        // This offer was agreed in March (outside any window)
        TransferOffer::create([
            'game_id' => $game->id,
            'game_player_id' => $player->id,
            'offering_team_id' => $userTeam->id,
            'selling_team_id' => $sellerTeam->id,
            'offer_type' => TransferOffer::TYPE_USER_BID,
            'transfer_fee' => 8_000_000_00,
            'status' => TransferOffer::STATUS_AGREED,
            'direction' => TransferOffer::DIRECTION_INCOMING,
            'expires_at' => '2025-07-01',
            'game_date' => '2025-03-15',
            'resolved_at' => '2025-03-15',
        ]);

        // Run the season closing pipeline
        $pipeline = app(SeasonClosingPipeline::class);
        $data = new SeasonTransitionData(
            oldSeason: '2024',
            newSeason: '2025',
            competitionId: $game->competition_id,
        );
        $pipeline->run($game);

        // BUG: player should have moved to the user's team, but they didn't
        $player->refresh();
        $this->assertSame(
            $userTeam->id,
            $player->team_id,
            'Agreed incoming transfer should complete at season end, but player is still on the seller team'
        );
    }

    /**
     * Proves that TransferMarketResetProcessor deletes agreed offers
     * without completing them.
     */
    public function test_agreed_offers_are_deleted_without_completion(): void
    {
        $game = Game::factory()->create([
            'current_date' => '2025-06-10',
            'season' => '2024',
        ]);
        $userTeam = $game->team;
        $buyerTeam = Team::factory()->create();

        GameInvestment::create([
            'game_id' => $game->id,
            'season' => '2024',
            'transfer_budget' => 50_000_000_00,
            'scouting_tier' => 1,
        ]);
        GameFinances::create([
            'game_id' => $game->id,
            'season' => '2024',
            'projected_revenue' => 100_000_000_00,
            'projected_wages' => 50_000_000_00,
        ]);

        $player = GamePlayer::factory()->forGame($game)->forTeam($userTeam)->create([
            'contract_until' => '2027-06-30',
        ]);

        TransferOffer::create([
            'game_id' => $game->id,
            'game_player_id' => $player->id,
            'offering_team_id' => $buyerTeam->id,
            'offer_type' => TransferOffer::TYPE_LISTED,
            'transfer_fee' => 10_000_000_00,
            'status' => TransferOffer::STATUS_AGREED,
            'direction' => TransferOffer::DIRECTION_OUTGOING,
            'expires_at' => '2025-07-01',
            'game_date' => '2025-02-05',
            'resolved_at' => '2025-02-05',
        ]);

        // Before season close: the agreed offer exists
        $this->assertSame(1, TransferOffer::where('game_id', $game->id)
            ->where('status', TransferOffer::STATUS_AGREED)
            ->count());

        $pipeline = app(SeasonClosingPipeline::class);
        $pipeline->run($game);

        // After season close: the offer has been deleted entirely
        $this->assertSame(0, TransferOffer::where('game_id', $game->id)->count(),
            'TransferMarketResetProcessor deletes all offers including agreed ones');

        // And the player never moved
        $player->refresh();
        $this->assertSame($userTeam->id, $player->team_id,
            'Player stayed on original team because the agreed offer was deleted without being completed');
    }

    /**
     * Proves that the forward-looking current_date causes CareerActionProcessor
     * to skip agreed transfer completion when the last match of a window
     * advances the date past the window boundary.
     */
    public function test_forward_looking_date_skips_window_transfer_completion(): void
    {
        // Last match was Jan 28 (winter window), but current_date is now Feb 5 (next match)
        $game = Game::factory()->create([
            'current_date' => '2025-02-05',
            'season' => '2024',
        ]);

        // The window is closed because current_date is in February
        $this->assertFalse($game->isTransferWindowOpen(),
            'Window should be closed because forward-looking current_date is in February');

        // This means CareerActionProcessor will NOT run completeAgreedTransfers
        // even though the transfer was agreed during the January window
    }
}
