<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GameFinances;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Models\TransferOffer;
use App\Models\User;
use App\Modules\Finance\Enums\SigningContext;
use App\Modules\Finance\Services\WageBudgetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WageBudgetServiceTest extends TestCase
{
    use RefreshDatabase;

    private WageBudgetService $service;
    private Competition $competition;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(WageBudgetService::class);

        $this->competition = Competition::factory()->league()->create([
            'id' => 'ESP1',
            'name' => 'LaLiga',
        ]);

        // Wage cap default for tests: 100% ratio, €100K buffer
        config([
            'finances.wage_cap_ratio' => 1.0,
            'finances.wage_cap_ratio_by_tier' => [],
            'finances.wage_cap_buffer_cents' => 10_000_000,
        ]);
    }

    public function test_current_season_headroom_uses_revenue_minus_squad_wages(): void
    {
        $game = $this->createGameWithRevenue(100_000_000_00); // €1M revenue
        $this->addSquadPlayer($game, 30_000_000_00); // €300K wage
        $this->addSquadPlayer($game, 20_000_000_00); // €200K wage

        $headroom = $this->service->currentSeasonHeadroom($game);

        $this->assertSame(100_000_000_00, $headroom->projectedRevenue);
        $this->assertSame(50_000_000_00, $headroom->currentSquadWages);
        // cap = revenue * 1.0 + €100K buffer
        $this->assertSame(100_000_000_00 + 10_000_000, $headroom->cap());
        $this->assertSame((100_000_000_00 + 10_000_000) - 50_000_000_00, $headroom->headroom());
    }

    public function test_next_season_headroom_counts_pending_pre_contracts(): void
    {
        $game = $this->createGameWithRevenue(100_000_000_00);
        // One player whose contract carries over
        $this->addSquadPlayer($game, 40_000_000_00, contractUntil: '2030-06-30');
        // A pending pre-contract for an external player
        $externalPlayer = $this->makeExternalPlayer($game);
        TransferOffer::create([
            'game_id' => $game->id,
            'game_player_id' => $externalPlayer->id,
            'offering_team_id' => $game->team_id,
            'selling_team_id' => $externalPlayer->team_id,
            'offer_type' => TransferOffer::TYPE_PRE_CONTRACT,
            'direction' => TransferOffer::DIRECTION_INCOMING,
            'transfer_fee' => 0,
            'offered_wage' => 25_000_000_00,
            'status' => TransferOffer::STATUS_PENDING,
            'expires_at' => $game->current_date->copy()->addDays(60),
            'game_date' => $game->current_date,
        ]);

        $headroom = $this->service->nextSeasonHeadroom($game);

        $this->assertSame(40_000_000_00, $headroom->currentSquadWages);
        $this->assertSame(25_000_000_00, $headroom->pendingPreContractWages);
        $this->assertSame(65_000_000_00, $headroom->committedWages());
    }

    public function test_can_afford_rejects_signing_that_breaches_cap(): void
    {
        $game = $this->createGameWithRevenue(50_000_000_00); // €500K revenue
        $this->addSquadPlayer($game, 45_000_000_00); // €450K wage

        // €100K signing pushes total to €550K — over the €500K + €100K buffer = €600K? actually within
        // Make it bigger: try €200K signing → total €650K, over €600K cap
        $decision = $this->service->canAfford($game, 20_000_000_00, SigningContext::TRANSFER);

        $this->assertFalse($decision->allowed);
        $this->assertGreaterThan(0, $decision->shortfallCents);
    }

    public function test_can_afford_allows_signing_within_buffer(): void
    {
        $game = $this->createGameWithRevenue(50_000_000_00); // €500K
        $this->addSquadPlayer($game, 49_900_000_00); // €499K

        // €200K signing → €499K + €200K = €699K, cap = €500K + €100K = €600K — over
        // Try €100K signing → €499K + €100K = €599K, under cap
        $okDecision = $this->service->canAfford($game, 10_000_000_00, SigningContext::TRANSFER);
        $this->assertTrue($okDecision->allowed);

        // €200K — clearly over
        $rejectDecision = $this->service->canAfford($game, 20_000_000_00, SigningContext::TRANSFER);
        $this->assertFalse($rejectDecision->allowed);
    }

    public function test_renewal_context_never_hard_rejects(): void
    {
        $game = $this->createGameWithRevenue(50_000_000_00);
        $this->addSquadPlayer($game, 90_000_000_00); // Already over cap

        $decision = $this->service->canAfford($game, 50_000_000_00, SigningContext::RENEWAL);

        $this->assertTrue($decision->allowed); // soft mode never blocks
        $this->assertGreaterThan(0, $decision->shortfallCents);
    }

    public function test_pre_contract_context_skips_current_season(): void
    {
        $game = $this->createGameWithRevenue(50_000_000_00);
        // Already at cap for current season
        $this->addSquadPlayer($game, 60_000_000_00, contractUntil: '2024-06-30'); // expires this season

        // PRE_CONTRACT only cares about next season — current squad expiring doesn't carry
        $decision = $this->service->canAfford($game, 30_000_000_00, SigningContext::PRE_CONTRACT);

        $this->assertTrue($decision->allowed);
    }

    public function test_freeable_wages_returns_players_ordered_by_wage_desc(): void
    {
        $game = $this->createGameWithRevenue(100_000_000_00);
        $p1 = $this->addSquadPlayer($game, 10_000_000_00);
        $p2 = $this->addSquadPlayer($game, 30_000_000_00);
        $p3 = $this->addSquadPlayer($game, 20_000_000_00);

        $freeables = $this->service->freeableWages($game, 25_000_000_00);

        $this->assertGreaterThanOrEqual(2, $freeables->count());
        $this->assertSame($p2->id, $freeables->first()->id); // highest wage first
    }

    // ── helpers ──

    private function createGameWithRevenue(int $revenueCents, string $season = '2024'): Game
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();

        $game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'competition_id' => $this->competition->id,
            'current_date' => "{$season}-09-01",
            'season' => $season,
        ]);

        GameFinances::create([
            'game_id' => $game->id,
            'season' => (int) $season,
            'projected_total_revenue' => $revenueCents,
        ]);

        return $game;
    }

    private function addSquadPlayer(Game $game, int $annualWage, ?string $contractUntil = null): GamePlayer
    {
        return GamePlayer::factory()->create([
            'game_id' => $game->id,
            'team_id' => $game->team_id,
            'annual_wage' => $annualWage,
            'contract_until' => $contractUntil ?? ((int) $game->season + 2) . '-06-30',
        ]);
    }

    private function makeExternalPlayer(Game $game): GamePlayer
    {
        $otherTeam = Team::factory()->create();

        return GamePlayer::factory()->create([
            'game_id' => $game->id,
            'team_id' => $otherTeam->id,
            'contract_until' => $game->getSeasonEndDate()->toDateString(),
            'annual_wage' => 15_000_000_00,
        ]);
    }
}
