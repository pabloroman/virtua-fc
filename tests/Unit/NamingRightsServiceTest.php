<?php

namespace Tests\Unit;

use App\Models\Competition;
use App\Models\FinancialTransaction;
use App\Models\Game;
use App\Models\GameFinances;
use App\Models\GameInvestment;
use App\Models\GameMatch;
use App\Models\GameStadium;
use App\Models\GameStadiumNamingDeal;
use App\Models\MatchAttendance;
use App\Models\Team;
use App\Models\TeamReputation;
use App\Modules\Finance\Services\BudgetProjectionService;
use App\Modules\Finance\Services\SalaryCapService;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Stadium\Services\FanLoyaltyService;
use App\Modules\Stadium\Services\GameStadiumNameResolver;
use App\Modules\Stadium\Services\MatchAttendanceService;
use App\Modules\Stadium\Services\NamingRightsService;
use App\Modules\Stadium\Services\StadiumCapacityResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

class NamingRightsServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;
    use RefreshDatabase;

    private MatchAttendanceService $attendance;
    private StadiumCapacityResolver $capacity;
    private NotificationService $notifications;
    private NamingRightsService $service;
    private Competition $league;
    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->attendance = Mockery::mock(MatchAttendanceService::class);
        $this->capacity = Mockery::mock(StadiumCapacityResolver::class);
        $this->notifications = Mockery::mock(NotificationService::class);
        $this->notifications->shouldReceive('create')->byDefault();

        $this->service = new NamingRightsService(
            $this->attendance,
            $this->capacity,
            new GameStadiumNameResolver(),
            new FanLoyaltyService(),
            $this->notifications,
        );

        $this->league = Competition::factory()->league()->create(['tier' => 1]);
        $this->team = Team::factory()->create(['stadium_seats' => 10_000]);
    }

    public function test_accepting_an_offer_activates_it_brands_the_stadium_and_shocks_loyalty(): void
    {
        $game = $this->preSeasonGame();
        $this->seedLoyalty($game, base: 90, current: 90);
        $stadium = $this->seedStadium($game);

        $offer = $this->seedOffer($game, status: GameStadiumNamingDeal::STATUS_PENDING, value: 5_000_000_00, seasons: 4);
        $other = $this->seedOffer($game, status: GameStadiumNamingDeal::STATUS_PENDING);

        $this->service->acceptOffer($game, $offer->id);

        $offer->refresh();
        $this->assertSame(GameStadiumNamingDeal::STATUS_ACTIVE, $offer->status);
        $this->assertSame(2026, $offer->start_season);
        $this->assertSame(2029, $offer->end_season); // 2026 + 4 - 1

        // Competing offer was rejected.
        $this->assertSame(GameStadiumNamingDeal::STATUS_REJECTED, $other->fresh()->status);

        // Stadium took the sponsor's name.
        $this->assertSame($offer->proposed_stadium_name, $stadium->fresh()->stadium_name);

        // One-time loyalty shock = round(90 * 0.12) = 11 → 90 - 11 = 79.
        $this->assertSame(79, $this->loyaltyFor($game));
    }

    public function test_accepting_outside_the_window_is_rejected(): void
    {
        // Not pre-season and no fixtures → next league matchday is null → closed.
        $game = Game::factory()->forTeam($this->team)->inCompetition($this->league->id)
            ->create(['pre_season' => false, 'season' => 2026]);
        $offer = $this->seedOffer($game, status: GameStadiumNamingDeal::STATUS_PENDING);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('messages.naming_rights_window_closed');

        $this->service->acceptOffer($game, $offer->id);
    }

    public function test_cannot_accept_a_second_deal_while_one_is_active(): void
    {
        $game = $this->preSeasonGame();
        $this->seedOffer($game, status: GameStadiumNamingDeal::STATUS_ACTIVE);
        $pending = $this->seedOffer($game, status: GameStadiumNamingDeal::STATUS_PENDING);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('messages.naming_rights_deal_active');

        $this->service->acceptOffer($game, $pending->id);
    }

    public function test_rename_sets_the_name_then_blocks_a_second_rename_in_the_same_season(): void
    {
        $game = $this->preSeasonGame();
        $this->seedStadium($game);

        $this->service->rename($game, 'Catedral del Norte');
        $this->assertSame('Catedral del Norte', $this->stadiumFor($game)->stadium_name);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('messages.stadium_already_renamed');
        $this->service->rename($game, 'Another Name');
    }

    public function test_rename_is_blocked_while_a_naming_deal_is_active(): void
    {
        $game = $this->preSeasonGame();
        $this->seedStadium($game);
        $this->seedOffer($game, status: GameStadiumNamingDeal::STATUS_ACTIVE);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('messages.naming_rights_deal_active');
        $this->service->rename($game, 'Fan Owned Park');
    }

    public function test_settled_revenue_scales_with_realised_attendance(): void
    {
        $game = $this->preSeasonGame();
        $deal = $this->seedOffer($game, status: GameStadiumNamingDeal::STATUS_ACTIVE, value: 1_000_000_00, seasons: 3);
        $deal->update(['start_season' => 2026, 'end_season' => 2028]);

        // Two played home league fixtures, each 80% full (8,000 / 10,000).
        $matches = collect([
            $this->homeLeagueMatch($game),
            $this->homeLeagueMatch($game),
        ]);

        foreach ($matches as $match) {
            $this->attendance->shouldReceive('resolveForMatch')
                ->with(Mockery::on(fn ($m) => $m->id === $match->id), $game)
                ->andReturn(new MatchAttendance(['attendance' => 8_000, 'capacity_at_match' => 10_000]));
        }

        // 1,000,000,00 cents × 0.8 = 800,000,00.
        $this->assertSame(800_000_00, $this->service->settledRevenueForGame($game));
    }

    public function test_projected_revenue_scales_by_expected_fill(): void
    {
        $game = $this->preSeasonGame();
        $deal = $this->seedOffer($game, status: GameStadiumNamingDeal::STATUS_ACTIVE, value: 1_000_000_00);
        $deal->update(['start_season' => 2026, 'end_season' => 2030]);

        $this->capacity->shouldReceive('effectiveCapacity')->andReturn(10_000);
        $this->attendance->shouldReceive('projectBaselineForTeam')->andReturn(7_000);

        // 1,000,000,00 × 0.7 = 700,000,00.
        $this->assertSame(700_000_00, $this->service->projectedRevenueForGame($game));
    }

    public function test_projected_and_settled_revenue_are_zero_without_an_active_deal(): void
    {
        $game = $this->preSeasonGame();

        $this->assertSame(0, $this->service->projectedRevenueForGame($game));
        $this->assertSame(0, $this->service->settledRevenueForGame($game));
    }

    public function test_seek_sponsors_mints_offers_up_to_the_cap_and_charges_the_fee(): void
    {
        $game = $this->preSeasonGame();
        $game->update(['current_date' => '2026-07-01']);
        $this->seedLoyalty($game, base: 60, current: 60); // tier 'established'
        $this->seedInvestment($game, transferBudget: 5_000_000_00);
        config()->set('finances.naming_rights.max_pending_offers', 3);
        config()->set('finances.naming_rights.search_fee.established', 80_000_00);

        $minted = $this->service->seekSponsors($game);

        $this->assertCount(3, $minted);
        $this->assertSame(3, GameStadiumNamingDeal::where('game_id', $game->id)
            ->where('status', GameStadiumNamingDeal::STATUS_PENDING)->count());

        // Fee charged: transfer budget decremented + an agency-fee expense logged.
        $this->assertSame(5_000_000_00 - 80_000_00, (int) GameInvestment::where('game_id', $game->id)->value('transfer_budget'));
        $this->assertSame(1, FinancialTransaction::where('game_id', $game->id)
            ->where('category', FinancialTransaction::CATEGORY_AGENT_FEE)->count());

        // Cooldown stamped to the search date.
        $this->assertSame('2026-07-01', $this->stadiumFor($game)->naming_rights_last_sought_date->toDateString());
    }

    public function test_seek_sponsors_is_blocked_outside_the_window(): void
    {
        // Not pre-season and no fixtures → next league matchday null → closed.
        $game = Game::factory()->forTeam($this->team)->inCompetition($this->league->id)
            ->create(['pre_season' => false, 'season' => 2026]);
        $this->seedLoyalty($game, base: 60, current: 60);
        $this->seedInvestment($game, transferBudget: 5_000_000_00);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('messages.naming_rights_window_closed');

        $this->service->seekSponsors($game);
    }

    public function test_seek_sponsors_is_blocked_while_a_deal_is_active(): void
    {
        $game = $this->preSeasonGame();
        $this->seedLoyalty($game, base: 60, current: 60);
        $this->seedInvestment($game, transferBudget: 5_000_000_00);
        $this->seedOffer($game, status: GameStadiumNamingDeal::STATUS_ACTIVE);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('messages.naming_rights_deal_active');

        $this->service->seekSponsors($game);
    }

    public function test_seek_sponsors_respects_the_cooldown(): void
    {
        $game = $this->preSeasonGame();
        $game->update(['current_date' => '2026-07-05']);
        $this->seedLoyalty($game, base: 60, current: 60);
        $this->seedInvestment($game, transferBudget: 5_000_000_00);
        config()->set('finances.naming_rights.search_cooldown_days', 14);

        // Sought 5 days ago → still inside the 14-day cooldown.
        $this->seedStadium($game)->update(['naming_rights_last_sought_date' => '2026-06-30']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('messages.naming_rights_search_cooldown');

        $this->service->seekSponsors($game);
    }

    public function test_seek_sponsors_is_blocked_when_the_fee_is_unaffordable(): void
    {
        $game = $this->preSeasonGame();
        $this->seedLoyalty($game, base: 60, current: 60);
        $this->seedInvestment($game, transferBudget: 10_00); // only €10 of budget
        config()->set('finances.naming_rights.search_fee.established', 80_000_00);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('messages.naming_rights_search_unaffordable');

        $this->service->seekSponsors($game);
    }

    public function test_accepting_an_offer_raises_the_salary_cap(): void
    {
        $game = $this->preSeasonGame();
        $this->seedLoyalty($game, base: 50, current: 50);
        $this->seedStadium($game);

        GameFinances::create([
            'game_id' => $game->id,
            'season' => 2026,
            'projected_total_revenue' => 10_000_000_00,
            'projected_surplus' => 2_000_000_00,
            'projected_naming_rights_revenue' => 0,
        ]);

        $offer = $this->seedOffer($game, status: GameStadiumNamingDeal::STATUS_PENDING, value: 1_000_000_00);

        // Expected fill 0.7 → projected naming rights = 700,000.00.
        $this->capacity->shouldReceive('effectiveCapacity')->andReturn(10_000);
        $this->attendance->shouldReceive('projectBaselineForTeam')->andReturn(7_000);

        config()->set('finances.wage_cap_ratio', 0.70);
        $salaryCap = new SalaryCapService(Mockery::mock(BudgetProjectionService::class));

        $capBefore = $salaryCap->cap($game);
        $this->service->acceptOffer($game, $offer->id);
        $capAfter = $salaryCap->cap($game->fresh());

        // Recurring naming income lifts the ceiling by 700,000.00 × 0.70.
        $this->assertSame(490_000_00, $capAfter - $capBefore);
    }

    public function test_offers_never_exceed_the_pending_cap_or_duplicate_a_sponsor(): void
    {
        $game = $this->preSeasonGame();
        $this->seedLoyalty($game, base: 60, current: 60);
        config()->set('finances.naming_rights.max_pending_offers', 3);

        // Force as many as possible; the cap and sponsor-dedupe bound it.
        for ($i = 0; $i < 10; $i++) {
            $this->service->forceOffer($game);
        }

        $pending = GameStadiumNamingDeal::where('game_id', $game->id)
            ->where('status', GameStadiumNamingDeal::STATUS_PENDING)->get();

        $this->assertSame(3, $pending->count());
        $this->assertSame($pending->count(), $pending->pluck('sponsor_name')->unique()->count());
    }

    public function test_rollover_expires_an_ended_deal_and_restores_the_pre_deal_name(): void
    {
        $game = $this->preSeasonGame();
        $stadium = $this->seedStadium($game);

        $ended = $this->seedOffer($game, status: GameStadiumNamingDeal::STATUS_ACTIVE);
        $ended->update([
            'start_season' => 2022,
            'end_season' => 2025, // ended before 2026
            'previous_stadium_name' => 'Catedral del Norte', // a custom rename predating the deal
        ]);
        $stadium->update(['stadium_name' => $ended->proposed_stadium_name]);

        $this->service->rolloverForNewSeason($game);

        $this->assertSame(GameStadiumNamingDeal::STATUS_EXPIRED, $ended->fresh()->status);
        // Name reverts to the manager's own rename, not the historic default.
        $this->assertSame('Catedral del Norte', $stadium->fresh()->stadium_name);
        // Rollover mints no offers — the manager seeks them from the Commercial page.
        $this->assertSame(0, GameStadiumNamingDeal::where('game_id', $game->id)
            ->where('status', GameStadiumNamingDeal::STATUS_PENDING)->count());
    }

    public function test_accepting_after_a_rename_restores_the_custom_name_when_the_deal_expires(): void
    {
        $game = $this->preSeasonGame();
        $this->seedLoyalty($game, base: 60, current: 60);
        $this->seedStadium($game);

        // Manager renames the ground first.
        $this->service->rename($game, 'Mi Estadio');

        // A one-season sponsor offer arrives and is accepted.
        $offer = $this->seedOffer($game, status: GameStadiumNamingDeal::STATUS_PENDING, seasons: 1);
        $this->service->acceptOffer($game, $offer->id);

        $offer->refresh();
        $this->assertSame('Mi Estadio', $offer->previous_stadium_name);
        $this->assertSame($offer->proposed_stadium_name, $this->stadiumFor($game)->stadium_name);

        // Next season the deal has ended; the ground reverts to the rename,
        // not the historic name.
        $game->update(['season' => 2027]);
        $this->service->rolloverForNewSeason($game);

        $this->assertSame('Mi Estadio', $this->stadiumFor($game)->stadium_name);
    }

    public function test_expiry_restores_the_historic_name_when_there_was_no_rename(): void
    {
        $game = $this->preSeasonGame();
        $stadium = $this->seedStadium($game); // no custom name → historic

        $ended = $this->seedOffer($game, status: GameStadiumNamingDeal::STATUS_ACTIVE);
        $ended->update([
            'start_season' => 2022,
            'end_season' => 2025,
            'previous_stadium_name' => null, // no rename predated the deal
        ]);
        $stadium->update(['stadium_name' => $ended->proposed_stadium_name]);

        $this->service->rolloverForNewSeason($game);

        $this->assertNull($stadium->fresh()->stadium_name);
    }

    public function test_accepting_folds_projected_income_into_current_finances(): void
    {
        $game = $this->preSeasonGame();
        $this->seedLoyalty($game, base: 50, current: 50);
        $this->seedStadium($game);

        $finances = GameFinances::create([
            'game_id' => $game->id,
            'season' => 2026,
            'projected_total_revenue' => 10_000_000_00,
            'projected_surplus' => 2_000_000_00,
            'projected_naming_rights_revenue' => 0,
        ]);

        $offer = $this->seedOffer($game, status: GameStadiumNamingDeal::STATUS_PENDING, value: 1_000_000_00);

        // Expected fill 0.7 → projected naming rights = 700,000,00.
        $this->capacity->shouldReceive('effectiveCapacity')->andReturn(10_000);
        $this->attendance->shouldReceive('projectBaselineForTeam')->andReturn(7_000);

        $this->service->acceptOffer($game, $offer->id);

        $finances->refresh();
        $this->assertSame(700_000_00, $finances->projected_naming_rights_revenue);
        $this->assertSame(10_700_000_00, $finances->projected_total_revenue);
        $this->assertSame(2_700_000_00, $finances->projected_surplus);
    }

    public function test_venue_name_reflects_the_game_scoped_override(): void
    {
        $game = $this->preSeasonGame();
        $this->seedStadium($game, name: 'Estadio Renombrado');

        $match = $this->homeLeagueMatch($game);

        $this->assertSame('Estadio Renombrado', $match->venueName());
    }

    public function test_venue_name_falls_back_to_the_team_stadium_name(): void
    {
        $this->team->update(['stadium_name' => 'Estadio Histórico']);
        $game = $this->preSeasonGame();
        $this->seedStadium($game); // no override name

        $match = $this->homeLeagueMatch($game);

        $this->assertSame('Estadio Histórico', $match->venueName());
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function preSeasonGame(): Game
    {
        return Game::factory()->forTeam($this->team)->inCompetition($this->league->id)
            ->create(['pre_season' => true, 'season' => 2026]);
    }

    private function seedLoyalty(Game $game, int $base, int $current): TeamReputation
    {
        return TeamReputation::create([
            'game_id' => $game->id,
            'team_id' => $game->team_id,
            'reputation_level' => 'established',
            'base_reputation_level' => 'established',
            'reputation_points' => 250,
            'base_loyalty' => $base,
            'loyalty_points' => $current,
        ]);
    }

    private function loyaltyFor(Game $game): int
    {
        return (int) TeamReputation::where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->value('loyalty_points');
    }

    private function seedStadium(Game $game, ?string $name = null): GameStadium
    {
        return GameStadium::create([
            'game_id' => $game->id,
            'team_id' => $game->team_id,
            'stadium_name' => $name,
            'base_capacity' => 10_000,
        ]);
    }

    private function stadiumFor(Game $game): GameStadium
    {
        return GameStadium::where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->firstOrFail();
    }

    private function seedInvestment(Game $game, int $transferBudget): GameInvestment
    {
        return GameInvestment::create([
            'game_id' => $game->id,
            'season' => $game->season,
            'transfer_budget' => $transferBudget,
        ]);
    }

    private function seedOffer(
        Game $game,
        string $status,
        int $value = 1_000_000_00,
        int $seasons = 3,
    ): GameStadiumNamingDeal {
        static $i = 0;
        $i++;

        return GameStadiumNamingDeal::create([
            'game_id' => $game->id,
            'team_id' => $game->team_id,
            'sponsor_name' => "Sponsor {$i}",
            'proposed_stadium_name' => "Sponsor {$i} Arena",
            'annual_value_cents' => $value,
            'contract_seasons' => $seasons,
            'status' => $status,
            'offered_season' => 2026,
        ]);
    }

    private function homeLeagueMatch(Game $game): GameMatch
    {
        return GameMatch::factory()
            ->forGame($game)
            ->forCompetition($this->league)
            ->create([
                'home_team_id' => $game->team_id,
                'away_team_id' => Team::factory()->create()->id,
                'played' => true,
            ]);
    }
}
