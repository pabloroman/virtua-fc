<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class FixSeasonClashesCommandTest extends TestCase
{
    // A throwaway season disjoint from the years ValidateSeasonCommandTest
    // (2096) and ScaffoldSeasonCommandTest (2098/2099) use: all three write to
    // the shared base_path('data') tree, so under `test --parallel` they would
    // otherwise race, one tearDown deleting another's freshly written files.
    private string $season = '2097';

    protected function tearDown(): void
    {
        File::deleteDirectory(base_path("data/{$this->season}"));
        parent::tearDown();
    }

    public function test_it_moves_a_cup_round_off_a_league_date(): void
    {
        $this->writeSeason(cupRound1: $this->weekOfSeason(3));

        $this->artisan('app:fix-season-clashes', ['season' => $this->season, '--apply' => true])
            ->assertSuccessful();

        // The league keeps its matchweek; the cup is the one that gives way.
        $this->assertSame($this->weekOfSeason(3), $this->leagueDate(3));
        $this->assertNotSame($this->weekOfSeason(3), $this->cupDate(1));
    }

    public function test_the_moved_round_does_not_land_on_another_booked_date(): void
    {
        $this->writeSeason(cupRound1: $this->weekOfSeason(3));

        $this->artisan('app:fix-season-clashes', ['season' => $this->season, '--apply' => true]);

        // Every league date is taken by the same clubs, so the only free days
        // are the ones between matchweeks.
        $leagueDates = array_map(fn (int $w): string => $this->weekOfSeason($w), range(1, 6));
        $this->assertNotContains($this->cupDate(1), $leagueDates);
    }

    public function test_it_leaves_the_season_validating(): void
    {
        $this->writeSeason(cupRound1: $this->weekOfSeason(3));

        $this->artisan('app:fix-season-clashes', ['season' => $this->season, '--apply' => true]);

        $this->artisan('app:validate-season', ['season' => $this->season])
            ->doesntExpectOutputToContain('are booked twice');
    }

    public function test_a_dry_run_changes_nothing_on_disk(): void
    {
        $this->writeSeason(cupRound1: $this->weekOfSeason(3));

        $this->artisan('app:fix-season-clashes', ['season' => $this->season])
            ->expectsOutputToContain('Re-run with --apply');

        $this->assertSame($this->weekOfSeason(3), $this->cupDate(1));
    }

    public function test_a_clean_season_reports_nothing_to_do(): void
    {
        $this->writeSeason(cupRound1: $this->dayOfSeason(3));

        $this->artisan('app:fix-season-clashes', ['season' => $this->season])
            ->expectsOutputToContain('No club is booked twice')
            ->assertSuccessful();
    }

    public function test_it_writes_the_canonical_encoding(): void
    {
        $this->writeSeason(cupRound1: $this->weekOfSeason(3));

        $this->artisan('app:fix-season-clashes', ['season' => $this->season, '--apply' => true]);

        // app:normalize-season must be a no-op afterwards, or every fix would
        // land as a whole-file reformat in the diff.
        $path = base_path("data/{$this->season}/ESPCUP/schedule.json");
        $written = File::get($path);
        $canonical = json_encode(
            json_decode($written, true),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ) . "\n";

        $this->assertSame($canonical, $written);
    }

    /**
     * A four-club league running weekly, plus a one-round cup whose field is
     * the same four clubs.
     */
    private function writeSeason(string $cupRound1): void
    {
        $clubs = [];
        for ($i = 0; $i < 4; $i++) {
            $clubs[] = ['id' => (string) (200 + $i), 'name' => "Club {$i}"];
        }

        $leagueDir = base_path("data/{$this->season}/ESP1");
        File::ensureDirectoryExists($leagueDir);
        File::put("{$leagueDir}/teams.json", json_encode(['seasonID' => $this->season, 'clubs' => $clubs]));
        $league = [];
        for ($round = 1; $round <= 6; $round++) {
            $league[] = ['round' => $round, 'date' => $this->weekOfSeason($round)];
        }
        File::put("{$leagueDir}/schedule.json", json_encode(['league' => $league]));

        $cupDir = base_path("data/{$this->season}/ESPCUP");
        File::ensureDirectoryExists($cupDir);
        File::put("{$cupDir}/teams.json", json_encode(['seasonID' => $this->season, 'clubs' => $clubs]));
        File::put("{$cupDir}/schedule.json", json_encode([
            'knockout' => [['round' => 1, 'name' => 'cup.first_round', 'date' => $cupRound1]],
        ]));
    }

    private function leagueDate(int $round): string
    {
        $schedule = json_decode(File::get(base_path("data/{$this->season}/ESP1/schedule.json")), true);

        return $schedule['league'][$round - 1]['date'];
    }

    private function cupDate(int $round): string
    {
        $schedule = json_decode(File::get(base_path("data/{$this->season}/ESPCUP/schedule.json")), true);

        return $schedule['knockout'][$round - 1]['date'];
    }

    private function weekOfSeason(int $week): string
    {
        return date('Y-m-d', strtotime("{$this->season}-08-01 +" . ($week - 1) . ' weeks'));
    }

    private function dayOfSeason(int $days): string
    {
        return date('Y-m-d', strtotime("{$this->season}-08-01 +{$days} days"));
    }
}
