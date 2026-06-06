<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Models\User;
use App\Modules\Transfer\Enums\NegotiationScenario;
use App\Modules\Transfer\Services\ContractService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the renewal wage-demand re-anchoring: the base wage is anchored to the
 * lesser of the player's ability-derived value and his *real* market value, so
 * an established star no longer demands double/triple his wage off the generous
 * ability anchors while equivalent players at rival clubs (priced off market
 * value) earn far less. Wonderkids and veterans keep their existing treatment.
 */
class RenewalWageDemandTest extends TestCase
{
    use RefreshDatabase;

    private ContractService $contractService;
    private Competition $competition;
    private Game $game;

    private const REFERENCE_DATE = '2024-08-15';

    protected function setUp(): void
    {
        parent::setUp();

        $this->contractService = app(ContractService::class);

        $this->competition = Competition::factory()->league()->create([
            'id' => 'ESP1',
            'name' => 'LaLiga',
        ]);

        $user = User::factory()->create();
        $userTeam = Team::factory()->create();

        $this->game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $userTeam->id,
            'competition_id' => $this->competition->id,
            'season' => '2024',
            'current_date' => self::REFERENCE_DATE,
        ]);
    }

    public function test_established_star_demand_is_capped_by_real_market_value(): void
    {
        // Same ability/age/wage/tier — only the real market value differs. The
        // ability anchor for overall 85 (~€60M) sits well above a €40M market
        // value, so the low-MV star is pulled down to the market rate while the
        // high-MV star (whose MV exceeds the anchor) keeps the ability-based wage.
        $lowMarketValueStar = $this->makePlayer(
            overall: 85,
            age: 27,
            marketValueCents: 40_000_000_00,
            wageCents: 4_000_000_00,
            tier: 4,
        );
        $highMarketValueStar = $this->makePlayer(
            overall: 85,
            age: 27,
            marketValueCents: 100_000_000_00,
            wageCents: 4_000_000_00,
            tier: 4,
        );

        $lowDemand = $this->contractService->calculateWageDemand($lowMarketValueStar, NegotiationScenario::RENEWAL)['wage'];
        $highDemand = $this->contractService->calculateWageDemand($highMarketValueStar, NegotiationScenario::RENEWAL)['wage'];

        $this->assertLessThan(
            $highDemand,
            $lowDemand,
            'A star with a lower real market value should demand less than an identical star whose market value exceeds the ability anchor.'
        );
    }

    public function test_established_star_renewal_is_not_double_or_triple_current_wage(): void
    {
        // The reported bug: a seeded star earning ~€4M demands €11-20M on renewal.
        // With market-value anchoring the demand is a sane raise, not a multiple.
        $star = $this->makePlayer(
            overall: 85,
            age: 27,
            marketValueCents: 40_000_000_00,
            wageCents: 4_000_000_00,
            tier: 4,
        );

        $demand = $this->contractService->calculateWageDemand($star, NegotiationScenario::RENEWAL)['wage'];

        $this->assertGreaterThan(
            $star->annual_wage,
            $demand,
            'Renewal demand must still be a raise over the current wage.'
        );
        $this->assertLessThan(
            $star->annual_wage * 2,
            $demand,
            'Renewal demand must not balloon to double (or triple) the current wage.'
        );
    }

    public function test_wonderkid_demand_is_unaffected_by_potential_inflated_market_value(): void
    {
        // A wonderkid's market value carries a potential premium that sits ABOVE
        // his (youth-stripped) ability value, so the min() cap binds to the ability
        // value regardless of how high the market value is — the existing wonderkid
        // protection. Two identical youths with very different market values must
        // therefore demand the same wage.
        $modestValueKid = $this->makePlayer(
            overall: 70,
            age: 18,
            marketValueCents: 15_000_000_00,
            wageCents: 50_000_00,
            tier: 3,
        );
        $hypedKid = $this->makePlayer(
            overall: 70,
            age: 18,
            marketValueCents: 80_000_000_00,
            wageCents: 50_000_00,
            tier: 3,
        );

        $modestDemand = $this->contractService->calculateWageDemand($modestValueKid, NegotiationScenario::RENEWAL)['wage'];
        $hypedDemand = $this->contractService->calculateWageDemand($hypedKid, NegotiationScenario::RENEWAL)['wage'];

        $this->assertSame(
            $modestDemand,
            $hypedDemand,
            'A wonderkid demand should be anchored to ability, not his potential-inflated market value.'
        );
    }

    public function test_veteran_demand_ignores_the_market_value_cap(): void
    {
        // Veterans are exempt from the market-value cap: wageBaseValue() already
        // preserves their age decline and the veteran wage modifier is calibrated
        // against that depressed value. A veteran with a market value BELOW his
        // ability anchor must NOT be pulled down to it — so two veterans with
        // different (one below-anchor) market values still demand the same wage.
        $lowValueVeteran = $this->makePlayer(
            overall: 80,
            age: 36,
            marketValueCents: 5_000_000_00,
            wageCents: 6_000_000_00,
            tier: 3,
        );
        $highValueVeteran = $this->makePlayer(
            overall: 80,
            age: 36,
            marketValueCents: 50_000_000_00,
            wageCents: 6_000_000_00,
            tier: 3,
        );

        $lowDemand = $this->contractService->calculateWageDemand($lowValueVeteran, NegotiationScenario::RENEWAL)['wage'];
        $highDemand = $this->contractService->calculateWageDemand($highValueVeteran, NegotiationScenario::RENEWAL)['wage'];

        $this->assertSame(
            $lowDemand,
            $highDemand,
            'A veteran demand must ignore the market-value cap so a below-anchor market value does not depress it.'
        );
    }

    /**
     * Create a player on his own team (attached to the league) inside the shared
     * game. A dedicated team keeps each player out of the others' peer-median
     * wage pool so demands stay independent.
     */
    private function makePlayer(int $overall, int $age, int $marketValueCents, int $wageCents, int $tier): GamePlayer
    {
        $team = Team::factory()->create();
        $team->competitions()->attach($this->competition->id, ['season' => '2024']);

        $player = GamePlayer::factory()->age($age, self::REFERENCE_DATE)->create([
            'game_id' => $this->game->id,
            'team_id' => $team->id,
            'overall_score' => $overall,
            'market_value_cents' => $marketValueCents,
            'annual_wage' => $wageCents,
            'tier' => $tier,
            'position' => 'Central Midfield',
        ]);

        $player->load('team');

        return $player;
    }
}
