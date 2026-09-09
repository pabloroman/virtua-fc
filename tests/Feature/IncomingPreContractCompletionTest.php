<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Loan;
use App\Models\Team;
use App\Models\TransferOffer;
use App\Models\User;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Season\Processors\ContractExpirationProcessor;
use App\Modules\Season\Processors\LoanReturnProcessor;
use App\Modules\Season\Processors\PreContractTransferProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A pre-contract the user signs has to actually deliver the player at the
 * season boundary. Two ways it could silently fail to, both ending the same
 * way: TransferCompletionService::completeIncomingTransfer re-asserts that the
 * player is still at the club that agreed to sell him, finds he is not, and
 * rejects the deal with a "transfer fell through" notification.
 *
 *  - The selling club simply lets the contract run out, so
 *    ContractExpirationProcessor (priority 20) frees the player before
 *    PreContractTransferProcessor (priority 30) can move him. That is the
 *    ordinary Bosman case — the whole point of a pre-contract — not an edge
 *    case, and the AI's non-renewal roll decides how often it happens.
 *  - The player was already at the user's club on loan, so the offer recorded
 *    the borrowing club as the seller and stopped matching the moment
 *    LoanReturnProcessor (priority 5) sent him back to his owner.
 *
 * These run the processors in their pipeline priority order rather than the
 * whole SeasonClosingPipeline, so a failure points at the interaction under
 * test instead of at unrelated season-transition fixtures.
 */
class IncomingPreContractCompletionTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;
    private Team $userTeam;
    private Team $sellingTeam;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->userTeam = Team::factory()->create();
        $this->sellingTeam = Team::factory()->create();

        $this->game = Game::factory()->forTeam($this->userTeam)->create([
            'user_id' => $user->id,
            'season' => '2026',
            'current_date' => '2027-06-30',
        ]);
    }

    public function test_pre_contract_completes_when_the_selling_club_lets_the_contract_run_out(): void
    {
        // The AI club never renews, so the player reaches the boundary as a
        // free agent — exactly the situation a pre-contract exists to exploit.
        $this->forceAiNonRenewal();

        $player = $this->expiringPlayerAt($this->sellingTeam);
        $this->agreedPreContractFor($player, $this->sellingTeam);

        $this->runClosing(ContractExpirationProcessor::class, PreContractTransferProcessor::class);

        $this->assertSame(
            $this->userTeam->id,
            $player->fresh()->team_id,
            'A pre-contracted player must join the user even when his old club declined to renew.',
        );
    }

    public function test_pre_contract_completes_for_a_player_the_user_has_on_loan(): void
    {
        // Isolate the loan interaction: the owning club would have renewed him,
        // so nothing but the loan return moves the player before completion.
        $this->forceAiRenewal();

        // A loan rewrites team_id to the borrowing club, so this player sits on
        // the user's roster while the selling team still owns his contract.
        $player = $this->expiringPlayerAt($this->userTeam);
        Loan::create([
            'game_id' => $this->game->id,
            'game_player_id' => $player->id,
            'parent_team_id' => $this->sellingTeam->id,
            'loan_team_id' => $this->userTeam->id,
            'started_at' => '2026-08-01',
            'return_at' => '2027-06-30',
            'status' => Loan::STATUS_ACTIVE,
        ]);

        // The deal is struck with the club that owns him, not the one he is at.
        $this->agreedPreContractFor($player, $player->owningTeamId());

        $this->runClosing(
            LoanReturnProcessor::class,
            ContractExpirationProcessor::class,
            PreContractTransferProcessor::class,
        );

        $this->assertSame(
            $this->userTeam->id,
            $player->fresh()->team_id,
            'Signing a player the club already has on loan must survive his loan returning home.',
        );
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    /**
     * Run the named closing processors in order against the game under test.
     *
     * @param  class-string  ...$processors
     */
    private function runClosing(string ...$processors): void
    {
        $data = new SeasonTransitionData(
            oldSeason: $this->game->season,
            newSeason: '2027',
            competitionId: $this->game->competition_id,
        );

        foreach ($processors as $processor) {
            $data = app($processor)->process($this->game, $data);
        }
    }

    private function expiringPlayerAt(Team $team): GamePlayer
    {
        return GamePlayer::factory()->forGame($this->game)->forTeam($team)->create([
            'date_of_birth' => '1996-01-01',
            'contract_until' => '2027-06-30',
            'annual_wage' => 100_000_000,
            'market_value_cents' => 1_000_000_000,
        ]);
    }

    private function agreedPreContractFor(GamePlayer $player, Team|string $sellingTeam): TransferOffer
    {
        return TransferOffer::create([
            'game_id' => $this->game->id,
            'game_player_id' => $player->id,
            'offering_team_id' => $this->userTeam->id,
            'selling_team_id' => $sellingTeam instanceof Team ? $sellingTeam->id : $sellingTeam,
            'offer_type' => TransferOffer::TYPE_PRE_CONTRACT,
            'direction' => TransferOffer::DIRECTION_INCOMING,
            'transfer_fee' => 0,
            'offered_wage' => 150_000_000,
            'offered_years' => 3,
            'status' => TransferOffer::STATUS_AGREED,
            'expires_at' => $this->game->current_date,
            'game_date' => $this->game->current_date,
            'resolved_at' => $this->game->current_date,
        ]);
    }

    /** Make ContractExpirationProcessor's non-renewal roll a certainty. */
    private function forceAiNonRenewal(): void
    {
        config()->set('transfers.ai_contract_renewal.veteran_non_renewal', 1.0);
        config()->set('transfers.ai_contract_renewal.non_veteran_non_renewal_base', 1.0);
        config()->set('transfers.ai_contract_renewal.non_veteran_non_renewal_max', 1.0);
    }

    /** Make it an impossibility, so every AI club re-ups instead. */
    private function forceAiRenewal(): void
    {
        config()->set('transfers.ai_contract_renewal.veteran_non_renewal', 0.0);
        config()->set('transfers.ai_contract_renewal.non_veteran_non_renewal_base', 0.0);
        config()->set('transfers.ai_contract_renewal.non_veteran_non_renewal_max', 0.0);
    }
}
