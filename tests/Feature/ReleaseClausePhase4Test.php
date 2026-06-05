<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\RenewalNegotiation;
use App\Models\Team;
use App\Models\User;
use App\Modules\Transfer\Services\ContractService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase-4 behaviour: during a renewal the manager may raise the mandatory ES
 * clause within [floor, maxTolerable(offeredWage)]. The clause request rides on
 * the negotiation row so it survives counter rounds, and is applied (clamped)
 * when the renewal is agreed. Non-ES clubs can never carry a clause, so the
 * request is ignored there. The golden-handcuffs maths itself is unit-tested in
 * {@see \Tests\Unit\ReleaseClauseCalculationTest}.
 */
class ReleaseClausePhase4Test extends TestCase
{
    use RefreshDatabase;

    private const MV = 5_000_000_000;        // €50M
    private const ES_FLOOR = 6_250_000_000;  // €50M × 1.25
    private const DEMAND = 1_000_000_000;    // €10M renewal demand
    private const HIGH_WAGE = 1_500_000_000; // €15M → ratio 1.5 → tolerance hard cap (2.5×)

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('finances.release_clause.es_floor_multiplier', 1.25);
        config()->set('finances.release_clause.tolerance.base', 1.25);
        config()->set('finances.release_clause.tolerance.premium_slope', 2.5);
        config()->set('finances.release_clause.tolerance.hard_cap', 2.5);

