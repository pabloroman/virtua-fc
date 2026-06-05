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
 * clause to any value above the floor — there is no upper cap. The cost is paid
 * in wages: a clause above the floor raises the wage the player demands to
 * re-sign (golden handcuffs), so underpaying for a big clause makes the player
 * counter or reject through the normal loop. Non-ES clubs can never carry a
 * clause, so the request is ignored there. The clause→demand maths itself is
 * unit-tested in {@see \Tests\Unit\ReleaseClauseCalculationTest}.
 */
class ReleaseClausePhase4Test extends TestCase
{
    use RefreshDatabase;

    private const MV = 5_000_000_000;        // €50M
    private const ES_FLOOR = 6_250_000_000;  // €50M × 1.25
    private const DEMAND = 1_000_000_000;    // €10M renewal demand
    // A clause one full (premium_slope × MV) above the floor doubles the demand:
    // €62.5M + 2.5 × €50M = €187.5M → demand factor 2.0.
    private const BIG_CLAUSE = 18_750_000_000; // €187.5M
    private const COVERING_WAGE = 2_000_000_000; // €20M — covers the €187.5M clause

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('finances.release_clause.es_floor_multiplier', 1.25);
        config()->set('finances.release_clause.tolerance.premium_slope', 2.5);

        Competition::factory()->league()->create(['id' => 'ESP1']);
    }

    public function test_renewal_stores_a_user_raised_clause_unclamped(): void
    {
        [$game, $team] = $this->makeGame(country: 'ES', enabled: true);
        $player = $this->squadPlayer($game, $team);

        // €200M is far above the floor (€62.5M) and there is no cap, so it is
        // stored verbatim. (The wage that justifies it was settled in negotiation.)
        app(ContractService::class)->processRenewal(
            $player,
            self::COVERING_WAGE,
            3,
            requestedClauseCents: 20_000_000_000, // €200M
        );

        $this->assertSame(20_000_000_000, $player->fresh()->release_clause);
    }

    public function test_renewal_without_a_request_still_yields_the_floor(): void
    {
        [$game, $team] = $this->makeGame(country: 'ES', enabled: true);
        $player = $this->squadPlayer($game, $team);

        // No clause request → unchanged Phase-1 behaviour: the mandatory floor.
        app(ContractService::class)->processRenewal(
            $player,
            self::COVERING_WAGE,
            3,
            requestedClauseCents: null,
        );

        $this->assertSame(self::ES_FLOOR, $player->fresh()->release_clause);
    }

    public function test_non_es_renewal_never_creates_a_clause_even_with_a_request(): void
    {
        [$game, $team] = $this->makeGame(country: 'EN', enabled: true);
        $player = $this->squadPlayer($game, $team);

        app(ContractService::class)->processRenewal(
            $player,
            self::COVERING_WAGE,
            3,
            requestedClauseCents: 9_000_000_000,
        );

        $this->assertNull($player->fresh()->release_clause,
            'Non-ES clubs can never carry a clause — the request must be ignored');
    }

    public function test_a_high_clause_at_parity_wage_makes_the_player_counter(): void
    {
        [$game, $team] = $this->makeGame(country: 'ES', enabled: true);
        $player = $this->renewablePlayer($game, $team);

        // Offer exactly the base demand but ask for a €187.5M clause. The clause
        // doubles what the player will accept, so a parity wage falls short and the
        // player counters (rounds remain) — it is NOT silently clamped/accepted.
        $negotiation = $this->openNegotiation($game, $player, userOffer: self::DEMAND, clause: self::BIG_CLAUSE);

        $result = app(ContractService::class)->evaluateOffer($negotiation);

        $this->assertSame('countered', $result);
        $this->assertGreaterThan(self::DEMAND, (int) $negotiation->fresh()->counter_offer,
            'The counter must reflect the clause-raised demand, not the base ask');
        $this->assertNull($player->fresh()->release_clause, 'No clause is written until a deal is struck');
    }

    public function test_a_high_clause_is_accepted_when_the_wage_covers_it(): void
    {
        [$game, $team] = $this->makeGame(country: 'ES', enabled: true);
        $player = $this->renewablePlayer($game, $team);

        // Pay the wage the clause demands (€20M ≈ 2× base) and the same €187.5M
        // clause is accepted and stored unclamped.
        $negotiation = $this->openNegotiation($game, $player, userOffer: self::COVERING_WAGE, clause: self::BIG_CLAUSE);

        $result = app(ContractService::class)->evaluateOffer($negotiation);

        $this->assertSame('accepted', $result);
        $this->assertSame(self::BIG_CLAUSE, (int) $player->fresh()->release_clause);
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
            'counter_offer' => self::COVERING_WAGE,       // agreed at the counter wage
        ]);

        app(ContractService::class)->acceptCounterOffer($negotiation);

        // The counter already priced in the clause, so it is applied as-is.
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
            'counter_offer' => self::COVERING_WAGE,
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
            ->assertJsonPath('clause_floor', 62_500_000)         // €62.5M in euros
            ->assertJsonPath('clause_market_value', 50_000_000)  // €50M in euros
            ->assertJsonPath('clause_premium_slope', 2.5);
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
            'annual_wage' => self::DEMAND, // €10M
            'contract_until' => '2025-06-30',
        ]);
    }

    /**
     * A renewable player whose contract ends this season, so processRenewal will
     * actually write through when a deal is struck.
     */
    private function renewablePlayer(Game $game, Team $team): GamePlayer
    {
        return $this->expiringPlayer($game, $team);
    }

    /**
     * A fresh, round-1 open negotiation with a pinned base demand so the
     * clause→demand mechanic can be asserted deterministically (independent of
     * the wage-demand model).
     */
    private function openNegotiation(Game $game, GamePlayer $player, int $userOffer, int $clause): RenewalNegotiation
    {
        return RenewalNegotiation::create([
            'game_id' => $game->id,
            'game_player_id' => $player->id,
            'status' => RenewalNegotiation::STATUS_OFFER_PENDING,
            'round' => 1,
            'player_demand' => self::DEMAND,
            'preferred_years' => 3,
            'user_offer' => $userOffer,
            'offered_years' => 3, // == preferred → neutral years modifier
            'release_clause_requested' => $clause,
            'counter_offer' => null,
        ]);
    }
}
