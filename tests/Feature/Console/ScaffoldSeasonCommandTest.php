<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ScaffoldSeasonCommandTest extends TestCase
{
    // Throwaway season years kept well clear of any real data/{season} folder.
    private string $from = '2098';

    private string $to = '2099';

    protected function tearDown(): void
    {
        File::deleteDirectory(base_path("data/{$this->from}"));
        File::deleteDirectory(base_path("data/{$this->to}"));
        parent::tearDown();
    }

    public function test_shifts_schedule_dates_forward_one_year(): void
    {
        $srcDir = base_path("data/{$this->from}/ESP1");
        File::ensureDirectoryExists($srcDir);
        File::put("{$srcDir}/schedule.json", json_encode([
            'league' => [
                ['round' => 1, 'date' => '2098-08-17'],
                ['round' => 38, 'date' => '2099-05-24'],
            ],
        ]));

        // A knockout schedule with the two-legged date variant.
        $cupDir = base_path("data/{$this->from}/ESPCUP");
        File::ensureDirectoryExists($cupDir);
        File::put("{$cupDir}/schedule.json", json_encode([
            'knockout' => [
                ['round' => 1, 'name' => 'cup.first_round', 'date' => '2098-10-29'],
                ['round' => 6, 'name' => 'cup.semi_final', 'first_leg_date' => '2099-02-04', 'second_leg_date' => '2099-02-25'],
            ],
        ]));

        $this->artisan('app:scaffold-season', ['season' => $this->to, '--from' => $this->from])
            ->assertSuccessful();

        $league = json_decode(File::get(base_path("data/{$this->to}/ESP1/schedule.json")), true);
        $this->assertSame('2099-08-17', $league['league'][0]['date']);
        $this->assertSame('2100-05-24', $league['league'][1]['date']);

        $knockout = json_decode(File::get(base_path("data/{$this->to}/ESPCUP/schedule.json")), true);
        $this->assertSame('2099-10-29', $knockout['knockout'][0]['date']);
        // Two-legged dates and sibling fields (name) are preserved + shifted.
        $this->assertSame('cup.semi_final', $knockout['knockout'][1]['name']);
        $this->assertSame('2100-02-04', $knockout['knockout'][1]['first_leg_date']);
        $this->assertSame('2100-02-25', $knockout['knockout'][1]['second_leg_date']);
    }

    public function test_reports_missing_squad_data_and_does_not_invent_teams_json(): void
    {
        File::ensureDirectoryExists(base_path("data/{$this->from}/ESP1"));
        File::put(base_path("data/{$this->from}/ESP1/schedule.json"), json_encode([
            'league' => [['round' => 1, 'date' => '2098-08-17']],
        ]));

        $this->artisan('app:scaffold-season', ['season' => $this->to, '--from' => $this->from])
            ->expectsOutputToContain("data/{$this->to}/ESP1/teams.json")
            ->assertSuccessful();

        // The scaffolder bootstraps schedules only — squads come from the scraper.
        $this->assertFalse(File::exists(base_path("data/{$this->to}/ESP1/teams.json")));
    }

    public function test_does_not_overwrite_existing_schedule_without_force(): void
    {
        File::ensureDirectoryExists(base_path("data/{$this->from}/ESP1"));
        File::put(base_path("data/{$this->from}/ESP1/schedule.json"), json_encode([
            'league' => [['round' => 1, 'date' => '2098-08-17']],
        ]));

        $existing = base_path("data/{$this->to}/ESP1");
        File::ensureDirectoryExists($existing);
        File::put("{$existing}/schedule.json", '{"league":[{"round":1,"date":"KEEP"}]}');

        $this->artisan('app:scaffold-season', ['season' => $this->to, '--from' => $this->from])
            ->assertSuccessful();

        $this->assertStringContainsString('KEEP', File::get("{$existing}/schedule.json"));
    }
}
