<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GameInvestment;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Models\TransferOffer;
use App\Models\User;
use App\Modules\Transfer\Enums\NegotiationScenario;
use App\Modules\Transfer\Services\ContractService;
use App\Modules\Transfer\Services\TransferCompletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Release-clause negotiation during an INCOMING signing into the (ES) club —
 * buy transfer, Bosman pre-contract, or free agent. Mirrors the renewal flow:
 * the manager may set any clause above the mandatory floor during personal
 * terms; a clause above the floor raises the wage the player demands (golden
 * handcuffs), and the agreed clause is written through at completion. Non-ES
 * clubs can never carry a clause, so the control is hidden and any request is
 * dropped. The clause→demand maths is unit-tested in
 * {@see \Tests\Unit\ReleaseClauseCalculationTest}.
 */
class IncomingSigningClauseTest extends TestCase
{
    use RefreshDatabase;

    private const MV = 5_000_000_000;        // €50M
    private const ES_FLOOR = 6_250_000_000;  // €50M × 1.25
    // A clause one full (premium_slope × MV) above the floor doubles the demand:
    // €62.5M + 2.5 × €50M = €187.5M → demand factor 2.0.
    private const BIG_CLAUSE = 18_750_000_000; // €187.5M
    private const NEGOTIATED_CLAUSE = 10_000_000_000; // €100M (above the floor)
    private const BELOW_MARKET_CLAUSE = 3_000_000_000; // €30M (below MV, above the €12.5M min)
    private const BUDGET = 20_000_000_000;   // €200M

    private User $user;
    private Team $userTeam;
    private Team $sellerTeam;
    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('finances.release_clause.es_floor_multiplier', 1.25);
        config()->set('finances.release_clause.es_min_multiplier', 0.25);
        config()->set('finances.release_clause.tolerance.premium_slope', 2.5);

        $this->user = User::factory()->create();
        $this->userTeam = Team::factory()->create(['country' => 'ES']);
        $this->sellerTeam = Team::factory()->create(['country' => 'ES']);

        Competition::factory()->league()->create(['id' => 'ESP1']);

        $this->game = Game::factory()->forTeam($this->userTeam)->create([
            'user_id' => $this->user->id,
            'competition_id' => 'ESP1',
            'country' => 'ES',
            'season' => '2024',
            'current_date' => '2025-08-01',
            'release_clauses_enabled' => true,
        ]);