        Competition::factory()->league()->create(['id' => 'ESP1']);
    }

    public function test_renewal_stores_a_user_raised_clause_within_tolerance(): void
    {
        [$game, $team] = $this->makeGame(country: 'ES', enabled: true);
        $player = $this->squadPlayer($game, $team);

        // €90M is above the floor (€62.5M) and below the cap (€50M × 2.5 = €125M),
        // so it is stored verbatim.
        app(ContractService::class)->processRenewal(
            $player,
            self::HIGH_WAGE,
            3,
            requestedClauseCents: 9_000_000_000,
            wageDemandCents: self::DEMAND,
        );

        $this->assertSame(9_000_000_000, $player->fresh()->release_clause);
    }

    public function test_renewal_clamps_a_clause_above_the_tolerable_cap(): void
    {
        [$game, $team] = $this->makeGame(country: 'ES', enabled: true);
        $player = $this->squadPlayer($game, $team);

        $service = app(ContractService::class);
        $service->processRenewal(
            $player,
            self::HIGH_WAGE,
            3,
            requestedClauseCents: 20_000_000_000, // €200M — well over the cap
            wageDemandCents: self::DEMAND,
        );

        // Clamped to the wage-scaled maximum the player tolerates.
        $expected = $service->maxTolerableReleaseClause(self::MV, self::HIGH_WAGE, self::DEMAND);
        $this->assertSame($expected, $player->fresh()->release_clause);
    }

    public function test_renewal_without_a_request_still_yields_the_floor(): void
    {
        [$game, $team] = $this->makeGame(country: 'ES', enabled: true);
        $player = $this->squadPlayer($game, $team);

        // Real wage/demand are passed but no clause request → unchanged Phase-1
        // behaviour: the mandatory floor, regardless of the wage premium.
        app(ContractService::class)->processRenewal(
            $player,
            self::HIGH_WAGE,
            3,
            requestedClauseCents: null,
            wageDemandCents: self::DEMAND,
        );

        $this->assertSame(self::ES_FLOOR, $player->fresh()->release_clause);
    }

    public function test_non_es_renewal_never_creates_a_clause_even_with_a_request(): void
    {
        [$game, $team] = $this->makeGame(country: 'EN', enabled: true);
        $player = $this->squadPlayer($game, $team);

        app(ContractService::class)->processRenewal(
            $player,
            self::HIGH_WAGE,
            3,
            requestedClauseCents: 9_000_000_000,
            wageDemandCents: self::DEMAND,
        );

        $this->assertNull($player->fresh()->release_clause,
            'Non-ES clubs can never carry a clause — the request must be ignored');
    }

    public function test_accepting_a_counter_applies_the_persisted_clause_request(): void
    {
        [$game, $team] = $this->makeGame(country: 'ES', enabled: true);
        $player = $this->squadPlayer($game, $team);

        // A countered negotiation carrying the manager's earlier clause request.
        $negotiation = RenewalNegotiation::create([
            'game_id' => $game->id,
            'game_player_id' => $player->id,
            'status' => RenewalNegotiation::STATUS_PLAYER_COUNTERED,
            'round' => 1,
            'player_demand' => self::DEMAND,
            'preferred_years' => 3,
            'user_offer' => 1_200_000_000,
            'offered_years' => 3,
            'release_clause_requested' => 9_000_000_000, // €90M
            'counter_offer' => self::HIGH_WAGE,          // agreed at the counter wage
        ]);

        app(ContractService::class)->acceptCounterOffer($negotiation);

        // €90M sits inside [floor, cap] at the counter wage, so it is applied as-is.
        $this->assertSame(9_000_000_000, $player->fresh()->release_clause);
    }

    public function test_submit_new_offer_persists_the_updated_clause_request(): void
    {
        [$game, $team] = $this->makeGame(country: 'ES', enabled: true);
        $player = $this->squadPlayer($game, $team);

        $negotiation = RenewalNegotiation::create([
            'game_id' => $game->id,
            'game_player_id' => $player->id,
            'status' => RenewalNegotiation::STATUS_PLAYER_COUNTERED,
            'round' => 1,
            'player_demand' => self::DEMAND,
            'preferred_years' => 3,
            'user_offer' => 1_200_000_000,
            'offered_years' => 3,
            'release_clause_requested' => 7_000_000_000,
            'counter_offer' => self::HIGH_WAGE,
        ]);

        app(ContractService::class)->submitNewOffer($negotiation, 1_300_000_000, 3, 8_000_000_000);

        $this->assertSame(8_000_000_000, $negotiation->fresh()->release_clause_requested);
    }

    public function test_offer_endpoint_stores_the_clause_for_an_es_club(): void
    {
        [$game, $team, $user] = $this->makeGame(country: 'ES', enabled: true, withUser: true);
        $player = $this->expiringPlayer($game, $team);

        $this->actingAs($user)
            ->postJson(route('game.negotiate.renewal', [$game->id, $player->id]), [
                'action' => 'offer',
                'wage' => 50_000,       // below current wage → salary cap is a no-op
                'years' => 3,
                'clause' => 90_000_000, // €90M
            ])
            ->assertOk();

        $negotiation = RenewalNegotiation::where('game_player_id', $player->id)->first();
        $this->assertNotNull($negotiation);
        $this->assertSame(9_000_000_000, $negotiation->release_clause_requested);
    }

    public function test_offer_endpoint_ignores_the_clause_for_a_non_es_club(): void
    {
        [$game, $team, $user] = $this->makeGame(country: 'EN', enabled: true, withUser: true);
        $player = $this->expiringPlayer($game, $team);

        $this->actingAs($user)
            ->postJson(route('game.negotiate.renewal', [$game->id, $player->id]), [
                'action' => 'offer',
                'wage' => 50_000,
                'years' => 3,
                'clause' => 90_000_000,
            ])
            ->assertOk();

        $negotiation = RenewalNegotiation::where('game_player_id', $player->id)->first();
        $this->assertNotNull($negotiation);
        $this->assertNull($negotiation->release_clause_requested,
            'A clause sent for a non-ES club must be dropped');
    }

    public function test_start_endpoint_exposes_clause_config_for_an_es_club(): void
    {
        [$game, $team, $user] = $this->makeGame(country: 'ES', enabled: true, withUser: true);
        $player = $this->expiringPlayer($game, $team);

        $this->actingAs($user)
            ->postJson(route('game.negotiate.renewal', [$game->id, $player->id]), ['action' => 'start'])
            ->assertOk()
            ->assertJsonPath('clause_enabled', true)
            ->assertJsonPath('clause_floor', 62_500_000)        // €62.5M in euros
            ->assertJsonPath('clause_market_value', 50_000_000); // €50M in euros
    }

    public function test_start_endpoint_omits_clause_config_for_a_non_es_club(): void
    {
        [$game, $team, $user] = $this->makeGame(country: 'EN', enabled: true, withUser: true);
        $player = $this->expiringPlayer($game, $team);

        $response = $this->actingAs($user)
            ->postJson(route('game.negotiate.renewal', [$game->id, $player->id]), ['action' => 'start'])
            ->assertOk();

        $this->assertNull($response->json('clause_enabled'),
            'Non-ES clubs must not receive clause config');
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    /**
     * @return array{0: Game, 1: Team, 2: User}
     */
    private function makeGame(string $country, bool $enabled, bool $withUser = false): array
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['country' => $country]);

        $game = Game::factory()->forTeam($team)->create([
            'user_id' => $user->id,
            'competition_id' => 'ESP1',
            'country' => $country,
            'season' => '2024',
            'current_date' => '2025-02-15',
            'release_clauses_enabled' => $enabled,
        ]);

        return [$game, $team, $user];
    }

    private function squadPlayer(Game $game, Team $team): GamePlayer
    {
        return GamePlayer::factory()->forGame($game)->forTeam($team)->create([
            'market_value_cents' => self::MV,
            'release_clause' => null,
            'pending_annual_wage' => null,
            'contract_until' => '2026-06-30',
        ]);
    }

    /**
     * A last-year player who will engage in renewal talks, with a wage high
     * enough that a modest offer never trips the salary cap.
     */
    private function expiringPlayer(Game $game, Team $team): GamePlayer
    {
        return GamePlayer::factory()->forGame($game)->forTeam($team)->age(28)->create([
            'market_value_cents' => self::MV,
            'overall_score' => 70,
            'release_clause' => null,
            'pending_annual_wage' => null,
            'annual_wage' => 1_000_000_000, // €10M
            'contract_until' => '2025-06-30',
        ]);
    }
}
