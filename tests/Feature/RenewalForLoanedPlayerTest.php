<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Loan;
use App\Models\Player;
use App\Models\Team;
use App\Models\User;
use App\Modules\Transfer\Services\ContractService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the regression where a contract renewal could not be negotiated for
 * a player whose last-year contract was loaned out mid-season. The parent
 * club should retain full contract authority while the player is away.
 */
class RenewalForLoanedPlayerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Team $userTeam;
    private Team $otherTeam;
    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->userTeam = Team::factory()->create(['name' => 'User Team']);
        $this->otherTeam = Team::factory()->create(['name' => 'Other Team']);
        Competition::factory()->league()->create(['id' => 'ESP1']);

        $this->game = Game::factory()->create([
            'user_id' => $this->user->id,
            'team_id' => $this->userTeam->id,
            'competition_id' => 'ESP1',
            'season' => '2024',
            'current_date' => '2025-02-15',
        ]);
    }

    public function test_loaned_out_player_can_still_be_offered_renewal(): void
    {
        $player = $this->createExpiringPlayer();
        $this->loanPlayerOut($player, $this->otherTeam);

        $player->refresh()->load('game', 'activeLoan');

        $this->assertSame($this->otherTeam->id, $player->team_id,
            'Sanity check: loaning-out moves team_id to the borrowing club');
        $this->assertTrue($player->isLoanedOut($this->userTeam->id));
        $this->assertTrue(
            $player->canBeOfferedRenewal($this->game->getSeasonEndDate()),
            'A player loaned out by the user team must still be renewable'
        );
    }

    public function test_loaned_in_player_still_cannot_be_offered_renewal(): void
    {
        $player = $this->createExpiringPlayer(team: $this->otherTeam);
        $this->loanPlayerIn($player, parentTeam: $this->otherTeam);

        $player->refresh()->load('game', 'activeLoan');

        $this->assertSame($this->userTeam->id, $player->team_id);
        $this->assertTrue($player->isLoanedIn($this->userTeam->id));
        $this->assertFalse(
            $player->canBeOfferedRenewal($this->game->getSeasonEndDate()),
            'Loaned-in players belong to another club and must not be renewable by us'
        );
    }

    public function test_get_players_eligible_for_renewal_includes_loaned_out_player(): void
    {
        $onSite = $this->createExpiringPlayer();
        $loanedOut = $this->createExpiringPlayer();
        $this->loanPlayerOut($loanedOut, $this->otherTeam);

        $eligible = app(ContractService::class)->getPlayersEligibleForRenewal($this->game);
        $eligibleIds = $eligible->pluck('id')->all();

        $this->assertContains($onSite->id, $eligibleIds);
        $this->assertContains($loanedOut->id, $eligibleIds,
            'Players loaned out by the user team must appear in the renewal list'
        );
    }

    public function test_get_players_eligible_for_renewal_excludes_loaned_in_player(): void
    {
        $loanedIn = $this->createExpiringPlayer(team: $this->otherTeam);
        $this->loanPlayerIn($loanedIn, parentTeam: $this->otherTeam);

        $eligible = app(ContractService::class)->getPlayersEligibleForRenewal($this->game);

        $this->assertNotContains($loanedIn->id, $eligible->pluck('id')->all(),
            'Players loaned in from another club must never show in the renewal list'
        );
    }

    public function test_negotiate_renewal_endpoint_accepts_loaned_out_player(): void
    {
        $player = $this->createExpiringPlayer();
        $this->loanPlayerOut($player, $this->otherTeam);

        $response = $this->actingAs($this->user)
            ->postJson(
                route('game.negotiate.renewal', [$this->game->id, $player->id]),
                ['action' => 'start']
            );

        $response->assertOk();
        $response->assertJsonPath('status', 'ok');
        $response->assertJsonPath('negotiation_status', 'open');
    }

    public function test_negotiate_renewal_endpoint_rejects_loaned_in_player(): void
    {
        $player = $this->createExpiringPlayer(team: $this->otherTeam);
        $this->loanPlayerIn($player, parentTeam: $this->otherTeam);

        $response = $this->actingAs($this->user)
            ->postJson(
                route('game.negotiate.renewal', [$this->game->id, $player->id]),
                ['action' => 'start']
            );

        // Loaned-in players are not owned by us — the scope must not find them.
        $response->assertNotFound();
    }

    public function test_process_renewal_stores_pending_wage_for_loaned_out_player(): void
    {
        $player = $this->createExpiringPlayer();
        $this->loanPlayerOut($player, $this->otherTeam);
        $player->refresh();

        $success = app(ContractService::class)
            ->processRenewal($player, newWage: 500_000_00, contractYears: 2);

        $this->assertTrue($success);

        $player->refresh();
        $this->assertSame(500_000_00, $player->pending_annual_wage);
        $this->assertTrue(
            $player->contract_until->greaterThan($this->game->getSeasonEndDate()),
            'Contract end date should be pushed beyond the current season'
        );
    }

    // =====================================================
    // Helpers
    // =====================================================

    private function createExpiringPlayer(?Team $team = null): GamePlayer
    {
        $player = Player::factory()->age(28)->create([
            'technical_ability' => 70,
            'physical_ability' => 70,
        ]);

        return GamePlayer::factory()->create([
            'game_id' => $this->game->id,
            'player_id' => $player->id,
            'team_id' => ($team ?? $this->userTeam)->id,
            'position' => 'Central Midfield',
            // Contract expires before the season end (June 30) → "last year"
            'contract_until' => '2025-06-30',
            'annual_wage' => 200_000_00,
        ]);
    }

    private function loanPlayerOut(GamePlayer $player, Team $destination): Loan
    {
        $loan = Loan::create([
            'game_id' => $this->game->id,
            'game_player_id' => $player->id,
            'parent_team_id' => $this->userTeam->id,
            'loan_team_id' => $destination->id,
            'started_at' => $this->game->current_date,
            'return_at' => $this->game->getSeasonEndDate(),
            'status' => Loan::STATUS_ACTIVE,
        ]);

        // Mirror LoanService::processLoanOut: the player physically moves.
        $player->update(['team_id' => $destination->id, 'number' => null]);

        return $loan;
    }

    private function loanPlayerIn(GamePlayer $player, Team $parentTeam): Loan
    {
        $loan = Loan::create([
            'game_id' => $this->game->id,
            'game_player_id' => $player->id,
            'parent_team_id' => $parentTeam->id,
            'loan_team_id' => $this->userTeam->id,
            'started_at' => $this->game->current_date,
            'return_at' => $this->game->getSeasonEndDate(),
            'status' => Loan::STATUS_ACTIVE,
        ]);

        $player->update(['team_id' => $this->userTeam->id]);

        return $loan;
    }
}