        GameInvestment::create([
            'game_id' => $this->game->id,
            'season' => $this->game->season,
            'transfer_budget' => self::BUDGET,
            'scouting_tier' => 1,
        ]);
    }

    // ── Clause payload + gating ─────────────────────────────────────────────

    public function test_release_clause_payload_is_exposed_for_an_es_buyer(): void
    {
        $player = $this->targetPlayer($this->sellerTeam);

        $payload = app(ContractService::class)->releaseClausePayload($this->game, $player, 1_000_000_000);

        $this->assertSame(true, $payload['clause_enabled']);
        $this->assertSame(62_500_000, $payload['clause_floor']);        // €62.5M in euros
        $this->assertSame(12_500_000, $payload['clause_min']);          // €12.5M (0.25 × value)
        $this->assertSame(50_000_000, $payload['clause_market_value']); // €50M in euros
        $this->assertSame(10_000_000, $payload['clause_demand']);       // base demand in euros
        $this->assertSame(2.5, $payload['clause_premium_slope']);
    }

    public function test_release_clause_payload_is_empty_for_a_non_es_buyer(): void
    {
        $enTeam = Team::factory()->create(['country' => 'EN']);
        $enGame = Game::factory()->forTeam($enTeam)->create([
            'competition_id' => 'ESP1',
            'country' => 'EN',
            'release_clauses_enabled' => true,
        ]);
        $player = $this->targetPlayer($this->sellerTeam);

        $this->assertSame([], app(ContractService::class)->releaseClausePayload($enGame, $player, 1_000_000_000));
    }

    public function test_resolve_requested_clause_gates_on_es_and_the_flag(): void
    {
        $service = app(ContractService::class);

        // ES + feature on → euros converted to cents.
        $this->assertSame(9_000_000_000, $service->resolveRequestedClauseCents(90_000_000, $this->game));

        // A null request is always null.
        $this->assertNull($service->resolveRequestedClauseCents(null, $this->game));

        // Non-ES club → dropped.
        $enGame = Game::factory()->forTeam(Team::factory()->create(['country' => 'EN']))->create([
            'competition_id' => 'ESP1',
            'country' => 'EN',
            'release_clauses_enabled' => true,
        ]);
        $this->assertNull($service->resolveRequestedClauseCents(90_000_000, $enGame));

        // Feature off → dropped.
        $offGame = Game::factory()->forTeam($this->userTeam)->create([
            'competition_id' => 'ESP1',
            'country' => 'ES',
            'release_clauses_enabled' => false,
        ]);
        $this->assertNull($service->resolveRequestedClauseCents(90_000_000, $offGame));
    }

    // ── Clause raises the wage demand during personal terms ─────────────────

    public function test_a_clause_at_the_floor_accepts_a_parity_wage_offer(): void
    {
        $player = $this->targetPlayer($this->sellerTeam);
        $demand = app(ContractService::class)->calculateWageDemand($player, NegotiationScenario::TRANSFER, $this->userTeam);
        $offer = $this->feeAgreedOffer($player);

        // Offer exactly the base demand at the preferred length, clause at the
        // floor → the clause adds nothing, so the player accepts.
        $result = app(ContractService::class)->negotiateTermsSync(
            $offer,
            (int) $demand['wage'],
            (int) $demand['contractYears'],
            NegotiationScenario::TRANSFER,
            $this->game,
            self::ES_FLOOR,
        );

        $this->assertSame('accepted', $result['result']);
        $this->assertSame(self::ES_FLOOR, (int) $offer->fresh()->release_clause_requested);
    }

    public function test_a_high_clause_at_a_parity_wage_makes_the_player_hold_out(): void
    {
        $player = $this->targetPlayer($this->sellerTeam);
        $demand = app(ContractService::class)->calculateWageDemand($player, NegotiationScenario::TRANSFER, $this->userTeam);
        $offer = $this->feeAgreedOffer($player);

        // Same parity wage, but ask for a €187.5M clause that doubles what the
        // player will accept → the offer falls short and is NOT accepted, and the
        // manager's clause request is persisted for later rounds / completion.
        $result = app(ContractService::class)->negotiateTermsSync(
            $offer,
            (int) $demand['wage'],
            (int) $demand['contractYears'],
            NegotiationScenario::TRANSFER,
            $this->game,
            self::BIG_CLAUSE,
        );

        $this->assertNotSame('accepted', $result['result']);
        $this->assertSame(self::BIG_CLAUSE, (int) $offer->fresh()->release_clause_requested);
        $this->assertGreaterThan(
            (int) $demand['wage'],
            (int) $offer->fresh()->wage_counter_offer,
            'The counter must reflect the clause-raised demand, not the base ask',
        );
    }

    // ── Completion writes the negotiated clause ─────────────────────────────

    public function test_completed_buy_transfer_writes_the_negotiated_clause(): void
    {
        $player = $this->targetPlayer($this->sellerTeam);
        $offer = $this->feeAgreedOffer($player, [
            'transfer_fee' => 1_000_000_000,
            'offered_wage' => 2_000_000_000,
            'offered_years' => 4,
            'release_clause_requested' => self::NEGOTIATED_CLAUSE,
        ]);

        app(TransferCompletionService::class)->completeIncomingTransfer($offer, $this->game);

        $player->refresh();
        $this->assertSame($this->userTeam->id, $player->team_id);
        $this->assertSame(self::NEGOTIATED_CLAUSE, (int) $player->release_clause);
    }

    public function test_completed_buy_transfer_writes_a_below_market_value_clause(): void
    {
        $player = $this->targetPlayer($this->sellerTeam);
        $offer = $this->feeAgreedOffer($player, [
            'transfer_fee' => 1_000_000_000,
            'offered_wage' => 2_000_000_000,
            'offered_years' => 4,
            // €30M — below the €62.5M floor and below the €50M market value, but
            // above the €12.5M minimum, so it is honoured as the manager set it.
            'release_clause_requested' => self::BELOW_MARKET_CLAUSE,
        ]);

        app(TransferCompletionService::class)->completeIncomingTransfer($offer, $this->game);

        $this->assertSame(self::BELOW_MARKET_CLAUSE, (int) $player->fresh()->release_clause);
    }

    public function test_buy_transfer_without_a_request_falls_back_to_the_floor(): void
    {
        $player = $this->targetPlayer($this->sellerTeam);
        $offer = $this->feeAgreedOffer($player, [
            'transfer_fee' => 1_000_000_000,
            'offered_wage' => 2_000_000_000,
            'offered_years' => 4,
            'release_clause_requested' => null,
        ]);

        app(TransferCompletionService::class)->completeIncomingTransfer($offer, $this->game);

        // Untouched clause → mandatory floor, preserving prior behaviour.
        $this->assertSame(self::ES_FLOOR, (int) $player->fresh()->release_clause);
    }

    public function test_completed_free_agent_signing_writes_the_negotiated_clause(): void
    {
        $player = $this->targetPlayer(null); // unattached
        $offer = TransferOffer::create([
            'game_id' => $this->game->id,
            'game_player_id' => $player->id,
            'offering_team_id' => $this->userTeam->id,
            'selling_team_id' => null,
            'offer_type' => TransferOffer::TYPE_USER_BID,
            'direction' => TransferOffer::DIRECTION_INCOMING,
            'transfer_fee' => 0,
            'status' => TransferOffer::STATUS_AGREED,
            'offered_wage' => 2_000_000_000,
            'offered_years' => 3,
            'release_clause_requested' => self::NEGOTIATED_CLAUSE,
            'expires_at' => $this->game->current_date->copy()->addDays(14),
            'game_date' => $this->game->current_date,
            'negotiation_round' => 1,
        ]);

        app(TransferCompletionService::class)->completeFreeAgentSigning($this->game, $player, $offer);

        $player->refresh();
        $this->assertSame($this->userTeam->id, $player->team_id);
        $this->assertSame(self::NEGOTIATED_CLAUSE, (int) $player->release_clause);
    }

    public function test_completed_pre_contract_writes_the_negotiated_clause(): void
    {
        $player = $this->targetPlayer($this->sellerTeam);
        $offer = TransferOffer::create([
            'game_id' => $this->game->id,
            'game_player_id' => $player->id,
            'offering_team_id' => $this->userTeam->id,
            'selling_team_id' => $this->sellerTeam->id,
            'offer_type' => TransferOffer::TYPE_PRE_CONTRACT,
            'direction' => TransferOffer::DIRECTION_INCOMING,
            'transfer_fee' => 0,
            'status' => TransferOffer::STATUS_AGREED,
            'offered_wage' => 2_000_000_000,
            'offered_years' => 3,
            'release_clause_requested' => self::NEGOTIATED_CLAUSE,
            'expires_at' => $this->game->current_date->copy()->addDays(14),
            'game_date' => $this->game->current_date,
            'negotiation_round' => 1,
        ]);

        app(TransferCompletionService::class)->completePreContractTransfer($offer);

        $player->refresh();
        $this->assertSame($this->userTeam->id, $player->team_id);
        $this->assertSame(self::NEGOTIATED_CLAUSE, (int) $player->release_clause);
    }

    // ── HTTP: start_terms surfaces the clause control (countered-resume path) ─

    public function test_start_terms_exposes_the_clause_control_for_an_es_buy(): void
    {
        $player = $this->targetPlayer($this->sellerTeam);
        // A countered terms offer so start_terms returns the resume payload
        // without rolling the reputation willingness gate.
        $this->feeAgreedOffer($player, [
            'terms_status' => 'countered',
            'terms_round' => 1,
            'player_demand' => 1_000_000_000,
            'preferred_years' => 3,
            'offered_wage' => 900_000_000,
            'offered_years' => 3,
            'wage_counter_offer' => 1_000_000_000,
        ]);

        $this->actingAs($this->user)
            ->postJson(route('game.negotiate.transfer', [$this->game->id, $player->id]), ['action' => 'start_terms'])
            ->assertOk()
            ->assertJsonPath('clause_enabled', true)
            ->assertJsonPath('clause_floor', 62_500_000)
            ->assertJsonPath('clause_market_value', 50_000_000)
            ->assertJsonPath('clause_premium_slope', 2.5);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function targetPlayer(?Team $team): GamePlayer
    {
        return GamePlayer::factory()->forGame($this->game)->age(26)->create([
            'team_id' => $team?->id,
            'market_value_cents' => self::MV,
            'overall_score' => 75,
            'annual_wage' => 1_000_000_000,
            'release_clause' => null,
            'contract_until' => '2027-06-30',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function feeAgreedOffer(GamePlayer $player, array $overrides = []): TransferOffer
    {
        return TransferOffer::create(array_merge([
            'game_id' => $this->game->id,
            'game_player_id' => $player->id,
            'offering_team_id' => $this->userTeam->id,
            'selling_team_id' => $this->sellerTeam->id,
            'offer_type' => TransferOffer::TYPE_USER_BID,
            'direction' => TransferOffer::DIRECTION_INCOMING,
            'transfer_fee' => 1_000_000_000,
            'status' => TransferOffer::STATUS_FEE_AGREED,
            'expires_at' => $this->game->current_date->copy()->addDays(14),
            'game_date' => $this->game->current_date,
            'negotiation_round' => 1,
        ], $overrides));
    }
}
