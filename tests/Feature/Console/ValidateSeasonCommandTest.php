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

    /**
     * Write a continental participant list (UCL unless told otherwise).
     *
     * @param  array<int, array<string, mixed>>  $clubs
     */
    private function writeContinental(array $clubs, string $code = 'UCL'): void
    {
        $dir = base_path("data/{$this->season}/{$code}");
        File::ensureDirectoryExists($dir);
        File::put("{$dir}/teams.json", json_encode(['seasonID' => $this->season, 'clubs' => $clubs]));
        File::put("{$dir}/schedule.json", json_encode(['league' => []]));
    }

    /** Give a continental club somewhere for its squad to come from. */
    private function writeEurPoolTeam(string $id, string $name = 'Pool Club', ?string $country = 'NL'): void
    {
        $dir = base_path("data/{$this->season}/EUR");
        File::ensureDirectoryExists($dir);
        File::put("{$dir}/{$id}.json", json_encode(array_filter([
            'image' => "https://tmssl.akamaized.net/images/wappen/big/{$id}.png",
            'name' => $name,
            'country' => $country,
            'players' => [],
        ], fn ($v) => $v !== null)));
    }

    /**
     * A full 36-club Swiss field, backed by EUR pool files so the squad-source
     * check passes and only the asserted guard can fire.
     *
     * @return array<int, array<string, mixed>>
     */
    private function seedableSwissField(bool $withPots = true): array
    {
        $clubs = [];
        for ($i = 0; $i < 36; $i++) {
            $id = (string) (500 + $i);
            $this->writeEurPoolTeam($id, "Euro Club {$i}");
            $club = ['id' => $id, 'name' => "Euro Club {$i}", 'country' => 'NL'];
            if ($withPots) {
                $club['pot'] = intdiv($i, 9) + 1;
            }
            $clubs[] = $club;
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

    public function test_detects_continental_club_with_no_squad_data(): void
    {
        $clubs = $this->seedableSwissField();
        $clubs[0] = ['id' => '999999', 'name' => 'Ghost FC', 'country' => 'NL', 'pot' => 1];
        $this->writeContinental($clubs);

        // One assertion only: PendingCommand registers a separate mock
        // expectation per expected substring, and a single written line is
        // routed to just the first one that matches — so two
        // expectsOutputToContain calls can never both match the same line.
        // "Ghost FC (999999)" is the unseedable-participant error's own
        // "{name} ({id})" format and appears in no other message.
        $this->artisan('app:validate-season', ['season' => $this->season])
            ->expectsOutputToContain('Ghost FC (999999)')
            ->assertFailed();
    }

    public function test_accepts_a_continental_club_backed_by_an_eur_pool_file(): void
    {
        $this->writeContinental($this->seedableSwissField());

        $this->artisan('app:validate-season', ['season' => $this->season])
            ->doesntExpectOutputToContain('have no squad data')
            ->assertFailed(); // ESP1 and friends are still missing from this fixture.
    }

    public function test_accepts_a_continental_club_backed_by_a_league(): void
    {
        // 20 ESP1 clubs with ids 100..119; point the Swiss field's first entry at one.
        $this->writeEsp1($this->validClubs(20), $this->season);
        $clubs = $this->seedableSwissField();
        $clubs[0] = ['id' => '100', 'name' => 'Club 0', 'country' => 'ES', 'pot' => 1];
        $this->writeContinental($clubs);

        $this->artisan('app:validate-season', ['season' => $this->season])
            ->doesntExpectOutputToContain('have no squad data')
            ->assertFailed();
    }

    public function test_requires_a_literal_id_on_continental_clubs(): void
    {
        // The seeder reads $club['id'] directly, so a crest-only club resolves
        // in loadClubs() but is silently dropped at seed time.
        $clubs = $this->seedableSwissField();
        $clubs[0] = [
            'image' => 'https://tmssl.akamaized.net/images/wappen/big/500.png',
            'name' => 'Crest Only FC',
            'pot' => 1,
        ];
        $this->writeContinental($clubs);

        $this->artisan('app:validate-season', ['season' => $this->season])
            ->expectsOutputToContain("club 'Crest Only FC' has no literal 'id' key")
            ->assertFailed();
    }

    public function test_detects_a_club_entered_in_two_swiss_competitions(): void
    {
        // A club knocked out of Champions League qualifying drops into the
        // Europa League, so a fixture-page scrape lists it under both.
        $ucl = $this->seedableSwissField();
        $uel = $this->seedableSwissField();
        $uel[0] = $ucl[0];

        $this->writeContinental($ucl);
        $this->writeContinental($uel, 'UEL');

        $this->artisan('app:validate-season', ['season' => $this->season])
            ->expectsOutputToContain('is also a UCL entrant')
            ->assertFailed();
    }

    public function test_allows_a_super_cup_finalist_to_also_play_in_the_champions_league(): void
    {
        // The Super Cup is contested by the prior season's UCL and UEL winners,
        // who are in that season's European competitions too — not a duplicate.
        $ucl = $this->seedableSwissField();
        $this->writeContinental($ucl);
        $this->writeContinental([$ucl[0], $ucl[1]], 'UEFASUP');

        $this->artisan('app:validate-season', ['season' => $this->season])
            ->doesntExpectOutputToContain('entrant')
            ->assertFailed(); // ESP1 and friends are still missing from this fixture.
    }

    public function test_detects_a_continental_knockout_that_is_not_a_two_club_tie(): void
    {
        // The 2026 refresh scraped seven clubs onto the Super Cup — a single
        // tie — and nothing checked it, because only swiss_format had a shape.
        $this->writeContinental(array_slice($this->seedableSwissField(), 0, 7), 'UEFASUP');

        $this->artisan('app:validate-season', ['season' => $this->season])
            ->expectsOutputToContain('single two-club tie, got 7 clubs')
            ->assertFailed();
    }

    public function test_detects_short_swiss_field(): void
    {
        $this->writeContinental(array_slice($this->seedableSwissField(), 0, 35));

        $this->artisan('app:validate-season', ['season' => $this->season])
            ->expectsOutputToContain('swiss league phase needs exactly 36 clubs, got 35')
            ->assertFailed();
    }

    public function test_warns_but_does_not_fail_when_a_swiss_field_has_no_pots(): void
    {
        $this->writeContinental($this->seedableSwissField(withPots: false));

        $this->artisan('app:validate-season', ['season' => $this->season])
            ->expectsOutputToContain('no seeding pots')
            ->doesntExpectOutputToContain('every pot must hold')
            ->assertFailed();
    }

    public function test_detects_partially_potted_swiss_field(): void
    {
        $clubs = $this->seedableSwissField();
        foreach (range(0, 15) as $i) {
            unset($clubs[$i]['pot']);
        }
        $this->writeContinental($clubs);

        $this->artisan('app:validate-season', ['season' => $this->season])
            ->expectsOutputToContain('only 20 of 36 clubs have a')
            ->assertFailed();
    }

    public function test_detects_unbalanced_pots(): void
    {
        $clubs = $this->seedableSwissField();
        $clubs[35]['pot'] = 1; // pot 1 gets 10, pot 4 gets 8

        $this->writeContinental($clubs);

        $this->artisan('app:validate-season', ['season' => $this->season])
            ->expectsOutputToContain('every pot must hold exactly 9 clubs')
            ->assertFailed();
    }

    public function test_does_not_apply_the_swiss_shape_to_a_knockout_continental_cup(): void
    {
        // UEFASUP is a two-club knockout, not a Swiss league phase.
        $this->writeEurPoolTeam('700', 'Winner A');
        $this->writeEurPoolTeam('701', 'Winner B');
        $this->writeContinental([
            ['id' => '700', 'name' => 'Winner A', 'country' => 'EN'],
            ['id' => '701', 'name' => 'Winner B', 'country' => 'FR'],
        ], 'UEFASUP');

        $this->artisan('app:validate-season', ['season' => $this->season])
            ->doesntExpectOutputToContain('swiss league phase needs exactly')
            ->assertFailed();
    }

    public function test_warns_when_a_swiss_club_has_no_country_anywhere(): void
    {
        $clubs = $this->seedableSwissField();
        unset($clubs[0]['country']);
        $this->writeEurPoolTeam($clubs[0]['id'], $clubs[0]['name'], country: null);
        $this->writeContinental($clubs);

        $this->artisan('app:validate-season', ['season' => $this->season])
            ->expectsOutputToContain('cannot keep them apart from their compatriots')
            ->assertFailed();
    }
}
