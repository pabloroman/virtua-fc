<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ValidateSeasonCommandTest extends TestCase
{
    // Throwaway season kept clear of any real data/{season} folder AND of the
    // years ScaffoldSeasonCommandTest uses (2098/2099): both classes write to
    // the shared base_path('data') tree, so under `test --parallel` they must
    // not touch the same folder or they race (one's tearDown deletes the
    // other's freshly-written files). Disjoint years keep them isolated.
    private string $season = '2096';

    protected function tearDown(): void
    {
        File::deleteDirectory(base_path("data/{$this->season}"));
        parent::tearDown();
    }

    /**
     * Write data/2099/ESP1/teams.json with the given attributes and a matching
     * round-robin schedule (so only the asserted guard is the one that fires).
     *
     * @param  array<int, array<string, mixed>>  $clubs
     */
    private function writeEsp1(array $clubs, ?string $seasonId, ?int $leagueRounds = null): void
    {
        $dir = base_path("data/{$this->season}/ESP1");
        File::ensureDirectoryExists($dir);

        $teams = ['clubs' => $clubs];
        if ($seasonId !== null) {
            $teams['seasonID'] = $seasonId;
        }
        File::put("{$dir}/teams.json", json_encode($teams));

        $rounds = $leagueRounds ?? 2 * (count($clubs) - 1);
        $league = [];
        for ($i = 1; $i <= $rounds; $i++) {
            $league[] = ['round' => $i, 'date' => sprintf('%s-08-%02d', $this->season, min($i, 28))];
        }
        File::put("{$dir}/schedule.json", json_encode(['league' => $league]));
    }

    /** @return array<int, array<string, string>> */
    private function validClubs(int $count): array
    {
        $clubs = [];
        for ($i = 0; $i < $count; $i++) {
            $clubs[] = ['id' => (string) (100 + $i), 'name' => "Club {$i}"];
        }
        return $clubs;
    }

    public function test_fails_when_a_competition_teams_json_is_missing(): void
    {
        File::ensureDirectoryExists(base_path("data/{$this->season}"));

        $this->artisan('app:validate-season', ['season' => $this->season])
            ->expectsOutputToContain('teams.json missing')
            ->assertFailed();
    }

    public function test_fails_when_season_folder_absent(): void
    {
        $this->artisan('app:validate-season', ['season' => $this->season])
            ->assertFailed();
    }

    public function test_detects_season_id_mismatch(): void
    {
        $this->writeEsp1($this->validClubs(20), '2050');

        $this->artisan('app:validate-season', ['season' => $this->season])
            ->expectsOutputToContain("seasonID is '2050', expected '{$this->season}'")
            ->assertFailed();
    }

    public function test_detects_round_count_mismatch_for_round_robin_league(): void
    {
        // 20 teams require 38 league rounds; supply only 10.
        $this->writeEsp1($this->validClubs(20), $this->season, leagueRounds: 10);

        $this->artisan('app:validate-season', ['season' => $this->season])
            ->expectsOutputToContain('expected 38 league rounds for 20 teams')
            ->assertFailed();
    }

    public function test_detects_odd_team_count(): void
    {
        $this->writeEsp1($this->validClubs(19), $this->season);

        $this->artisan('app:validate-season', ['season' => $this->season])
            ->expectsOutputToContain('even count')
            ->assertFailed();
    }

    public function test_detects_unresolvable_transfermarkt_id(): void
    {
        $this->writeEsp1([
            ...$this->validClubs(19),
            ['name' => 'No Id Club'],
        ], $this->season);

        $this->artisan('app:validate-season', ['season' => $this->season])
            ->expectsOutputToContain("club 'No Id Club' has no resolvable transfermarkt id")
            ->assertFailed();
    }
}
