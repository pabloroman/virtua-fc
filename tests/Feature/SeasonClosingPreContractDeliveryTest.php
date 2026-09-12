<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\GameFinances;
use App\Models\GameInvestment;
use App\Models\GameNotification;
use App\Models\GamePlayer;
use App\Models\GameStanding;
use App\Models\GameTransfer;
use App\Models\Loan;
use App\Models\Team;
use App\Models\TransferOffer;
use App\Modules\Season\Services\SeasonClosingPipeline;
use App\Modules\Transfer\Exceptions\LockedPlayerMovedException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Tests\TestCase;

/**
 * A pre-contract the user signs has to survive the *whole* season close, not
 * just the two or three processors an isolated test happens to name.
 *
 * IncomingPreContractCompletionTest runs ContractExpirationProcessor and
 * PreContractTransferProcessor by hand, which is the right shape for pinning
 * those two interactions but leaves the other 27 closing processors untested.
 * Several of them relocate players before completion runs at priority 30 —
 * the two reserve auto-promotions at priorities 4 and 6 especially — and each
 * one silently kills the deal if it forgets TransferOffer::locksPlayer():
 * TransferCompletionService re-asserts the player is still at the club that
 * agreed to sell him, finds he is not, and rejects it as "transfer fell
 * through". That is the recurring shape behind "mis fichajes han
 * desaparecido", so it is pinned here against the real pipeline.
 *
 * Every case also implicitly asserts no offer reaches TransferMarketReset-
 * Processor (priority 70) still agreed: the base TestCase rethrows both
 * LockedPlayerMovedException and AgreedOfferDiscardedException.
 */
class SeasonClosingPreContractDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private const SEASON = '2024';
    private const CURRENT_DATE = '2025-06-10';
    private const CONTRACT_END = '2025-06-30';

    private Game $game;
    private Team $userTeam;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userTeam = Team::factory()->create();
        $this->game = Game::factory()->forTeam($this->userTeam)->create([
            'season' => self::SEASON,
            'current_date' => self::CURRENT_DATE,
        ]);

        $this->seedSeasonFixtures();

        // The club never renews, so every pre-contracted player reaches the
        // boundary via the Bosman path the feature exists to model.
        config()->set('transfers.ai_contract_renewal.veteran_non_renewal', 1.0);
        config()->set('transfers.ai_contract_renewal.non_veteran_non_renewal_base', 1.0);
        config()->set('transfers.ai_contract_renewal.non_veteran_non_renewal_max', 1.0);
    }

    public function test_pre_contract_from_an_ai_first_team_is_delivered(): void
    {
        $sellingTeam = Team::factory()->create();
        $player = $this->expiringPlayerAt($sellingTeam, '1998-01-01');
        $this->agreedPreContractFor($player, $sellingTeam->id);

        app(SeasonClosingPipeline::class)->run($this->game);

        $this->assertJoinedUserTeam($player, 'the ordinary Bosman signing');
    }

    /**
     * The player sits in an AI club's reserve and is old enough that
     * ReserveOveragePromotionProcessor (priority 4) would move him up to the
     * parent first team — long before completion at priority 30 gets to look
     * at him, and to a club that is not the one the offer names as seller.
     */
    public function test_pre_contract_for_an_overage_ai_reserve_player_is_delivered(): void
    {
        $reserve = $this->aiReserveTeam();
        // Born before the next season's U-23 cutoff (2025 - 23 = 2002-01-01).
        $player = $this->expiringPlayerAt($reserve, '1998-01-01');
        $this->agreedPreContractFor($player, $reserve->id);

        app(SeasonClosingPipeline::class)->run($this->game);

        $this->assertJoinedUserTeam($player, 'a signing from an AI reserve squad');
    }

    /**
     * Same club, the other promotion: still U-23 next season but a good enough
     * blend of ability and potential that AIReserveCallUpProcessor (priority 6)
     * would call him up to the parent first team.
     */
    public function test_pre_contract_for_an_ai_reserve_prospect_is_delivered(): void
    {
        $reserve = $this->aiReserveTeam();
        // After the cutoff, so he is still U-23; blend (74 + 80) / 2 = 77
        // clears ReserveTeamService::MIN_PROSPECT_BLEND.
        $player = $this->expiringPlayerAt($reserve, '2003-06-01', [
            'overall_score' => 74,
            'potential' => 80,
        ]);
        $this->agreedPreContractFor($player, $reserve->id);

        app(SeasonClosingPipeline::class)->run($this->game);

        $this->assertJoinedUserTeam($player, 'a signing from an AI reserve prospect pool');
    }

    /**
     * The user already holds the player on loan, so his team_id is the user's
     * club while the selling team still owns his contract. LoanReturnProcessor
     * (priority 5) sends him home before completion.
     */
    public function test_pre_contract_for_a_player_the_user_holds_on_loan_is_delivered(): void
    {
        $sellingTeam = Team::factory()->create();
        $player = $this->expiringPlayerAt($this->userTeam, '1998-01-01');

        Loan::create([
            'game_id' => $this->game->id,
            'game_player_id' => $player->id,
            'parent_team_id' => $sellingTeam->id,
            'loan_team_id' => $this->userTeam->id,
            'started_at' => '2024-08-01',
            'return_at' => self::CONTRACT_END,
            'status' => Loan::STATUS_ACTIVE,
        ]);

        $this->agreedPreContractFor($player, $player->owningTeamId());

        app(SeasonClosingPipeline::class)->run($this->game);

        $this->assertJoinedUserTeam($player, 'a signing the club already had on loan');
    }

    /**
     * When a deal genuinely does fall through, the user has to be told. The
     * notification is raised at priority 30 and StatsResetProcessor sweeps the
     * inbox at priority 65 — it must spare what this transition itself just
     * produced, or the only explanation for a missing signing is marked read
     * before the user can open the new season.
     */
    public function test_a_failed_pre_contract_leaves_an_unread_notification(): void
    {
        // This case moves a locked player on purpose to force the failure.
        Exceptions::fake([LockedPlayerMovedException::class]);

        $sellingTeam = Team::factory()->create();
        $elsewhere = Team::factory()->create();
        $player = $this->expiringPlayerAt($elsewhere, '1998-01-01');
        $this->agreedPreContractFor($player, $sellingTeam->id);

        $lastSeasonsNews = GameNotification::create([
            'game_id' => $this->game->id,
            'type' => GameNotification::TYPE_TRANSFER_OFFER_RECEIVED,
            'title' => 'Older news',
            'priority' => GameNotification::PRIORITY_INFO,
            'game_date' => '2025-02-01',
        ]);

        app(SeasonClosingPipeline::class)->run($this->game);

        $failure = GameNotification::where('game_id', $this->game->id)
            ->where('type', GameNotification::TYPE_TRANSFER_FAILED)
            ->first();

        $this->assertNotNull($failure, 'A pre-contract that fell through must notify the user.');
        $this->assertNull(
            $failure->read_at,
            'The transition must not mark its own report read before the user can see it.',
        );
        $this->assertNotNull(
            $lastSeasonsNews->fresh()?->read_at,
            'Last season\'s notifications should still be swept so the new season starts clean.',
        );
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function assertJoinedUserTeam(GamePlayer $player, string $what): void
    {
        $this->assertSame(
            $this->userTeam->id,
            $player->fresh()?->team_id,
            "The season close must deliver {$what}.",
        );

        $this->assertTrue(
            GameTransfer::where('game_id', $this->game->id)
                ->where('game_player_id', $player->id)
                ->where('to_team_id', $this->userTeam->id)
                ->exists(),
            "A delivered signing must leave a GameTransfer row ({$what}).",
        );
    }

    /** An AI club with a reserve squad; returns the reserve. */
    private function aiReserveTeam(): Team
    {
        $parent = Team::factory()->create();

        return Team::factory()->create(['parent_team_id' => $parent->id]);
    }

    private function expiringPlayerAt(Team $team, string $dateOfBirth, array $attributes = []): GamePlayer
    {
        return GamePlayer::factory()->forGame($this->game)->forTeam($team)->create(array_merge([
            'date_of_birth' => $dateOfBirth,
            'contract_until' => self::CONTRACT_END,
            'annual_wage' => 100_000_000,
            'market_value_cents' => 1_000_000_000,
        ], $attributes));
    }

    private function agreedPreContractFor(GamePlayer $player, string $sellingTeamId): TransferOffer
    {
        return TransferOffer::create([
            'game_id' => $this->game->id,
            'game_player_id' => $player->id,
            'offering_team_id' => $this->userTeam->id,
            'selling_team_id' => $sellingTeamId,
            'offer_type' => TransferOffer::TYPE_PRE_CONTRACT,
            'direction' => TransferOffer::DIRECTION_INCOMING,
            'transfer_fee' => 0,
            'offered_wage' => 150_000_000,
            'offered_years' => 3,
            'status' => TransferOffer::STATUS_AGREED,
            'expires_at' => self::CURRENT_DATE,
            'game_date' => '2025-02-05',
            'resolved_at' => '2025-02-05',
        ]);
    }

    /** The rows the closing pipeline expects a played-out season to have left. */
    private function seedSeasonFixtures(): void
    {
        GameInvestment::create([
            'game_id' => $this->game->id,
            'season' => self::SEASON,
            'transfer_budget' => 50_000_000_00,
            'scouting_tier' => 1,
        ]);

        GameFinances::create([
            'game_id' => $this->game->id,
            'season' => self::SEASON,
            'projected_revenue' => 100_000_000_00,
            'projected_wages' => 50_000_000_00,
            'projected_position' => 10,
        ]);

        GameStanding::create([
            'game_id' => $this->game->id,
            'competition_id' => $this->game->competition_id,
            'team_id' => $this->game->team_id,
            'position' => 10,
            'played' => 38,
            'won' => 10,
            'drawn' => 8,
            'lost' => 20,
            'goals_for' => 40,
            'goals_against' => 60,
            'points' => 38,
        ]);
    }
}
