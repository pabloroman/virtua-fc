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

    /**
     * @param  array<int, array<string, mixed>>  $clubs
     */
    private function writeContinental(array $clubs, string $code = 'UCL'): string
    {
        $dir = base_path("data/{$this->season}/{$code}");
        File::ensureDirectoryExists($dir);
        $path = "{$dir}/teams.json";
        File::put($path, json_encode(['id' => $code, 'name' => $code, 'clubs' => $clubs]));

        return $path;
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function writeEurPoolTeam(string $id, array $extra = []): string
    {
        $dir = base_path("data/{$this->season}/EUR");
        File::ensureDirectoryExists($dir);
        $path = "{$dir}/{$id}.json";
        File::put($path, json_encode([
            'image' => "https://tmssl.akamaized.net/images/wappen/big/{$id}.png",
            'name' => "Pool Club {$id}",
            'players' => [],
        ] + $extra));

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

    public function test_backfills_pool_country_from_the_continental_list(): void
    {
        // The scraper long omitted country on pool files; without it the club
        // seeds as 'EU' and the Swiss draw can no longer keep compatriots apart.
        $poolPath = $this->writeEurPoolTeam('1090');
        $this->writeContinental([['id' => '1090', 'name' => 'AZ Alkmaar', 'country' => 'NL']]);

        $this->artisan('app:normalize-season', ['season' => $this->season])->assertSuccessful();

        $pool = json_decode(File::get($poolPath), true);
        $this->assertSame('NL', $pool['country']);
        $this->assertSame(['image', 'name', 'country'], array_slice(array_keys($pool), 0, 3));
    }

    public function test_backfills_continental_country_from_the_pool_file(): void
    {
        $this->writeEurPoolTeam('1090', ['country' => 'NL']);
        $path = $this->writeContinental([['id' => '1090', 'name' => 'AZ Alkmaar']]);

        $this->artisan('app:normalize-season', ['season' => $this->season])->assertSuccessful();

        $club = json_decode(File::get($path), true)['clubs'][0];
        $this->assertSame('NL', $club['country']);
        $this->assertSame(['id', 'name', 'country'], array_keys($club));
    }

    public function test_backfills_continental_country_from_the_club_league(): void
    {
        $this->writeEsp1([
            'id' => 'ES1',
            'name' => 'LaLiga',
            'clubs' => [['transfermarktId' => '13', 'name' => 'Atlético de Madrid', 'players' => []]],
        ]);
        $path = $this->writeContinental([['id' => '13', 'name' => 'Atlético de Madrid']]);

        $this->artisan('app:normalize-season', ['season' => $this->season])->assertSuccessful();

        $this->assertSame('ES', json_decode(File::get($path), true)['clubs'][0]['country']);
    }

    public function test_never_overwrites_a_declared_country(): void
    {
        $this->writeEurPoolTeam('1090', ['country' => 'BE']);
        $path = $this->writeContinental([['id' => '1090', 'name' => 'AZ Alkmaar', 'country' => 'NL']]);

        $this->artisan('app:normalize-season', ['season' => $this->season])->assertSuccessful();

        $this->assertSame('NL', json_decode(File::get($path), true)['clubs'][0]['country']);
        $this->assertSame('BE', json_decode(File::get(base_path("data/{$this->season}/EUR/1090.json")), true)['country']);
    }

    public function test_leaves_an_unresolvable_country_alone_and_stays_idempotent(): void
    {
        $poolPath = $this->writeEurPoolTeam('1090');
        $this->writeContinental([['id' => '1090', 'name' => 'AZ Alkmaar']]);

        $this->artisan('app:normalize-season', ['season' => $this->season])->assertSuccessful();
        $this->assertArrayNotHasKey('country', json_decode(File::get($poolPath), true));

        // Nothing left to fix: a second pass is a no-op and --check is clean.
        $this->artisan('app:normalize-season', ['season' => $this->season])
            ->expectsOutputToContain('already canonical')
            ->assertSuccessful();
        $this->artisan('app:normalize-season', ['season' => $this->season, '--check' => true])
            ->assertSuccessful();
    }
}
