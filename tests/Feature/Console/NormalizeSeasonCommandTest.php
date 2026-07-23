<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class NormalizeSeasonCommandTest extends TestCase
{
    // Throwaway year, kept disjoint from the other console-command tests that
    // write to the shared base_path('data') tree (Scaffold 2098/2099, Diff
    // 2094/2095, Validate 2096): under `test --parallel` they run in separate
    // processes but share the same data/ folder, so overlapping years race.
    private string $season = '2097';

    protected function tearDown(): void
    {
        File::deleteDirectory(base_path("data/{$this->season}"));
        parent::tearDown();
    }

    private function writeEsp1(array $payload): string
    {
        $dir = base_path("data/{$this->season}/ESP1");
        File::ensureDirectoryExists($dir);
        $path = "{$dir}/teams.json";
        File::put($path, json_encode($payload));

        return $path;
    }

    public function test_forces_season_id_and_sorts_clubs_and_players(): void
    {
        $path = $this->writeEsp1([
            'id' => 'ES1',
            'name' => 'LaLiga',
            // Out of id order, with out-of-order players and no seasonID.
            'clubs' => [
                ['id' => '20', 'name' => 'Club B', 'players' => [
                    ['id' => '500', 'name' => 'Zico'],
                    ['id' => '100', 'name' => 'Ardiles'],
                ]],
                ['id' => '10', 'name' => 'Club A', 'players' => []],
            ],
        ]);

        $this->artisan('app:normalize-season', ['season' => $this->season])->assertSuccessful();

        $data = json_decode(File::get($path), true);
        $this->assertSame($this->season, $data['seasonID']);
        $this->assertSame(['10', '20'], array_column($data['clubs'], 'id'));
        $this->assertSame(['100', '500'], array_column($data['clubs'][1]['players'], 'id'));
    }

    public function test_is_idempotent_and_check_mode_passes_on_canonical_data(): void
    {
        $path = $this->writeEsp1([
            'id' => 'ES1',
            'name' => 'LaLiga',
            'clubs' => [['id' => '10', 'name' => 'Club A', 'players' => []]],
        ]);

        $this->artisan('app:normalize-season', ['season' => $this->season])->assertSuccessful();
        $afterFirst = File::get($path);

        // A second pass changes nothing, and --check confirms canonical (exit 0).
        $this->artisan('app:normalize-season', ['season' => $this->season])
            ->expectsOutputToContain('already canonical')
            ->assertSuccessful();
        $this->artisan('app:normalize-season', ['season' => $this->season, '--check' => true])
            ->assertSuccessful();

        $this->assertSame($afterFirst, File::get($path));
    }

    public function test_check_mode_fails_on_non_canonical_data(): void
    {
        $this->writeEsp1([
            'name' => 'LaLiga',
            'clubs' => [['id' => '10', 'name' => 'Club A', 'players' => []]],
        ]);

        // Missing seasonID makes the file non-canonical; --check must not write.
        $this->artisan('app:normalize-season', ['season' => $this->season, '--check' => true])
            ->expectsOutputToContain('not canonical')
            ->assertFailed();
    }
}
