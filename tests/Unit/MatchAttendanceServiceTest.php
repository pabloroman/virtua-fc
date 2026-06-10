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
 * attendance floor.
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

    public function test_season_ticket_holders_floor_the_gate(): void
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

        // Holders always show up: the gate sits near 18k (± walk-up jitter),
        // far above the ~10k the demand curve alone would produce.
        $this->assertGreaterThan(15_000, $result['attendance']);
        $this->assertLessThanOrEqual(20_000, $result['attendance']);
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
