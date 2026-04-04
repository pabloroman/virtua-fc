<?php

namespace Tests\Unit;

use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\Team;
use App\Models\User;
use App\Modules\Competition\Services\CupDrawService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CupDrawServiceTest extends TestCase
{
    use RefreshDatabase;

    private CupDrawService $service;
    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(CupDrawService::class);

        $user = User::factory()->create();

        Competition::factory()->league()->create([
            'id' => 'ESP1',
            'name' => 'LaLiga',
            'tier' => 1,
        ]);

        Competition::factory()->league()->create([
            'id' => 'ESP2',
            'name' => 'LaLiga 2',
            'tier' => 2,
        ]);

        Competition::factory()->knockoutCup()->create([
            'id' => 'ESPCUP',
            'name' => 'Copa del Rey',
            'season' => '2025',
        ]);

        $team = Team::factory()->create();

        $this->game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'competition_id' => 'ESP1',
            'season' => '2025',
        ]);
    }

    public function test_cross_category_draw_pairs_different_tiers(): void
    {
        // Create 4 tier-1 teams and 4 tier-99 teams (no league entry = tier 99)
        $tier1Teams = Team::factory()->count(4)->create();
        $tier99Teams = Team::factory()->count(4)->create();

        // Register tier-1 teams in ESP1 league
        foreach ($tier1Teams as $team) {
            CompetitionEntry::create([
                'game_id' => $this->game->id,
                'competition_id' => 'ESP1',
                'team_id' => $team->id,
                'entry_round' => 1,
            ]);
        }

        // Register all teams in the cup at round 1
        foreach ($tier1Teams->merge($tier99Teams) as $team) {
            CompetitionEntry::create([
                'game_id' => $this->game->id,
                'competition_id' => 'ESPCUP',
                'team_id' => $team->id,
                'entry_round' => 1,
            ]);
        }

        $ties = $this->service->conductDraw($this->game->id, 'ESPCUP', 1);

        $this->assertCount(4, $ties);

        // Every tie should pair a tier-1 team with a non-tier-1 team
        $tier1Ids = $tier1Teams->pluck('id')->all();
        $tier99Ids = $tier99Teams->pluck('id')->all();

        foreach ($ties as $tie) {
            $homeInTier1 = in_array($tie->home_team_id, $tier1Ids);
            $awayInTier1 = in_array($tie->away_team_id, $tier1Ids);

            $this->assertNotEquals(
                $homeInTier1,
                $awayInTier1,
                'Each tie should pair a tier-1 team against a non-tier-1 team'
            );
        }
    }

    public function test_home_advantage_for_lower_category_team(): void
    {
        $tier1Teams = Team::factory()->count(2)->create();
        $tier99Teams = Team::factory()->count(2)->create();

        foreach ($tier1Teams as $team) {
            CompetitionEntry::create([
                'game_id' => $this->game->id,
                'competition_id' => 'ESP1',
                'team_id' => $team->id,
                'entry_round' => 1,
            ]);
        }

        foreach ($tier1Teams->merge($tier99Teams) as $team) {
            CompetitionEntry::create([
                'game_id' => $this->game->id,
                'competition_id' => 'ESPCUP',
                'team_id' => $team->id,
                'entry_round' => 1,
            ]);
        }

        $ties = $this->service->conductDraw($this->game->id, 'ESPCUP', 1);

        $this->assertCount(2, $ties);

        $tier1Ids = $tier1Teams->pluck('id')->all();

        foreach ($ties as $tie) {
            // Lower-category team (tier 99, not in ESP1) should be home
            $this->assertFalse(
                in_array($tie->home_team_id, $tier1Ids),
                'Lower-category team should have home advantage'
            );
        }
    }

    public function test_draw_with_unequal_tier_groups(): void
    {
        // 2 tier-2 teams + 6 tier-99 teams
        $tier2Teams = Team::factory()->count(2)->create();
        $tier99Teams = Team::factory()->count(6)->create();

        foreach ($tier2Teams as $team) {
            CompetitionEntry::create([
                'game_id' => $this->game->id,
                'competition_id' => 'ESP2',
                'team_id' => $team->id,
                'entry_round' => 1,
            ]);
        }

        foreach ($tier2Teams->merge($tier99Teams) as $team) {
            CompetitionEntry::create([
                'game_id' => $this->game->id,
                'competition_id' => 'ESPCUP',
                'team_id' => $team->id,
                'entry_round' => 1,
            ]);
        }

        $ties = $this->service->conductDraw($this->game->id, 'ESPCUP', 1);

        $this->assertCount(4, $ties);

        // Count cross-category pairings
        $tier2Ids = $tier2Teams->pluck('id')->all();
        $crossCategoryCount = 0;

        foreach ($ties as $tie) {
            $homeInTier2 = in_array($tie->home_team_id, $tier2Ids);
            $awayInTier2 = in_array($tie->away_team_id, $tier2Ids);

            if ($homeInTier2 !== $awayInTier2) {
                $crossCategoryCount++;
            }
        }

        // Should have exactly 2 cross-category pairings (max possible)
        $this->assertEquals(2, $crossCategoryCount);
    }

    public function test_draw_without_pairing_config_uses_random(): void
    {
        // Create a cup without draw_pairing config
        Competition::factory()->knockoutCup()->create([
            'id' => 'ESPSUP',
            'name' => 'Supercopa',
            'season' => '2025',
        ]);

        $teams = Team::factory()->count(4)->create();

        foreach ($teams as $team) {
            CompetitionEntry::create([
                'game_id' => $this->game->id,
                'competition_id' => 'ESPSUP',
                'team_id' => $team->id,
                'entry_round' => 1,
            ]);
        }

        // ESPSUP has no draw_pairing config, should use RandomPairing
        // Just verify ties are created without errors
        $ties = $this->service->conductDraw($this->game->id, 'ESPSUP', 1);

        $this->assertCount(2, $ties);
    }
}
