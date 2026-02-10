<?php

namespace Tests\Unit;

use App\Game\Services\LeagueFixtureGenerator;
use PHPUnit\Framework\TestCase;

class LeagueFixtureGeneratorTest extends TestCase
{
    private LeagueFixtureGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new LeagueFixtureGenerator();
    }

    private function makeTeams(int $count): array
    {
        return array_map(fn ($i) => "team-{$i}", range(1, $count));
    }

    private function makeMatchdays(int $count): array
    {
        $matchdays = [];
        for ($i = 1; $i <= $count; $i++) {
            $matchdays[] = [
                'round' => $i,
                'date' => sprintf('%02d/08/25', $i),
            ];
        }

        return $matchdays;
    }

    // ──────────────────────────────────────────────────────────────
    // 20-team league (La Liga: 38 matchdays, 10 matches per round)
    // ──────────────────────────────────────────────────────────────

    public function test_20_teams_produces_380_fixtures(): void
    {
        $teams = $this->makeTeams(20);
        $matchdays = $this->makeMatchdays(38);
        $fixtures = $this->generator->generate($teams, $matchdays);

        // 20 teams × 38 matches each / 2 = 380
        $this->assertCount(380, $fixtures);
    }

    public function test_20_teams_each_team_plays_38_matches(): void
    {
        $teams = $this->makeTeams(20);
        $matchdays = $this->makeMatchdays(38);
        $fixtures = $this->generator->generate($teams, $matchdays);

        $counts = $this->countMatchesPerTeam($fixtures);

        foreach ($counts as $teamId => $count) {
            $this->assertEquals(38, $count, "Team {$teamId} has {$count} matches instead of 38");
        }
    }

    public function test_20_teams_10_matches_per_matchday(): void
    {
        $teams = $this->makeTeams(20);
        $matchdays = $this->makeMatchdays(38);
        $fixtures = $this->generator->generate($teams, $matchdays);

        $perRound = $this->countMatchesPerRound($fixtures);

        foreach ($perRound as $round => $count) {
            $this->assertEquals(10, $count, "Round {$round} has {$count} matches instead of 10");
        }
    }

    // ──────────────────────────────────────────────────────────────
    // 22-team league (La Liga 2: 42 matchdays, 11 matches per round)
    // ──────────────────────────────────────────────────────────────

    public function test_22_teams_produces_462_fixtures(): void
    {
        $teams = $this->makeTeams(22);
        $matchdays = $this->makeMatchdays(42);
        $fixtures = $this->generator->generate($teams, $matchdays);

        // 22 teams × 42 matches each / 2 = 462
        $this->assertCount(462, $fixtures);
    }

    public function test_22_teams_each_team_plays_42_matches(): void
    {
        $teams = $this->makeTeams(22);
        $matchdays = $this->makeMatchdays(42);
        $fixtures = $this->generator->generate($teams, $matchdays);

        $counts = $this->countMatchesPerTeam($fixtures);

        foreach ($counts as $teamId => $count) {
            $this->assertEquals(42, $count, "Team {$teamId} has {$count} matches instead of 42");
        }
    }

    public function test_22_teams_11_matches_per_matchday(): void
    {
        $teams = $this->makeTeams(22);
        $matchdays = $this->makeMatchdays(42);
        $fixtures = $this->generator->generate($teams, $matchdays);

        $perRound = $this->countMatchesPerRound($fixtures);

        foreach ($perRound as $round => $count) {
            $this->assertEquals(11, $count, "Round {$round} has {$count} matches instead of 11");
        }
    }

    // ──────────────────────────────────────────────────────────────
    // Core invariants
    // ──────────────────────────────────────────────────────────────

    public function test_no_team_plays_itself(): void
    {
        $teams = $this->makeTeams(20);
        $matchdays = $this->makeMatchdays(38);
        $fixtures = $this->generator->generate($teams, $matchdays);

        foreach ($fixtures as $fixture) {
            $this->assertNotEquals(
                $fixture['homeTeamId'],
                $fixture['awayTeamId'],
                'A team is playing against itself'
            );
        }
    }

    public function test_no_team_plays_twice_in_same_round(): void
    {
        $teams = $this->makeTeams(20);
        $matchdays = $this->makeMatchdays(38);
        $fixtures = $this->generator->generate($teams, $matchdays);

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
                "Round {$md} has double-booked teams: " . implode(', ', array_unique($duplicates))
            );
        }
    }

    public function test_every_pair_plays_exactly_twice(): void
    {
        $teams = $this->makeTeams(20);
        $matchdays = $this->makeMatchdays(38);
        $fixtures = $this->generator->generate($teams, $matchdays);

        $pairCounts = [];
        foreach ($fixtures as $fixture) {
            $a = $fixture['homeTeamId'];
            $b = $fixture['awayTeamId'];
            $key = $a < $b ? "{$a}|{$b}" : "{$b}|{$a}";
            $pairCounts[$key] = ($pairCounts[$key] ?? 0) + 1;
        }

        foreach ($pairCounts as $pair => $count) {
            $this->assertEquals(2, $count, "Pair {$pair} plays {$count} times instead of 2");
        }
    }

    public function test_second_half_reverses_home_away(): void
    {
        $teams = $this->makeTeams(20);
        $matchdays = $this->makeMatchdays(38);
        $fixtures = $this->generator->generate($teams, $matchdays);

        // Group by matchday
        $byRound = [];
        foreach ($fixtures as $fixture) {
            $byRound[$fixture['matchday']][] = $fixture;
        }

        $halfSeason = 19;

        // For each first-half round, find the mirrored second-half round
        for ($round = 1; $round <= $halfSeason; $round++) {
            $mirrorRound = $halfSeason + $round;

            $firstHalfPairs = [];
            foreach ($byRound[$round] as $f) {
                $firstHalfPairs["{$f['homeTeamId']}|{$f['awayTeamId']}"] = true;
            }

            foreach ($byRound[$mirrorRound] as $f) {
                // Second half should have the reverse: away|home from first half
                $reversed = "{$f['awayTeamId']}|{$f['homeTeamId']}";
                $this->assertArrayHasKey(
                    $reversed,
                    $firstHalfPairs,
                    "Round {$mirrorRound} fixture {$f['homeTeamId']} vs {$f['awayTeamId']} doesn't mirror round {$round}"
                );
            }
        }
    }

    public function test_each_team_has_balanced_home_away(): void
    {
        $teams = $this->makeTeams(20);
        $matchdays = $this->makeMatchdays(38);
        $fixtures = $this->generator->generate($teams, $matchdays);

        $homeCount = [];
        $awayCount = [];

        foreach ($fixtures as $fixture) {
            $homeCount[$fixture['homeTeamId']] = ($homeCount[$fixture['homeTeamId']] ?? 0) + 1;
            $awayCount[$fixture['awayTeamId']] = ($awayCount[$fixture['awayTeamId']] ?? 0) + 1;
        }

        foreach ($teams as $teamId) {
            $this->assertEquals(19, $homeCount[$teamId] ?? 0, "Team {$teamId} has wrong home count");
            $this->assertEquals(19, $awayCount[$teamId] ?? 0, "Team {$teamId} has wrong away count");
        }
    }

    public function test_fixtures_use_provided_dates(): void
    {
        $teams = $this->makeTeams(4);
        $matchdays = [
            ['round' => 1, 'date' => '17/08/25'],
            ['round' => 2, 'date' => '24/08/25'],
            ['round' => 3, 'date' => '31/08/25'],
            ['round' => 4, 'date' => '14/09/25'],
            ['round' => 5, 'date' => '21/09/25'],
            ['round' => 6, 'date' => '28/09/25'],
        ];

        $fixtures = $this->generator->generate($teams, $matchdays);

        foreach ($fixtures as $fixture) {
            $expected = $matchdays[$fixture['matchday'] - 1]['date'];
            $this->assertEquals($expected, $fixture['date'], "Matchday {$fixture['matchday']} has wrong date");
        }
    }

    // ──────────────────────────────────────────────────────────────
    // Consistency across multiple runs (shuffled order)
    // ──────────────────────────────────────────────────────────────

    public function test_invariants_hold_across_multiple_runs(): void
    {
        $teams = $this->makeTeams(20);
        $matchdays = $this->makeMatchdays(38);

        for ($i = 0; $i < 10; $i++) {
            $fixtures = $this->generator->generate($teams, $matchdays);

            $this->assertCount(380, $fixtures, "Iteration {$i}: wrong fixture count");

            $counts = $this->countMatchesPerTeam($fixtures);
            foreach ($counts as $teamId => $count) {
                $this->assertEquals(38, $count, "Iteration {$i}: Team {$teamId} has {$count} matches");
            }

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
                    "Iteration {$i}: Round {$md} has double-booked teams"
                );
            }
        }
    }

    // ──────────────────────────────────────────────────────────────
    // Home/Away alternation
    // ──────────────────────────────────────────────────────────────

    public function test_no_team_has_more_than_2_consecutive_in_each_half(): void
    {
        $teams = $this->makeTeams(20);
        $matchdays = $this->makeMatchdays(38);
        $halfSeason = 19;

        // Run multiple times since team order is shuffled
        for ($run = 0; $run < 10; $run++) {
            $fixtures = $this->generator->generate($teams, $matchdays);

            // Build home/away sequence per team ordered by matchday
            $sequences = [];
            foreach ($fixtures as $fixture) {
                $md = $fixture['matchday'];
                $sequences[$fixture['homeTeamId']][$md] = 'H';
                $sequences[$fixture['awayTeamId']][$md] = 'A';
            }

            foreach ($sequences as $teamId => $matchdayVenues) {
                ksort($matchdayVenues);
                $venues = array_values($matchdayVenues);

                // Check each half separately (max 2 consecutive guaranteed)
                foreach (['first' => [0, $halfSeason], 'second' => [$halfSeason, $halfSeason]] as $half => [$offset, $length]) {
                    $halfVenues = array_slice($venues, $offset, $length);
                    $maxConsecutive = $this->maxConsecutiveRun($halfVenues);

                    $this->assertLessThanOrEqual(
                        2,
                        $maxConsecutive,
                        "Run {$run}: Team {$teamId} has {$maxConsecutive} consecutive same-venue games in {$half} half. Pattern: " . implode('', $halfVenues)
                    );
                }

                // Full season: max 3 (boundary between halves can add 1)
                $maxFull = $this->maxConsecutiveRun($venues);
                $this->assertLessThanOrEqual(
                    3,
                    $maxFull,
                    "Run {$run}: Team {$teamId} has {$maxFull} consecutive same-venue games. Pattern: " . implode('', $venues)
                );
            }
        }
    }

    // ──────────────────────────────────────────────────────────────
    // Validation
    // ──────────────────────────────────────────────────────────────

    public function test_rejects_odd_team_count(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->generator->generate($this->makeTeams(19), $this->makeMatchdays(36));
    }

    public function test_rejects_fewer_than_4_teams(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->generator->generate($this->makeTeams(2), $this->makeMatchdays(2));
    }

    public function test_rejects_wrong_matchday_count(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->generator->generate($this->makeTeams(20), $this->makeMatchdays(30));
    }

    // ──────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────

    private function countMatchesPerTeam(array $fixtures): array
    {
        $counts = [];
        foreach ($fixtures as $fixture) {
            $counts[$fixture['homeTeamId']] = ($counts[$fixture['homeTeamId']] ?? 0) + 1;
            $counts[$fixture['awayTeamId']] = ($counts[$fixture['awayTeamId']] ?? 0) + 1;
        }

        return $counts;
    }

    private function countMatchesPerRound(array $fixtures): array
    {
        $counts = [];
        foreach ($fixtures as $fixture) {
            $counts[$fixture['matchday']] = ($counts[$fixture['matchday']] ?? 0) + 1;
        }

        return $counts;
    }

    private function maxConsecutiveRun(array $venues): int
    {
        if (empty($venues)) {
            return 0;
        }

        $max = 1;
        $current = 1;

        for ($i = 1; $i < count($venues); $i++) {
            if ($venues[$i] === $venues[$i - 1]) {
                $current++;
                $max = max($max, $current);
            } else {
                $current = 1;
            }
        }

        return $max;
    }
}
