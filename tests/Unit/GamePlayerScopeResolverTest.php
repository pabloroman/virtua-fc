<?php

namespace Tests\Unit;

use App\Models\Competition;
use App\Models\Game;
use App\Models\Team;
use App\Modules\Player\Support\GamePlayerScopeResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GamePlayerScopeResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_team_ids_includes_country_competition_teams(): void
    {
        $espCompetition = Competition::factory()->create([
            'country' => 'ES',
            'role' => Competition::ROLE_LEAGUE,
        ]);
        $espTeam = Team::factory()->create();
        $espCompetition->teams()->attach($espTeam->id, ['season' => '2025']);

        $game = Game::factory()->create(['country' => 'ES']);

        $resolver = new GamePlayerScopeResolver();

        $this->assertContains($espTeam->id, $resolver->activeTeamIdsForGame($game));
    }

    public function test_active_team_ids_excludes_foreign_country_teams(): void
    {
        $engCompetition = Competition::factory()->create([
            'country' => 'EN',
            'role' => Competition::ROLE_LEAGUE,
        ]);
        $engTeam = Team::factory()->create();
        $engCompetition->teams()->attach($engTeam->id, ['season' => '2025']);

        $game = Game::factory()->create(['country' => 'ES']);

        $resolver = new GamePlayerScopeResolver();

        $this->assertNotContains($engTeam->id, $resolver->activeTeamIdsForGame($game));
    }

    public function test_results_are_cached_per_game(): void
    {
        $game = Game::factory()->create(['country' => 'ES']);
        $resolver = new GamePlayerScopeResolver();

        $first = $resolver->activeTeamIdsForGame($game);
        $second = $resolver->activeTeamIdsForGame($game);

        $this->assertSame($first, $second);
    }

    public function test_forget_clears_cache(): void
    {
        $game = Game::factory()->create(['country' => 'ES']);
        $resolver = new GamePlayerScopeResolver();

        $resolver->activeTeamIdsForGame($game);
        $resolver->forget($game->id);

        // Add a new active team after caching has been cleared.
        $newCompetition = Competition::factory()->create([
            'country' => 'ES',
            'role' => Competition::ROLE_LEAGUE,
        ]);
        $newTeam = Team::factory()->create();
        $newCompetition->teams()->attach($newTeam->id, ['season' => '2025']);

        $this->assertContains($newTeam->id, $resolver->activeTeamIdsForGame($game));
    }
}
