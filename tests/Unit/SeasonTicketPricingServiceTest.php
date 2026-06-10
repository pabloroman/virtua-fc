<?php

namespace Tests\Unit;

use App\Models\Game;
use App\Models\Team;
use App\Models\TeamReputation;
use App\Modules\Stadium\Services\DemandCurveService;
use App\Modules\Stadium\Services\GameStadiumResolver;
use App\Modules\Stadium\Services\SeasonTicketPricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the preset-based season-ticket pricing: cheaper presets fill more
 * seats at a lower per-seat price, the default preset is seeded once, and an
 * explicit choice persists. The walk-up/finance coupling is gone, so these
 * tests touch the pricing row only.
 */
class SeasonTicketPricingServiceTest extends TestCase
{
    use RefreshDatabase;

    private SeasonTicketPricingService $service;
    private Game $game;
    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new SeasonTicketPricingService(new GameStadiumResolver());

        $this->team = Team::factory()->create(['stadium_seats' => 20_000]);
        $this->game = Game::factory()->forTeam($this->team)->create([
            'season' => 2026,
            'pre_season' => true,
        ]);

        TeamReputation::create([
            'game_id' => $this->game->id,
            'team_id' => $this->team->id,
            'reputation_level' => 'established',
            'base_reputation_level' => 'established',
            'reputation_points' => TeamReputation::pointsForTier('established'),
            'base_loyalty' => 60,
            'loyalty_points' => 60,
        ]);
    }

    public function test_cheaper_preset_fills_more_seats_at_a_lower_price(): void
    {
        $accessible = $this->service->predictForPreset($this->game, $this->team, 'accessible');
        $premium = $this->service->predictForPreset($this->game, $this->team, 'premium');

        // Cheaper preset → fuller ground.
        $this->assertGreaterThan($premium['total_sold'], $accessible['total_sold']);
        $this->assertGreaterThan($premium['overall_fill_rate'], $accessible['overall_fill_rate']);

        // ...but a lower per-seat price in every comparable area.
        $this->assertLessThan(
            $premium['areas'][0]['price_cents'],
            $accessible['areas'][0]['price_cents'],
        );
    }

    public function test_default_preset_is_seeded_once_and_marked_default(): void
    {
        $this->assertNull($this->service->getCurrent($this->game));

        $pricing = $this->service->applyDefaultIfMissing($this->game);

        $this->assertNotNull($pricing);
        $this->assertSame(SeasonTicketPricingService::DEFAULT_PRESET, $pricing->pricing_preset);
        $this->assertTrue($pricing->is_default);

        // Idempotent — a second call returns the same row, not a new one.
        $again = $this->service->applyDefaultIfMissing($this->game);
        $this->assertSame($pricing->id, $again->id);
    }

    public function test_apply_persists_the_chosen_preset(): void
    {
        $pricing = $this->service->apply($this->game, 'premium');

        $this->assertSame('premium', $pricing->pricing_preset);
        $this->assertFalse($pricing->is_default);
        $this->assertSame($pricing->total_sold, $this->service->soldSeasonTicketsForGame($this->game));
    }

    public function test_unknown_preset_falls_back_to_default(): void
    {
        $pricing = $this->service->apply($this->game, 'bogus');

        $this->assertSame(SeasonTicketPricingService::DEFAULT_PRESET, $pricing->pricing_preset);
    }

    public function test_penetration_leaves_a_walkup_gap_that_shrinks_with_loyalty(): void
    {
        $demand = new DemandCurveService();

        // Mid-loyalty club (established, loyalty 60 from setUp): abonos must sit
        // BELOW match demand, or there's no walk-up gate to project.
        $midRep = TeamReputation::where('game_id', $this->game->id)->first();
        $midDemand = $demand->projectBaseline($this->team, $midRep, 20_000);
        $midSold = $this->service->predictForPreset($this->game, $this->team, 'standard')['total_sold'];
        $this->assertLessThan($midDemand, $midSold);

        // High-loyalty club: abonos saturate the crowd, so the gap shrinks
        // toward zero (an elite ground sells out via season tickets).
        $hiTeam = Team::factory()->create(['stadium_seats' => 20_000]);
        $hiGame = Game::factory()->forTeam($hiTeam)->create(['season' => 2026, 'pre_season' => true]);
        $hiRep = TeamReputation::create([
            'game_id' => $hiGame->id,
            'team_id' => $hiTeam->id,
            'reputation_level' => 'established',
            'base_reputation_level' => 'established',
            'reputation_points' => TeamReputation::pointsForTier('established'),
            'base_loyalty' => 99,
            'loyalty_points' => 99,
        ]);
        $hiDemand = $demand->projectBaseline($hiTeam, $hiRep, 20_000);
        $hiSold = $this->service->predictForPreset($hiGame, $hiTeam, 'standard')['total_sold'];

        $midGapFraction = ($midDemand - $midSold) / $midDemand;
        $hiGapFraction = ($hiDemand - $hiSold) / $hiDemand;
        $this->assertLessThan($midGapFraction, $hiGapFraction);
    }
}
