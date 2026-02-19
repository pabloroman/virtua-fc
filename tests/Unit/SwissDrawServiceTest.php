<?php

namespace Tests\Unit;

use App\Modules\Competition\Services\SwissDrawService;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class SwissDrawServiceTest extends TestCase
{
    private SwissDrawService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SwissDrawService();
    }

    /**
     * Build matchday dates array for 8 matchdays.
     *
     * @return array<int, string>
     */
    private function makeMatchdayDates(): array
    {
        $dates = [];
        $start = Carbon::parse('2025-09-17');
        for ($i = 1; $i <= 8; $i++) {
            $dates[$i] = $start->copy()->addDays(($i - 1) * 14)->format('Y-m-d');
        }
        return $dates;
    }

    /**
     * Build 36 teams across 4 pots with diverse countries.
     */
    private function makeTeams(): array
    {
        $countries = ['ES', 'EN', 'DE', 'IT', 'FR', 'PT', 'NL', 'BE', 'TR'];
        $teams = [];

        for ($pot = 1; $pot <= 4; $pot++) {
            for ($i = 0; $i < 9; $i++) {
                $teams[] = [
                    'id' => "team-pot{$pot}-{$i}",
                    'pot' => $pot,
                    'country' => $countries[$i],
                ];
            }
        }

        return $teams;
    }

    public function test_each_team_has_exactly_8_fixtures(): void
    {
        $teams = $this->makeTeams();
        $fixtures = $this->service->generateFixtures($teams, $this->makeMatchdayDates());

        // Count matches per team
        $matchCounts = [];
        foreach ($fixtures as $fixture) {
            $matchCounts[$fixture['homeTeamId']] = ($matchCounts[$fixture['homeTeamId']] ?? 0) + 1;
            $matchCounts[$fixture['awayTeamId']] = ($matchCounts[$fixture['awayTeamId']] ?? 0) + 1;
        }

        foreach ($matchCounts as $teamId => $count) {
            $this->assertEquals(8, $count, "Team {$teamId} has {$count} matches instead of 8");
        }
    }

    public function test_total_match_count_is_144(): void
    {
        $teams = $this->makeTeams();
        $fixtures = $this->service->generateFixtures($teams, $this->makeMatchdayDates());

        // 36 teams × 8 matches / 2 = 144 unique matches
        $this->assertCount(144, $fixtures);
    }

    public function test_no_team_plays_itself(): void
    {
        $teams = $this->makeTeams();
        $fixtures = $this->service->generateFixtures($teams, $this->makeMatchdayDates());

        foreach ($fixtures as $fixture) {
            $this->assertNotEquals(
                $fixture['homeTeamId'],
                $fixture['awayTeamId'],
                'A team is playing itself'
            );
        }
    }

    public function test_no_duplicate_pairings(): void
    {
        $teams = $this->makeTeams();
        $fixtures = $this->service->generateFixtures($teams, $this->makeMatchdayDates());

        $pairs = [];
        foreach ($fixtures as $fixture) {
            $a = $fixture['homeTeamId'];
            $b = $fixture['awayTeamId'];
            $key = $a < $b ? "{$a}|{$b}" : "{$b}|{$a}";
            $this->assertArrayNotHasKey($key, $pairs, "Duplicate pairing: {$a} vs {$b}");
            $pairs[$key] = true;
        }
    }

    public function test_each_matchday_has_18_matches(): void
    {
        $teams = $this->makeTeams();
        $fixtures = $this->service->generateFixtures($teams, $this->makeMatchdayDates());

        $matchdayCounts = [];
        foreach ($fixtures as $fixture) {
            $md = $fixture['matchday'];
            $matchdayCounts[$md] = ($matchdayCounts[$md] ?? 0) + 1;
        }

        foreach ($matchdayCounts as $md => $count) {
            $this->assertEquals(18, $count, "Matchday {$md} has {$count} matches instead of 18");
        }
    }

    public function test_no_team_plays_twice_in_same_round(): void
    {
        $teams = $this->makeTeams();

        for ($i = 0; $i < 10; $i++) {
            $fixtures = $this->service->generateFixtures($teams, $this->makeMatchdayDates());

            $roundTeams = [];
            foreach ($fixtures as $fixture) {
                $md = $fixture['matchday'];
                $roundTeams[$md][] = $fixture['homeTeamId'];
                $roundTeams[$md][] = $fixture['awayTeamId'];
            }

            foreach ($roundTeams as $md => $teamIds) {
                $duplicates = array_diff_assoc($teamIds, array_unique($teamIds));
                $this->assertEmpty(
                    $duplicates,
                    "Iteration {$i}: Round {$md} has double-booked teams: " . implode(', ', array_unique($duplicates))
                );
            }
        }
    }

    public function test_each_team_has_4_home_and_4_away_games(): void
    {
        $teams = $this->makeTeams();

        for ($i = 0; $i < 10; $i++) {
            $fixtures = $this->service->generateFixtures($teams, $this->makeMatchdayDates());

            $homeCounts = [];
            $awayCounts = [];
            foreach ($fixtures as $fixture) {
                $homeCounts[$fixture['homeTeamId']] = ($homeCounts[$fixture['homeTeamId']] ?? 0) + 1;
                $awayCounts[$fixture['awayTeamId']] = ($awayCounts[$fixture['awayTeamId']] ?? 0) + 1;
            }

            foreach ($homeCounts as $teamId => $count) {
                $this->assertEquals(4, $count, "Iteration {$i}: Team {$teamId} has {$count} home games instead of 4");
            }

            foreach ($awayCounts as $teamId => $count) {
                $this->assertEquals(4, $count, "Iteration {$i}: Team {$teamId} has {$count} away games instead of 4");
            }
        }
    }

    public function test_no_same_country_matchups(): void
    {
        $teams = $this->makeTeams();
        $teamCountry = [];
        foreach ($teams as $team) {
            $teamCountry[$team['id']] = $team['country'];
        }

        for ($i = 0; $i < 10; $i++) {
            $fixtures = $this->service->generateFixtures($teams, $this->makeMatchdayDates());

            foreach ($fixtures as $fixture) {
                $homeCountry = $teamCountry[$fixture['homeTeamId']];
                $awayCountry = $teamCountry[$fixture['awayTeamId']];
                $this->assertNotEquals(
                    $homeCountry,
                    $awayCountry,
                    "Same-country matchup: {$fixture['homeTeamId']} ({$homeCountry}) vs {$fixture['awayTeamId']} ({$awayCountry})"
                );
            }
        }
    }

    /**
     * Run multiple iterations to catch non-deterministic failures.
     */
    public function test_fixture_consistency_across_multiple_draws(): void
    {
        $teams = $this->makeTeams();

        for ($i = 0; $i < 10; $i++) {
            $fixtures = $this->service->generateFixtures($teams, $this->makeMatchdayDates());

            $this->assertCount(144, $fixtures, "Iteration {$i}: expected 144 matches");

            $matchCounts = [];
            foreach ($fixtures as $fixture) {
                $matchCounts[$fixture['homeTeamId']] = ($matchCounts[$fixture['homeTeamId']] ?? 0) + 1;
                $matchCounts[$fixture['awayTeamId']] = ($matchCounts[$fixture['awayTeamId']] ?? 0) + 1;
            }

            foreach ($matchCounts as $teamId => $count) {
                $this->assertEquals(8, $count, "Iteration {$i}: Team {$teamId} has {$count} matches");
            }
        }
    }

    /**
     * Test with UUID-like IDs to ensure delimiter handling works.
     */
    public function test_works_with_uuid_ids(): void
    {
        $countries = ['ES', 'EN', 'DE', 'IT', 'FR', 'PT', 'NL', 'BE', 'TR'];
        $teams = [];

        for ($pot = 1; $pot <= 4; $pot++) {
            for ($i = 0; $i < 9; $i++) {
                $teams[] = [
                    'id' => sprintf(
                        '%08x-%04x-%04x-%04x-%012x',
                        $pot * 1000 + $i, $pot, $i, rand(0, 0xffff), rand(0, 0xffffffffffff)
                    ),
                    'pot' => $pot,
                    'country' => $countries[$i],
                ];
            }
        }

        $fixtures = $this->service->generateFixtures($teams, $this->makeMatchdayDates());

        $this->assertCount(144, $fixtures);

        $matchCounts = [];
        foreach ($fixtures as $fixture) {
            $matchCounts[$fixture['homeTeamId']] = ($matchCounts[$fixture['homeTeamId']] ?? 0) + 1;
            $matchCounts[$fixture['awayTeamId']] = ($matchCounts[$fixture['awayTeamId']] ?? 0) + 1;
        }

        foreach ($matchCounts as $teamId => $count) {
            $this->assertEquals(8, $count, "Team {$teamId} has {$count} matches instead of 8");
        }
    }
}
