<?php

namespace Tests\Feature\Console;

use App\Models\ManagerStats;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MergeOrphanManagerStatsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_sums_cumulative_stats_takes_max_of_streaks_and_recomputes_win_percentage(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();

        $orphan = ManagerStats::create([
            'user_id' => $user->id,
            'game_id' => null,
            'team_id' => $team->id,
            'matches_played' => 21,
            'matches_won' => 17,
            'matches_drawn' => 0,
            'matches_lost' => 4,
            'win_percentage' => 80.95,
            'current_unbeaten_streak' => 2,
            'longest_unbeaten_streak' => 6,
            'seasons_completed' => 1,
        ]);

        $path = $this->writeOldExport([[
            'user_id' => $user->id,
            'team_id' => $team->id,
            'matches_played' => 1029,
            'matches_won' => 781,
            'matches_drawn' => 164,
            'matches_lost' => 84,
            'win_percentage' => 75.90,
            'current_unbeaten_streak' => 0,
            'longest_unbeaten_streak' => 87,
            'seasons_completed' => 18,
        ]]);

        $this->artisan('app:merge-orphan-manager-stats', ['--from' => $path])
            ->assertSuccessful();

        $orphan->refresh();

        $this->assertSame(1050, $orphan->matches_played);
        $this->assertSame(798, $orphan->matches_won);
        $this->assertSame(164, $orphan->matches_drawn);
        $this->assertSame(88, $orphan->matches_lost);
        $this->assertSame(19, $orphan->seasons_completed);
        $this->assertSame(87, $orphan->longest_unbeaten_streak);
        $this->assertSame(2, $orphan->current_unbeaten_streak);
        $this->assertSame('76.00', (string) $orphan->win_percentage);
    }

    public function test_dry_run_does_not_persist(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();

        $orphan = ManagerStats::create([
            'user_id' => $user->id,
            'game_id' => null,
            'team_id' => $team->id,
            'matches_played' => 10,
            'matches_won' => 5,
            'matches_drawn' => 3,
            'matches_lost' => 2,
            'win_percentage' => 50.00,
            'current_unbeaten_streak' => 0,
            'longest_unbeaten_streak' => 4,
            'seasons_completed' => 0,
        ]);

        $path = $this->writeOldExport([[
            'user_id' => $user->id,
            'team_id' => $team->id,
            'matches_played' => 100,
            'matches_won' => 60,
            'matches_drawn' => 20,
            'matches_lost' => 20,
            'win_percentage' => 60.00,
            'current_unbeaten_streak' => 3,
            'longest_unbeaten_streak' => 12,
            'seasons_completed' => 2,
        ]]);

        $this->artisan('app:merge-orphan-manager-stats', [
            '--from' => $path,
            '--dry-run' => true,
        ])->assertSuccessful();

        $orphan->refresh();
        $this->assertSame(10, $orphan->matches_played);
        $this->assertSame(0, $orphan->seasons_completed);
    }

    public function test_reports_orphan_with_no_old_match_and_leaves_row_unchanged(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();

        $orphan = ManagerStats::create([
            'user_id' => $user->id,
            'game_id' => null,
            'team_id' => $team->id,
            'matches_played' => 7,
            'matches_won' => 3,
            'matches_drawn' => 2,
            'matches_lost' => 2,
            'win_percentage' => 42.86,
            'current_unbeaten_streak' => 0,
            'longest_unbeaten_streak' => 2,
            'seasons_completed' => 0,
        ]);

        $path = $this->writeOldExport([]);

        $this->artisan('app:merge-orphan-manager-stats', ['--from' => $path])
            ->expectsOutputToContain('1 orphan(s) had no OLD-server match')
            ->assertSuccessful();

        $orphan->refresh();
        $this->assertSame(7, $orphan->matches_played);
    }

    public function test_skips_when_multiple_old_rows_match_same_user_team(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();

        $orphan = ManagerStats::create([
            'user_id' => $user->id,
            'game_id' => null,
            'team_id' => $team->id,
            'matches_played' => 5,
            'matches_won' => 2,
            'matches_drawn' => 1,
            'matches_lost' => 2,
            'win_percentage' => 40.00,
            'current_unbeaten_streak' => 0,
            'longest_unbeaten_streak' => 1,
            'seasons_completed' => 0,
        ]);

        $path = $this->writeOldExport([
            [
                'user_id' => $user->id,
                'team_id' => $team->id,
                'matches_played' => 50,
                'matches_won' => 25,
                'matches_drawn' => 15,
                'matches_lost' => 10,
                'win_percentage' => 50.00,
                'current_unbeaten_streak' => 1,
                'longest_unbeaten_streak' => 8,
                'seasons_completed' => 1,
            ],
            [
                'user_id' => $user->id,
                'team_id' => $team->id,
                'matches_played' => 200,
                'matches_won' => 130,
                'matches_drawn' => 40,
                'matches_lost' => 30,
                'win_percentage' => 65.00,
                'current_unbeaten_streak' => 0,
                'longest_unbeaten_streak' => 15,
                'seasons_completed' => 4,
            ],
        ]);

        $this->artisan('app:merge-orphan-manager-stats', ['--from' => $path])
            ->expectsOutputToContain('matched multiple OLD rows')
            ->assertSuccessful();

        $orphan->refresh();
        $this->assertSame(5, $orphan->matches_played);
    }

    public function test_skips_when_multiple_orphans_share_user_and_team(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();

        ManagerStats::create([
            'user_id' => $user->id,
            'game_id' => null,
            'team_id' => $team->id,
            'matches_played' => 5,
            'matches_won' => 2,
            'matches_drawn' => 1,
            'matches_lost' => 2,
            'win_percentage' => 40.00,
            'current_unbeaten_streak' => 0,
            'longest_unbeaten_streak' => 1,
            'seasons_completed' => 0,
        ]);
        ManagerStats::create([
            'user_id' => $user->id,
            'game_id' => null,
            'team_id' => $team->id,
            'matches_played' => 9,
            'matches_won' => 4,
            'matches_drawn' => 2,
            'matches_lost' => 3,
            'win_percentage' => 44.44,
            'current_unbeaten_streak' => 0,
            'longest_unbeaten_streak' => 2,
            'seasons_completed' => 0,
        ]);

        $path = $this->writeOldExport([[
            'user_id' => $user->id,
            'team_id' => $team->id,
            'matches_played' => 500,
            'matches_won' => 300,
            'matches_drawn' => 100,
            'matches_lost' => 100,
            'win_percentage' => 60.00,
            'current_unbeaten_streak' => 1,
            'longest_unbeaten_streak' => 20,
            'seasons_completed' => 8,
        ]]);

        $this->artisan('app:merge-orphan-manager-stats', ['--from' => $path])
            ->expectsOutputToContain('multiple NEW orphans')
            ->assertSuccessful();

        $this->assertSame(
            [5, 9],
            ManagerStats::where('user_id', $user->id)
                ->orderBy('matches_played')
                ->pluck('matches_played')
                ->all(),
        );
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private function writeOldExport(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'old_stats_') . '.json';
        file_put_contents($path, json_encode($rows));

        return $path;
    }

    protected function tearDown(): void
    {
        foreach (glob(sys_get_temp_dir() . '/old_stats_*.json') ?: [] as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }
}
