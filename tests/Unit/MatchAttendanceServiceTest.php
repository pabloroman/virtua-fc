<?php

namespace Tests\Unit;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\SeasonTicketPricing;
use App\Models\Team;
use App\Models\TeamReputation;
use App\Modules\Stadium\Services\DemandCurveService;
use App\Modules\Stadium\Services\GameStadiumResolver;
use App\Modules\Stadium\Services\MatchAttendanceService;
use App\Modules\Stadium\Services\SeasonTicketPricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the special-venue resolution shared by describeForMatch() and
 * projectForMatch() (sold-out rounds, neutral venues) and the season-ticket
 * attendance composition (attending holders + walk-up, with no-shows).
 */
class MatchAttendanceServiceTest extends TestCase
{
    use RefreshDatabase;

    private MatchAttendanceService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $resolver = new GameStadiumResolver();
        $this->service = new MatchAttendanceService(
            new DemandCurveService(),
            new SeasonTicketPricingService($resolver),
            $resolver,
        );
    }

    public function test_sold_out_round_plays_to_a_full_house(): void
    {
        $home = Team::factory()->create(['stadium_seats' => 25_000]);
        $away = Team::factory()->create();
        $game = Game::factory()->forTeam($home)->create();

        $match = GameMatch::factory()->forGame($game)->between($home, $away)->create([
            'round_name' => 'cup.final',
        ]);

        $result = $this->service->describeForMatch($match, $game);

        $this->assertSame(25_000, $result['attendance']);
        $this->assertSame(25_000, $result['capacity']);
    }

    public function test_neutral_venue_is_a_full_house_for_describe_but_null_for_projection(): void
    {
        $home = Team::factory()->create(['stadium_seats' => 25_000]);
        $away = Team::factory()->create();
        $game = Game::factory()->forTeam($home)->create();

        $match = GameMatch::factory()->forGame($game)->between($home, $away)->create([
            'round_name' => 'Matchday 5',
            'neutral_venue_name' => 'Estadio Neutral',
            'neutral_venue_capacity' => 40_000,
        ]);

        $this->assertSame(
            ['attendance' => 40_000, 'capacity' => 40_000],
            $this->service->describeForMatch($match, $game),
        );
        $this->assertNull($this->service->projectForMatch($match, $game));
    }

    public function test_attending_holders_floor_the_gate_minus_no_shows(): void
    {
        $home = Team::factory()->create(['stadium_seats' => 20_000]);
        $away = Team::factory()->create();
        $league = Competition::factory()->create([
            'role' => Competition::ROLE_LEAGUE,
            'handler_type' => 'league',
        ]);
        $game = Game::factory()->forTeam($home)->inCompetition($league->id)->create(['season' => 2026]);

        // Crashed home loyalty → raw demand ~50% (≈10k), well below holders.
        $this->seedReputation($game, $home, 'local', 0);
        $this->seedReputation($game, $away, 'local', 50);

        SeasonTicketPricing::create([
            'game_id' => $game->id,
            'season' => 2026,
            'areas' => [],
            'total_capacity' => 20_000,
            'total_sold' => 18_000,
            'total_revenue' => 0,
            'pricing_preset' => 'standard',
            'is_default' => false,
        ]);

        $match = GameMatch::factory()->forGame($game)->forCompetition($league)->between($home, $away)->create([
            'round_name' => 'Matchday 3',
        ]);

        $result = $this->service->describeForMatch($match, $game);

        // Demand (~10k) is far below the 18k holders, so there is no walk-up:
        // the gate is just the attending holders, i.e. 18k minus the 5%
        // no-show = 17,100. (Pre no-show this floored at the full 18k.)
        $this->assertSame(17_100, $result['attendance']);
    }

    public function test_walkup_fills_demand_above_the_abono_base(): void
    {
        $home = Team::factory()->create(['stadium_seats' => 20_000]);
        $away = Team::factory()->create();
        $league = Competition::factory()->create([
            'role' => Competition::ROLE_LEAGUE,
            'handler_type' => 'league',
        ]);
        $game = Game::factory()->forTeam($home)->inCompetition($league->id)->create(['season' => 2026]);

        // High home loyalty → demand (~90% ≈ 18k) well above the abono base.
        $this->seedReputation($game, $home, 'established', 90);
        $this->seedReputation($game, $away, 'local', 50);

        SeasonTicketPricing::create([
            'game_id' => $game->id,
            'season' => 2026,
            'areas' => [],
            'total_capacity' => 20_000,
            'total_sold' => 10_000,
            'total_revenue' => 0,
            'pricing_preset' => 'standard',
            'is_default' => false,
        ]);

        $match = GameMatch::factory()->forGame($game)->forCompetition($league)->between($home, $away)->create([
            'round_name' => 'Matchday 3',
        ]);

        $result = $this->service->describeForMatch($match, $game);

        // Gate = attending holders (10k − 5% = 9,500) + walk-up demand on top,
        // so it sits well above the full holder count and never exceeds capacity.
        $this->assertGreaterThan(10_000, $result['attendance']);
        $this->assertLessThanOrEqual(20_000, $result['attendance']);
    }

    public function test_occupancy_responds_to_the_pricing_preset(): void
    {
        // Same club, same holder count — only the preset differs. The preset's
        // occupancy factor scales total demand, so cheaper prices draw a bigger
        // crowd than premium pricing (which prices some fans out).
        $premium = $this->attendanceForPreset('premium');
        $accessible = $this->attendanceForPreset('accessible');

        $this->assertGreaterThan($premium, $accessible);
    }

    private function attendanceForPreset(string $preset): int
    {
        $home = Team::factory()->create(['stadium_seats' => 20_000]);
        $away = Team::factory()->create();
        $league = Competition::factory()->create([
            'role' => Competition::ROLE_LEAGUE,
            'handler_type' => 'league',
        ]);
        $game = Game::factory()->forTeam($home)->inCompetition($league->id)->create(['season' => 2026]);

        // High demand (loyalty 80) so the factor has room to move the crowd.
        $this->seedReputation($game, $home, 'established', 80);
        $this->seedReputation($game, $away, 'local', 50);

        SeasonTicketPricing::create([
            'game_id' => $game->id,
            'season' => 2026,
            'areas' => [],
            'total_capacity' => 20_000,
            'total_sold' => 9_000,
            'total_revenue' => 0,
            'pricing_preset' => $preset,
            'is_default' => false,
        ]);

        $match = GameMatch::factory()->forGame($game)->forCompetition($league)->between($home, $away)->create([
            'round_name' => 'Matchday 3',
        ]);

        return $this->service->describeForMatch($match, $game)['attendance'];
    }

    private function seedReputation(Game $game, Team $team, string $level, int $loyalty): void
    {
        TeamReputation::create([
            'game_id' => $game->id,
            'team_id' => $team->id,
            'reputation_level' => $level,
            'base_reputation_level' => $level,
            'reputation_points' => TeamReputation::pointsForTier($level),
            'base_loyalty' => $loyalty,
            'loyalty_points' => $loyalty,
        ]);
    }
}
