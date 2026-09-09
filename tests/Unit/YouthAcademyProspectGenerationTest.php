<?php

namespace Tests\Unit;

use App\Models\AcademyPlayer;
use App\Models\Game;
use App\Models\GameInvestment;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Modules\Academy\Services\YouthAcademyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class YouthAcademyProspectGenerationTest extends TestCase
{
    use RefreshDatabase;

    private YouthAcademyService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(YouthAcademyService::class);
    }

    public function test_per_tick_probability_matches_tier_average_over_season(): void
    {
        // Expected season arrivals = mean(min, max); spread over 38 ticks.
        $this->assertEqualsWithDelta(1.0 / 38, YouthAcademyService::perTickProspectProbability(0), 1e-9);
        $this->assertEqualsWithDelta(2.5 / 38, YouthAcademyService::perTickProspectProbability(1), 1e-9);
        $this->assertEqualsWithDelta(4.0 / 38, YouthAcademyService::perTickProspectProbability(2), 1e-9);
        $this->assertEqualsWithDelta(5.0 / 38, YouthAcademyService::perTickProspectProbability(3), 1e-9);
        $this->assertEqualsWithDelta(5.0 / 38, YouthAcademyService::perTickProspectProbability(4), 1e-9);

        // Unknown tier falls back to tier 0's config rather than erroring.
        $this->assertEqualsWithDelta(1.0 / 38, YouthAcademyService::perTickProspectProbability(99), 1e-9);
    }

    public function test_generates_academy_player_for_non_filial_game(): void
    {
        $game = $this->makeGame(tier: 2);

        $prospect = $this->firstProspect($game);

        $this->assertInstanceOf(AcademyPlayer::class, $prospect);
        $this->assertSame($game->id, $prospect->game_id);
        $this->assertSame($game->team_id, $prospect->team_id);
        $this->assertFalse($prospect->is_on_loan);
        $this->assertSame($game->current_date->toDateString(), $prospect->appeared_at->toDateString());
        $this->assertGreaterThan(0, $prospect->overall_score);
        $this->assertGreaterThanOrEqual($prospect->overall_score, $prospect->potential);
    }

    public function test_generates_reserve_game_player_for_filial_game(): void
    {
        $reserveTeam = Team::factory()->create();
        $game = $this->makeGame(tier: 3, reserveTeamId: $reserveTeam->id);

        $prospect = $this->firstProspect($game);

        $this->assertInstanceOf(GamePlayer::class, $prospect);
        $this->assertSame($reserveTeam->id, $prospect->team_id);
        // Filial prospects skip the AcademyPlayer pool entirely.
        $this->assertSame(0, AcademyPlayer::where('game_id', $game->id)->count());
    }

    public function test_returns_null_when_no_prospect_arrives_this_tick(): void
    {
        // Tier 0 has the lowest probability (~2.6%/tick); across many ticks at
        // least one roll must miss and return null.
        $game = $this->makeGame(tier: 0);

        $sawNull = false;
        for ($i = 0; $i < 200; $i++) {
            if ($this->service->maybeGenerateProspect($game) === null) {
                $sawNull = true;
                break;
            }
        }

        $this->assertTrue($sawNull, 'Expected at least one tick with no arrival.');
    }

    /**
     * Roll until the first prospect arrives. The per-tick probability is high
     * enough that a miss streak of this length is astronomically unlikely, so
     * the loop is deterministic in practice without seeding the RNG.
     */
    private function firstProspect(Game $game): AcademyPlayer|GamePlayer
    {
        for ($i = 0; $i < 500; $i++) {
            $prospect = $this->service->maybeGenerateProspect($game);
            if ($prospect !== null) {
                return $prospect;
            }
        }

        $this->fail('No prospect generated in 500 ticks — probability calibration is off.');
    }

    private function makeGame(int $tier, ?string $reserveTeamId = null): Game
    {
        $team = Team::factory()->create();

        $game = Game::factory()->forTeam($team)->create([
            'season' => '2025',
            'current_date' => '2024-09-15',
            'reserve_team_id' => $reserveTeamId,
        ]);

        GameInvestment::create([
            'game_id' => $game->id,
            'season' => 2025,
            'available_surplus' => 0,
            'transfer_budget' => 0,
            'youth_academy_amount' => 0,
            'youth_academy_tier' => $tier,
            'medical_amount' => 0,
            'medical_tier' => 0,
            'scouting_amount' => 0,
            'scouting_tier' => 0,
        ]);

        return $game;
    }
}
