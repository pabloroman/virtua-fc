<?php

namespace App\Game\Services;

use Carbon\Carbon;

/**
 * Generates round-robin league fixtures using the circle method.
 *
 * For N teams (must be even), generates N-1 rounds for the first half of the season,
 * then mirrors them (swapping home/away) for the second half.
 *
 * Input: team IDs + matchday schedule (dates per round).
 * Output: flat array of fixtures matching SwissDrawService format.
 */
class LeagueFixtureGenerator
{
    /**
     * Load matchday calendar from a competition's matchdays.json file.
     *
     * @param  string  $competitionId  e.g. 'ESP1', 'ESP2'
     * @param  string  $season  e.g. '2025'
     * @return array<array{round: int, date: string}>
     */
    public static function loadMatchdays(string $competitionId, string $season): array
    {
        $path = base_path("data/{$season}/{$competitionId}/matchdays.json");

        if (!file_exists($path)) {
            throw new \RuntimeException("Matchdays file not found: {$path}");
        }

        return json_decode(file_get_contents($path), true);
    }

    /**
     * Adjust matchday dates by a year offset.
     * Used for generating fixtures in subsequent seasons.
     *
     * @param  array<array{round: int, date: string}>  $matchdays
     * @param  int  $yearOffset  Number of years to add (e.g. 1 for next season)
     * @return array<array{round: int, date: string}>
     */
    public static function adjustMatchdayYears(array $matchdays, int $yearOffset): array
    {
        return array_map(function ($md) use ($yearOffset) {
            $date = Carbon::createFromFormat('d/m/y', $md['date'])->addYears($yearOffset);

            return [
                'round' => $md['round'],
                'date' => $date->format('d/m/y'),
            ];
        }, $matchdays);
    }
    /**
     * Generate a full double round-robin schedule.
     *
     * @param  array<string>  $teamIds  Team IDs (must be even count, minimum 4)
     * @param  array<array{round: int, date: string}>  $matchdays  Schedule with round numbers and dates (dd/mm/yy)
     * @return array<array{matchday: int, date: string, homeTeamId: string, awayTeamId: string}>
     */
    public function generate(array $teamIds, array $matchdays): array
    {
        $teamCount = count($teamIds);

        if ($teamCount < 4 || $teamCount % 2 !== 0) {
            throw new \InvalidArgumentException(
                "Team count must be even and at least 4, got {$teamCount}"
            );
        }

        $halfSeason = $teamCount - 1;
        $expectedMatchdays = $halfSeason * 2;

        if (count($matchdays) !== $expectedMatchdays) {
            throw new \InvalidArgumentException(
                "Expected {$expectedMatchdays} matchdays for {$teamCount} teams, got " . count($matchdays)
            );
        }

        // Shuffle team order so the schedule is different each time
        $teams = $teamIds;
        shuffle($teams);

        $firstHalf = $this->generateFirstHalf($teams);

        return $this->buildFixtures($firstHalf, $matchdays, $teams);
    }

    /**
     * Generate first half pairings using the circle method.
     *
     * Fixes team[0] in place and rotates the rest clockwise.
     * Returns array indexed by round (0-based), each containing
     * pairs of [homeIndex, awayIndex] into the $teams array.
     *
     * @param  array<string>  $teams
     * @return array<int, array<array{int, int}>>
     */
    private function generateFirstHalf(array $teams): array
    {
        $n = count($teams);
        $halfN = $n / 2;
        $rounds = $n - 1;

        // Positions 0..n-2 rotate; position 0 is fixed.
        // We build a "rotating" array of indices 1..n-1
        $rotating = range(1, $n - 1);

        $schedule = [];

        for ($round = 0; $round < $rounds; $round++) {
            $pairs = [];

            // First pair: fixed team (index 0) vs first in rotation
            // Alternate home/away for the fixed team to balance
            if ($round % 2 === 0) {
                $pairs[] = [0, $rotating[0]];
            } else {
                $pairs[] = [$rotating[0], 0];
            }

            // Remaining pairs: mirror positions from rotation array
            // Alternate home/away by pair position to minimize consecutive
            // same-venue games (achieves theoretical minimum of n-2 breaks)
            for ($i = 1; $i < $halfN; $i++) {
                if ($i % 2 === 1) {
                    $pairs[] = [$rotating[$i], $rotating[$n - 1 - $i]];
                } else {
                    $pairs[] = [$rotating[$n - 1 - $i], $rotating[$i]];
                }
            }

            $schedule[] = $pairs;

            // Rotate: last element moves to front
            $last = array_pop($rotating);
            array_unshift($rotating, $last);
        }

        return $schedule;
    }

    /**
     * Build the flat fixture array from first-half pairings.
     *
     * Second half mirrors first half with home/away swapped.
     *
     * @param  array<int, array<array{int, int}>>  $firstHalf
     * @param  array<array{round: int, date: string}>  $matchdays
     * @param  array<string>  $teams
     * @return array<array{matchday: int, date: string, homeTeamId: string, awayTeamId: string}>
     */
    private function buildFixtures(array $firstHalf, array $matchdays, array $teams): array
    {
        $fixtures = [];
        $halfSeason = count($firstHalf);

        // Index matchdays by round number for lookup
        $matchdayMap = [];
        foreach ($matchdays as $md) {
            $matchdayMap[$md['round']] = $md['date'];
        }

        // First half of the season
        for ($round = 0; $round < $halfSeason; $round++) {
            $matchday = $round + 1;
            $date = $matchdayMap[$matchday];

            foreach ($firstHalf[$round] as [$homeIdx, $awayIdx]) {
                $fixtures[] = [
                    'matchday' => $matchday,
                    'date' => $date,
                    'homeTeamId' => $teams[$homeIdx],
                    'awayTeamId' => $teams[$awayIdx],
                ];
            }
        }

        // Second half: swap home/away
        for ($round = 0; $round < $halfSeason; $round++) {
            $matchday = $halfSeason + $round + 1;
            $date = $matchdayMap[$matchday];

            foreach ($firstHalf[$round] as [$homeIdx, $awayIdx]) {
                $fixtures[] = [
                    'matchday' => $matchday,
                    'date' => $date,
                    'homeTeamId' => $teams[$awayIdx],
                    'awayTeamId' => $teams[$homeIdx],
                ];
            }
        }

        return $fixtures;
    }
}
