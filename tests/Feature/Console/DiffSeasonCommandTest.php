<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DiffSeasonCommandTest extends TestCase
{
    private string $from = '2098';
    private string $season = '2099';

    protected function tearDown(): void
    {
        File::deleteDirectory(base_path("data/{$this->from}"));
        File::deleteDirectory(base_path("data/{$this->season}"));
        parent::tearDown();
    }

    private function writeEsp1(string $season, array $clubs): void
    {
        $dir = base_path("data/{$season}/ESP1");
        File::ensureDirectoryExists($dir);
        File::put("{$dir}/teams.json", json_encode(['seasonID' => $season, 'clubs' => $clubs]));
    }

    public function test_reports_signings_departures_and_club_movements(): void
    {
        $this->writeEsp1($this->from, [
            ['id' => '10', 'name' => 'Club A', 'players' => [
                ['id' => '100', 'name' => 'Stays Here'],
                ['id' => '200', 'name' => 'Departed Player'],
            ]],
            ['id' => '20', 'name' => 'Relegated Club', 'players' => []],
        ]);

        $this->writeEsp1($this->season, [
            ['id' => '10', 'name' => 'Club A', 'players' => [
                ['id' => '100', 'name' => 'Stays Here'],
                ['id' => '300', 'name' => 'New Signing'],
            ]],
            ['id' => '30', 'name' => 'Promoted Club', 'players' => []],
        ]);

        $this->artisan('app:diff-season', [
            'season' => $this->season,
            '--from' => $this->from,
        ])
            ->expectsOutputToContain('New Signing')
            ->expectsOutputToContain('Departed Player')
            ->expectsOutputToContain('Promoted Club')
            ->expectsOutputToContain('Relegated Club')
            ->assertSuccessful();
    }

    public function test_reports_no_changes_when_squads_match(): void
    {
        $clubs = [['id' => '10', 'name' => 'Club A', 'players' => [['id' => '100', 'name' => 'Same']]]];
        $this->writeEsp1($this->from, $clubs);
        $this->writeEsp1($this->season, $clubs);

        $this->artisan('app:diff-season', [
            'season' => $this->season,
            '--from' => $this->from,
        ])
            ->expectsOutputToContain('No squad changes')
            ->assertSuccessful();
    }
}
