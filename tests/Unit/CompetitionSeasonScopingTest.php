<?php

namespace Tests\Unit;

use App\Models\Competition;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Eager-load and whereHas build the relation off a blank model instance, so a
 * `$this->season` filter would compare against null and quietly return nothing.
 *
 * @see CompetitionTeam::SEASON_MATCHES_COMPETITION
 */
class CompetitionSeasonScopingTest extends TestCase
{
    use RefreshDatabase;

    private Competition $competition;

    private Team $current;

    private Team $stale;

    protected function setUp(): void
    {
        parent::setUp();

        $this->competition = Competition::factory()->create(['season' => '2026']);
        $this->current = Team::factory()->create(['name' => 'Current FC']);
        $this->stale = Team::factory()->create(['name' => 'Relegated FC']);

        $this->competition->teams()->attach($this->current->id, ['season' => '2026']);
        $this->competition->teams()->attach($this->stale->id, ['season' => '2025']);
    }

    public function test_lazy_access_returns_only_the_competitions_own_season(): void
    {
        $teams = Competition::find($this->competition->id)->teams;

        $this->assertCount(1, $teams);
        $this->assertSame($this->current->id, $teams->first()->id);
    }

    public function test_eager_loading_returns_only_the_competitions_own_season(): void
    {
        $teams = Competition::with('teams')->find($this->competition->id)->teams;

        $this->assertCount(1, $teams);
        $this->assertSame($this->current->id, $teams->first()->id);
    }

    public function test_where_has_still_matches_the_competition(): void
    {
        $ids = Competition::whereHas('teams', fn ($q) => $q->where('teams.id', $this->current->id))
            ->pluck('id');

        $this->assertContains($this->competition->id, $ids->all());
    }

    public function test_where_has_does_not_match_on_a_previous_seasons_row(): void
    {
        $ids = Competition::whereHas('teams', fn ($q) => $q->where('teams.id', $this->stale->id))
            ->pluck('id');

        $this->assertNotContains($this->competition->id, $ids->all());
    }
}
