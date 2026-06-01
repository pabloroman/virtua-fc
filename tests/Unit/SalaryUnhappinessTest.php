<?php

namespace Tests\Unit;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Modules\Match\Events\GameDateAdvanced;
use App\Modules\Transfer\Enums\NegotiationScenario;
use App\Modules\Transfer\Listeners\RollSalaryUnhappiness;
use App\Modules\Transfer\Services\ContractService;
use App\Modules\Transfer\Services\DispositionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalaryUnhappinessTest extends TestCase
{
    use RefreshDatabase;

    private DispositionService $dispositionService;
    private ContractService $contractService;
    private Team $team;
    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dispositionService = app(DispositionService::class);
        $this->contractService = app(ContractService::class);

        $this->team = Team::factory()->create();
        $this->game = Game::factory()->forTeam($this->team)->create([
            'current_date' => '2026-03-15',
        ]);
    }

    public function test_roll_flags_a_wage_gapped_player_with_current_date(): void
    {
        $this->buildPeers(wage: 1_000_000_00, count: 4);
        $underpaid = $this->makePlayer(annualWage: 100_000_00);

        // Force the roll to hit: stub random_int by seeding mt_rand-equivalent path
        // via a deterministic loop — repeat until flag flips or we exhaust attempts.
        for ($i = 0; $i < 500 && $underpaid->fresh()->salary_unhappy_since === null; $i++) {
            $this->dispositionService->rollSalaryUnhappiness($this->game);
        }

        $underpaid->refresh();
        $this->assertNotNull(
            $underpaid->salary_unhappy_since,
            'Wage-gapped player should have been flagged within 500 rolls at 5% chance',
        );
        $this->assertTrue(
            $underpaid->salary_unhappy_since->equalTo(Carbon::parse('2026-03-15')),
            'Flag date should equal the game current_date at roll time',
        );
    }

    public function test_roll_never_flags_a_non_wage_gapped_player(): void
    {
        // All peers earn near the same wage as the player — no gap.
        $this->buildPeers(wage: 500_000_00, count: 4);
        $contented = $this->makePlayer(annualWage: 500_000_00);

        for ($i = 0; $i < 200; $i++) {
            $this->dispositionService->rollSalaryUnhappiness($this->game);
        }

        $this->assertNull($contented->fresh()->salary_unhappy_since);
    }

    public function test_roll_clears_flagged_player_whose_gap_closed(): void
    {
        $this->buildPeers(wage: 1_000_000_00, count: 4);
        $player = $this->makePlayer(
            annualWage: 100_000_00,
            salaryUnhappySince: '2026-01-01',
        );

        // Manager raises wage above the 60% wage-gap floor — gap is now closed.
        $player->update(['annual_wage' => 700_000_00]);

        $result = $this->dispositionService->rollSalaryUnhappiness($this->game);

        $this->assertSame(1, $result['cleared']);
        $this->assertNull($player->fresh()->salary_unhappy_since);
    }

    public function test_drip_only_affects_flagged_players(): void
    {
        $this->buildPeers(wage: 1_000_000_00, count: 4);

        // Two underpaid players, only one is flagged.
        $flagged = $this->makePlayer(
            annualWage: 100_000_00,
            salaryUnhappySince: '2026-01-01',
            morale: 80,
        );
        $unflagged = $this->makePlayer(
            annualWage: 100_000_00,
            salaryUnhappySince: null,
            morale: 80,
        );

        $affected = $this->dispositionService->applyWageGapMoraleDrip($this->game);

        $this->assertSame(1, $affected);
        $this->assertSame(80 - DispositionService::WAGE_GAP_MORALE_DRIP, $flagged->fresh()->morale);
        $this->assertSame(80, $unflagged->fresh()->morale);
    }

    public function test_renewal_pulls_demand_partway_toward_peer_median_for_unflagged_player(): void
    {
        // Build a band of well-paid same-ability peers so the peer median is high.
        $this->buildPeers(wage: 1_000_000_00, count: 4);
        $player = $this->makePlayer(
            annualWage: 200_000_00,
            salaryUnhappySince: null, // explicitly NOT flagged
        );

        $demand = $this->contractService->calculateWageDemand($player, NegotiationScenario::RENEWAL);

        // Partial pull (not a hard floor): the demand rises above the player's
        // current wage toward the peer median, but stops short of fully matching
        // it so a manager can still run a wage scale.
        $this->assertGreaterThan(
            200_000_00,
            $demand['wage'],
            'An underpaid player should demand a raise toward the peer median',
        );
        $this->assertLessThan(
            1_000_000_00,
            $demand['wage'],
            'The peer median is a partial pull, not a hard floor — demand should stop short of it',
        );
    }

    public function test_renewal_pulls_demand_partway_toward_peer_median_for_flagged_player(): void
    {
        $this->buildPeers(wage: 1_000_000_00, count: 4);
        $player = $this->makePlayer(
            annualWage: 200_000_00,
            salaryUnhappySince: '2026-01-01',
        );

        $demand = $this->contractService->calculateWageDemand($player, NegotiationScenario::RENEWAL);

        $this->assertGreaterThan(200_000_00, $demand['wage']);
        $this->assertLessThan(1_000_000_00, $demand['wage']);
    }

    public function test_low_ability_youth_is_not_priced_against_star_squadmates(): void
    {
        // A squad of well-paid stars (high ability) plus a modest-ability
        // youngster on a youth wage. Grouping by current ability means the
        // youth is NOT compared to the stars, so his renewal stays sane rather
        // than exploding to the star median.
        for ($i = 0; $i < 4; $i++) {
            GamePlayer::factory()
                ->forGame($this->game)
                ->forTeam($this->team)
                ->create([
                    'overall_score' => 88,
                    'date_of_birth' => self::PRIME_DOB,
                    'annual_wage' => 1_400_000_00, // €14M stars
                ]);
        }

        $youth = GamePlayer::factory()
            ->forGame($this->game)
            ->forTeam($this->team)
            ->create([
                'overall_score' => 58, // modest current ability
                'date_of_birth' => '2006-06-01', // ~20yo
                'annual_wage' => 7_000_00, // €70K youth wage
            ]);

        $demand = $this->contractService->calculateWageDemand($youth, NegotiationScenario::RENEWAL);

        // Sanity, not savagery: a raise, but nowhere near the €14M star median.
        $this->assertGreaterThan(7_000_00, $demand['wage']);
        $this->assertLessThan(
            1_400_000_00,
            $demand['wage'],
            'A low-ability youth must not be priced against high-ability squadmates',
        );
    }

    public function test_renewal_clears_flag_synchronously_when_gap_closes(): void
    {
        $this->buildPeers(wage: 1_000_000_00, count: 4);
        $player = $this->makePlayer(
            annualWage: 200_000_00,
            salaryUnhappySince: '2026-01-01',
        );

        // Renewal at peer median fully closes the gap → flag should clear immediately.
        $this->contractService->processRenewal($player, newWage: 1_000_000_00, contractYears: 3);

        $this->assertNull($player->fresh()->salary_unhappy_since);
    }

    public function test_listener_delegates_to_service(): void
    {
        $this->buildPeers(wage: 1_000_000_00, count: 4);
        $player = $this->makePlayer(
            annualWage: 100_000_00,
            salaryUnhappySince: '2026-01-01',
        );
        // Manager already raised wage — listener should clear via the service.
        $player->update(['annual_wage' => 800_000_00]);

        $listener = new RollSalaryUnhappiness($this->dispositionService);
        $listener->handle(new GameDateAdvanced(
            $this->game,
            Carbon::parse('2026-03-14'),
            Carbon::parse('2026-03-15'),
        ));

        $this->assertNull($player->fresh()->salary_unhappy_since);
    }

    // ── helpers ──

    // Peer medians are grouped by current ability (overall_score ± band), so
    // every player/peer is pinned to the same score and a prime age (stable
    // wage modifier) to keep the comparison deterministic.
    private const PEER_OVERALL_SCORE = 70;
    private const PRIME_DOB = '1997-06-01';

    private function makePlayer(
        int $annualWage,
        ?string $salaryUnhappySince = null,
        ?int $morale = null,
    ): GamePlayer {
        $attrs = [
            'overall_score' => self::PEER_OVERALL_SCORE,
            'date_of_birth' => self::PRIME_DOB,
            'annual_wage' => $annualWage,
            'salary_unhappy_since' => $salaryUnhappySince,
        ];
        if ($morale !== null) {
            $attrs['morale'] = $morale;
        }
        return GamePlayer::factory()
            ->forGame($this->game)
            ->forTeam($this->team)
            ->create($attrs);
    }

    private function buildPeers(int $wage, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            GamePlayer::factory()
                ->forGame($this->game)
                ->forTeam($this->team)
                ->create([
                    'overall_score' => self::PEER_OVERALL_SCORE,
                    'date_of_birth' => self::PRIME_DOB,
                    'annual_wage' => $wage,
                ]);
        }
    }
}
