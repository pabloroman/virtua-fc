<?php

namespace Tests\Unit;

use App\Models\Game;
use App\Models\GameFinances;
use App\Models\GameInvestment;
use App\Models\GamePlayer;
use App\Models\GameStanding;
use App\Models\Team;
use App\Models\TransferOffer;
use App\Modules\Season\Services\SeasonClosingPipeline;
use App\Modules\Transfer\Services\TransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A pre-contract is a free transfer that takes effect when the player's
 * current contract expires (season end). This must hold in BOTH directions:
 * a player the user signs on a pre-contract joins next season, and a player
 * of the user's poached by an AI on a pre-contract leaves at season end —
 * not mid-window.
 *
 * Regression guard for the bug where completeAgreedTransfers() swept up
 * agreed outgoing pre-contracts (no offer_type filter), so a poached player
 * left the moment the next match was played during an open window, while the
 * symmetric incoming case correctly waited until summer.
 */
class PreContractOutgoingTimingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Scenario: an AI club agrees a pre-contract for the user's player during
     * the open January window. Completing agreed transfers (what the
     * match-played / window-open listeners do) must NOT move the player —
     * the pre-contract is a season-end event.
     */
    public function test_agreed_outgoing_pre_contract_does_not_complete_mid_window(): void
    {
        // current_date is inside the January transfer window.
        $game = Game::factory()->create([
            'current_date' => '2025-01-21',
            'season' => '2024',
        ]);
        $userTeam = $game->team;
        $buyerTeam = Team::factory()->create();

        // Player on the user's team with a contract expiring at season end.
        $player = GamePlayer::factory()->forGame($game)->forTeam($userTeam)->create([
            'contract_until' => '2025-06-30',
        ]);

        $offer = TransferOffer::create([
            'game_id' => $game->id,
            'game_player_id' => $player->id,
            'offering_team_id' => $buyerTeam->id,
            'offer_type' => TransferOffer::TYPE_PRE_CONTRACT,
            'transfer_fee' => 0,
            'status' => TransferOffer::STATUS_AGREED,
            'expires_at' => '2025-02-01',
            'game_date' => '2025-01-21',
            'resolved_at' => '2025-01-21',
        ]);

        $completed = app(TransferService::class)->completeAgreedTransfers($game);

        $this->assertTrue($completed->isEmpty(), 'Outgoing pre-contracts must not be completed mid-window');

        $player->refresh();
        $offer->refresh();
        $this->assertSame($userTeam->id, $player->team_id, 'Poached player stays until season end');
        $this->assertSame(TransferOffer::STATUS_AGREED, $offer->status, 'Pre-contract stays agreed, awaiting season end');
    }

    /**
     * Scenario: the same agreed outgoing pre-contract is completed at season
     * end by PreContractTransferProcessor — the player leaves on a free.
     */
    public function test_agreed_outgoing_pre_contract_completes_at_season_end(): void
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
            'projected_position' => 10,
        ]);
        GameStanding::create([
            'game_id' => $game->id,
            'competition_id' => $game->competition_id,
            'team_id' => $game->team_id,
            'position' => 10,
            'played' => 38,
            'won' => 10,
            'drawn' => 8,
            'lost' => 20,
            'goals_for' => 40,
            'goals_against' => 60,
            'points' => 38,
        ]);

        // Expiring contract: ContractExpirationProcessor skips players with an
        // agreed outgoing pre-contract so PreContractTransferProcessor can move them.
        $player = GamePlayer::factory()->forGame($game)->forTeam($userTeam)->create([
            'contract_until' => '2025-06-30',
        ]);

        TransferOffer::create([
            'game_id' => $game->id,
            'game_player_id' => $player->id,
            'offering_team_id' => $buyerTeam->id,
            'offer_type' => TransferOffer::TYPE_PRE_CONTRACT,
            'transfer_fee' => 0,
            'status' => TransferOffer::STATUS_AGREED,
            'expires_at' => '2025-07-01',
            'game_date' => '2025-01-21',
            'resolved_at' => '2025-01-21',
        ]);

        app(SeasonClosingPipeline::class)->run($game);

        $player->refresh();
        $this->assertSame(
            $buyerTeam->id,
            $player->team_id,
            'Outgoing pre-contract should complete at season end (player leaves on a free)'
        );
    }
}
