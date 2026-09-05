<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Saves cannot be migrated to a newer reference-data season, so the dashboard
 * has to make the distinction visible: each career card carries the season it
 * was created from, and users who still hold one from a past season are told
 * that starting a new career is the only way onto the new data.
 */
class DashboardSaveVintageTest extends TestCase
{
    use RefreshDatabase;

    private const NOTICE = 'keep the squads they started with';

    /** @param array<int, array{0: string, 1: string}> $saves [base_season, game_mode] */
    private function dashboardFor(array $saves): string
    {
        $user = User::factory()->create();

        foreach ($saves as [$baseSeason, $mode]) {
            Game::factory()->create([
                'user_id' => $user->id,
                'base_season' => $baseSeason,
                'season' => $baseSeason,
                'game_mode' => $mode,
            ]);
        }

        return $this->actingAs($user)->get('/dashboard')->getContent();
    }

    public function test_each_career_card_shows_the_season_it_was_created_from(): void
    {
        config(['season.current' => '2026']);

        $html = $this->dashboardFor([
            ['2025', Game::MODE_CAREER],
            ['2026', Game::MODE_CAREER],
        ]);

        $this->assertStringContainsString('Club Manager - 25/26', $html);
        $this->assertStringContainsString('Club Manager - 26/27', $html);
    }

    public function test_a_save_from_a_past_season_earns_the_notice(): void
    {
        config(['season.current' => '2026']);

        $html = $this->dashboardFor([['2025', Game::MODE_CAREER]]);

        $this->assertStringContainsString(self::NOTICE, $html);
        // Names the season currently seeded, not a hardcoded year.
        $this->assertStringContainsString('2026/27', $html);
    }

    public function test_no_notice_when_every_save_is_on_the_current_season(): void
    {
        config(['season.current' => '2026']);

        $html = $this->dashboardFor([['2026', Game::MODE_CAREER]]);

        $this->assertStringNotContainsString(self::NOTICE, $html);
    }

    public function test_world_cup_saves_carry_no_season_and_raise_no_notice(): void
    {
        config(['season.current' => '2026']);

        $html = $this->dashboardFor([['2025', Game::MODE_TOURNAMENT]]);

        $this->assertStringContainsString('World Cup', $html);
        $this->assertStringNotContainsString('World Cup - ', $html);
        $this->assertStringNotContainsString(self::NOTICE, $html);
    }
}
