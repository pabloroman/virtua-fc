<?php

namespace Tests\Unit;

use App\Modules\Competition\Services\SwissDrawService;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * Covers the 48-team / 4-pots-of-12 Swiss draw used by the national-teams
 * "World Cup — Swiss Format" tournament. Each nation still plays 8 matches
 * across 8 matchdays; only the pot size differs from the 36-team UCL draw.
 */
class SwissDraw48TeamsTest extends TestCase
{
    private SwissDrawService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SwissDrawService();
    }

    /**
     * @return array<int, string>
     */
    private function makeMatchdayDates(): array
    {
        $dates = [];
        $start = Carbon::parse('2026-06-11');
        for ($i = 1; $i <= 8; $i++) {
            $dates[$i] = $start->copy()->addDays(($i - 1) * 3)->format('Y-m-d');
        }
        return $dates;
    }

    /**
     * Build 48 teams across 4 pots of 12, using confederation-like groupings
     * as the "country" protection key (mirrors the real seed data).
     */
    private function makeTeams(): array
    {
        $confeds = ['UEFA', 'CONMEBOL', 'CONCACAF', 'CAF', 'AFC', 'OFC'];
        $teams = [];

        for ($pot = 1; $pot <= 4; $pot++) {
            for ($i = 0; $i < 12; $i++) {
                $teams[] = [
                    'id' => "team-pot{$pot}-{$i}",
                    'pot' => $pot,
                    'country' => $confeds[$i % count($confeds)],
                ];
            }
        }

        return $teams;
    }

    private function assertHardInvariants(array $fixtures): void
    {
        // 48 teams × 8 / 2 = 192 unique matches
        $this->assertCount(192, $fixtures, 'Expected 192 matches');

        $matchCounts = [];
        $homeCounts = [];
        $awayCounts = [];
        foreach ($fixtures as $fixture) {
            $matchCounts[$fixture['homeTeamId']] = ($matchCounts[$fixture['homeTeamId']] ?? 0) + 1;
            $matchCounts[$fixture['awayTeamId']] = ($matchCounts[$fixture['awayTeamId']] ?? 0) + 1;
            $homeCounts[$fixture['homeTeamId']] = ($homeCounts[$fixture['homeTeamId']] ?? 0) + 1;
            $awayCounts[$fixture['awayTeamId']] = ($awayCounts[$fixture['awayTeamId']] ?? 0) + 1;

            $this->assertNotEquals($fixture['homeTeamId'], $fixture['awayTeamId'], 'A team is playing itself');
        }

        $this->assertCount(48, $matchCounts, 'Expected 48 distinct teams');
        foreach ($matchCounts as $teamId => $count) {
            $this->assertEquals(8, $count, "Team {$teamId} has {$count} matches instead of 8");
        }
        foreach ($homeCounts as $teamId => $count) {
            $this->assertEquals(4, $count, "Team {$teamId} has {$count} home games instead of 4");
        }
        foreach ($awayCounts as $teamId => $count) {
            $this->assertEquals(4, $count, "Team {$teamId} has {$count} away games instead of 4");
        }

        // No duplicate pairings
        $pairs = [];
        foreach ($fixtures as $fixture) {
            $a = $fixture['homeTeamId'];
            $b = $fixture['awayTeamId'];
            $key = $a < $b ? "{$a}|{$b}" : "{$b}|{$a}";
            $this->assertArrayNotHasKey($key, $pairs, "Duplicate pairing: {$a} vs {$b}");
            $pairs[$key] = true;
        }

        // 8 matchdays with 24 matches each, no team twice per round
        $matchdayCounts = [];
        $roundTeams = [];
        foreach ($fixtures as $fixture) {
            $md = $fixture['matchday'];
            $matchdayCounts[$md] = ($matchdayCounts[$md] ?? 0) + 1;
            $roundTeams[$md][] = $fixture['homeTeamId'];
            $roundTeams[$md][] = $fixture['awayTeamId'];
        }
        $this->assertCount(8, $matchdayCounts, 'Expected 8 matchdays');
        foreach ($matchdayCounts as $md => $count) {
            $this->assertEquals(24, $count, "Matchday {$md} has {$count} matches instead of 24");
        }
        foreach ($roundTeams as $md => $teamIds) {
            $duplicates = array_diff_assoc($teamIds, array_unique($teamIds));
            $this->assertEmpty($duplicates, "Round {$md} has double-booked teams");
        }
    }

    public function test_produces_valid_48_team_draw(): void
    {
        // Multiple iterations to catch non-deterministic failures.
        for ($i = 0; $i < 10; $i++) {
            $fixtures = $this->service->generateFixtures($this->makeTeams(), $this->makeMatchdayDates());
            $this->assertHardInvariants($fixtures);
        }
    }

    public function test_all_same_confederation_falls_back_to_circle_method(): void
    {
        // Protection is impossible when every team shares a confederation —
        // the circle-method fallback must still yield a valid 48-team draw.
        $teams = [];
        for ($pot = 1; $pot <= 4; $pot++) {
            for ($i = 0; $i < 12; $i++) {
                $teams[] = ['id' => "team-pot{$pot}-{$i}", 'pot' => $pot, 'country' => 'UEFA'];
            }
        }

        $fixtures = $this->service->generateFixtures($teams, $this->makeMatchdayDates());
        $this->assertHardInvariants($fixtures);
    }

    public function test_rejects_field_not_divisible_into_four_equal_pots(): void
    {
        $teams = [];
        // 44 teams: pots of 11 — still divisible by 4, but make pot 4 short to
        // trigger the equal-size guard.
        for ($pot = 1; $pot <= 4; $pot++) {
            $size = $pot === 4 ? 8 : 12;
            for ($i = 0; $i < $size; $i++) {
                $teams[] = ['id' => "team-pot{$pot}-{$i}", 'pot' => $pot, 'country' => 'UEFA'];
            }
        }

        $this->expectException(\InvalidArgumentException::class);
        $this->service->generateFixtures($teams, $this->makeMatchdayDates());
    }
}
