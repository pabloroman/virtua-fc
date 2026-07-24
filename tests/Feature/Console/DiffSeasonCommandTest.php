<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DiffSeasonCommandTest extends TestCase
{
    // Throwaway years, kept disjoint from the other console-command tests that
    // write to the shared base_path('data') tree (Scaffold 2098/2099, Normalize
    // 2097, Validate 2096): under `test --parallel` they run in separate
    // processes but share the same data/ folder, so overlapping years race.
    private string $from = '2094';
    private string $season = '2095';

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

        // The report is emitted in a single write, but expectsOutputToContain
        // matches per write — so it can only consume one substring per call.
        // Capture the whole buffer with Artisan::output() and assert on it.
        $exitCode = Artisan::call('app:diff-season', [
            'season' => $this->season,
            '--from' => $this->from,
        ]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('New Signing', $output);
        $this->assertStringContainsString('Departed Player', $output);
        $this->assertStringContainsString('Promoted Club', $output);
        $this->assertStringContainsString('Relegated Club', $output);
    }

    public function test_reports_no_changes_when_squads_match(): void
    {
        $clubs = [['id' => '10', 'name' => 'Club A', 'players' => [['id' => '100', 'name' => 'Same']]]];
        $this->writeEsp1($this->from, $clubs);
        $this->writeEsp1($this->season, $clubs);

        $exitCode = Artisan::call('app:diff-season', [
            'season' => $this->season,
            '--from' => $this->from,
        ]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('No squad changes', $output);
    }
}
