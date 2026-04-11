<?php

namespace Tests\Unit;

use App\Models\Team;
use App\Modules\Transfer\Services\AIExclusionList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AIExclusionListTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_config_excludes_nothing(): void
    {
        config(['finances.ai_excluded_from_signing' => []]);
        $team = Team::factory()->create(['slug' => 'any-team']);

        $list = new AIExclusionList();

        $this->assertFalse($list->contains($team->id));
    }

    public function test_configured_slug_resolves_to_team_id(): void
    {
        $excluded = Team::factory()->create(['slug' => 'youth-only-fc']);
        $other = Team::factory()->create(['slug' => 'normal-fc']);

        config(['finances.ai_excluded_from_signing' => ['youth-only-fc']]);

        $list = new AIExclusionList();

        $this->assertTrue($list->contains($excluded->id));
        $this->assertFalse($list->contains($other->id));
    }

    public function test_unknown_slugs_are_ignored(): void
    {
        $team = Team::factory()->create(['slug' => 'real-team']);

        config(['finances.ai_excluded_from_signing' => ['does-not-exist', 'real-team']]);

        $list = new AIExclusionList();

        $this->assertTrue($list->contains($team->id));
    }

    public function test_multiple_excluded_slugs_resolve_together(): void
    {
        $a = Team::factory()->create(['slug' => 'club-a']);
        $b = Team::factory()->create(['slug' => 'club-b']);
        $c = Team::factory()->create(['slug' => 'club-c']);

        config(['finances.ai_excluded_from_signing' => ['club-a', 'club-b']]);

        $list = new AIExclusionList();

        $this->assertTrue($list->contains($a->id));
        $this->assertTrue($list->contains($b->id));
        $this->assertFalse($list->contains($c->id));
    }

    public function test_resolution_is_memoized(): void
    {
        Team::factory()->create(['slug' => 'memo-fc']);
        config(['finances.ai_excluded_from_signing' => ['memo-fc']]);

        $list = new AIExclusionList();

        // First call triggers the query
        $list->contains('irrelevant-id');

        // Second call should not re-query — we can't directly assert query count
        // without listening to the query log, so enable the log and verify.
        \DB::enableQueryLog();
        \DB::flushQueryLog();
        $list->contains('another-irrelevant-id');

        $this->assertCount(0, \DB::getQueryLog());
    }
}
