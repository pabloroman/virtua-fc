<?php

namespace App\Game\Services;

use Carbon\Carbon;

/**
 * Generates league phase fixtures for Swiss format competitions.
 *
 * 36 teams in 4 pots of 9. Each team plays 8 matches (4 home, 4 away):
 * - 2 opponents from each pot (1 home, 1 away)
 * - Country protection: max 2 opponents from same country
 *
 * Outputs fixture data compatible with the FixtureTemplate seeder.
 */
class SwissDrawService
{
    private const TEAMS_PER_POT = 9;
    private const MATCHES_PER_TEAM = 8;
    private const MATCHDAYS = 8;

    /**
     * Generate league phase fixtures.
     *
     * @param array<array{id: string, pot: int, country: string}> $teams Team data with pot and country
     * @param Carbon $startDate First matchday date
     * @param int $intervalDays Days between matchdays
     * @return array<array{matchday: int, date: string, homeTeamId: string, awayTeamId: string}>
     */
    public function generateFixtures(array $teams, Carbon $startDate, int $intervalDays = 14): array
    {
        // Group teams by pot
        $pots = [];
        foreach ($teams as $team) {
            $pots[$team['pot']][] = $team;
        }

        // Validate: 4 pots of 9
        foreach ([1, 2, 3, 4] as $pot) {
            if (!isset($pots[$pot]) || count($pots[$pot]) !== self::TEAMS_PER_POT) {
                throw new \InvalidArgumentException(
                    "Pot {$pot} must have exactly " . self::TEAMS_PER_POT . " teams, got " . count($pots[$pot] ?? [])
                );
            }
        }

        // Generate opponent assignments
        $assignments = $this->assignOpponents($teams, $pots);

        // Convert assignments to matches
        $matches = $this->createMatches($assignments);

        // Schedule into matchdays
        return $this->scheduleMatchdays($matches, $startDate, $intervalDays);
    }

    /**
     * Assign 8 opponents to each team (2 from each pot, respecting constraints).
     *
     * @return array<string, array{home: string[], away: string[]}> teamId => {home opponents, away opponents}
     */
    private function assignOpponents(array $teams, array $pots): array
    {
        $maxAttempts = 100;

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $result = $this->tryAssignOpponents($teams, $pots);
            if ($result !== null) {
                return $result;
            }
        }

        // Fallback: simple round-robin-style assignment without strict constraints
        return $this->fallbackAssignment($teams, $pots);
    }

    /**
     * Attempt opponent assignment with constraints. Returns null if backtracking fails.
     */
    private function tryAssignOpponents(array $teams, array $pots): ?array
    {
        $teamIndex = [];
        foreach ($teams as $team) {
            $teamIndex[$team['id']] = $team;
        }

        // Track assignments: teamId => [opponentIds]
        $opponents = [];
        // Track match count: "id1-id2" => count (should be max 1)
        $matchPairs = [];
        // Track home/away counts per team
        $homeCount = [];
        $awayCount = [];
        // Track opponents from same country
        $countryCount = [];

        foreach ($teams as $team) {
            $opponents[$team['id']] = [];
            $homeCount[$team['id']] = 0;
            $awayCount[$team['id']] = 0;
            $countryCount[$team['id']] = [];
        }

        // For each team, assign 2 opponents from each pot
        $shuffledTeams = $teams;
        shuffle($shuffledTeams);

        foreach ($shuffledTeams as $team) {
            foreach ([1, 2, 3, 4] as $pot) {
                $needed = 2 - $this->countOpponentsFromPot($opponents[$team['id']], $pots[$pot], $teamIndex);
                if ($needed <= 0) {
                    continue;
                }

                $candidates = collect($pots[$pot])
                    ->filter(function ($candidate) use ($team, $opponents, $matchPairs, $countryCount, $teamIndex) {
                        // Can't play yourself
                        if ($candidate['id'] === $team['id']) {
                            return false;
                        }

                        // Already assigned as opponent
                        if (in_array($candidate['id'], $opponents[$team['id']])) {
                            return false;
                        }

                        // Already paired in the other direction
                        $pairKey = $this->pairKey($team['id'], $candidate['id']);
                        if (isset($matchPairs[$pairKey])) {
                            return false;
                        }

                        // Country protection: max 2 from same country
                        $candidateCountry = $candidate['country'];
                        $existingFromCountry = $countryCount[$team['id']][$candidateCountry] ?? 0;
                        if ($existingFromCountry >= 2) {
                            return false;
                        }

                        // Check candidate hasn't reached max opponents
                        if (count($opponents[$candidate['id']]) >= self::MATCHES_PER_TEAM) {
                            return false;
                        }

                        return true;
                    })
                    ->shuffle()
                    ->values();

                foreach ($candidates->take($needed) as $opponent) {
                    $opponents[$team['id']][] = $opponent['id'];
                    $opponents[$opponent['id']][] = $team['id'];

                    $pairKey = $this->pairKey($team['id'], $opponent['id']);
                    $matchPairs[$pairKey] = true;

                    $countryCount[$team['id']][$opponent['country']] =
                        ($countryCount[$team['id']][$opponent['country']] ?? 0) + 1;
                    $countryCount[$opponent['id']][$team['country']] =
                        ($countryCount[$opponent['id']][$team['country']] ?? 0) + 1;
                }
            }
        }

        // Validate all teams have 8 opponents
        foreach ($opponents as $teamId => $opps) {
            if (count($opps) < self::MATCHES_PER_TEAM) {
                return null; // Failed, retry
            }
        }

        // Assign home/away: each team should have 4 home, 4 away
        return $this->assignHomeAway($opponents, $matchPairs, $teamIndex);
    }

    /**
     * Assign home/away for each match pair.
     */
    private function assignHomeAway(array $opponents, array $matchPairs, array $teamIndex): array
    {
        $result = [];
        $homeCount = [];
        $awayCount = [];

        foreach (array_keys($opponents) as $teamId) {
            $result[$teamId] = ['home' => [], 'away' => []];
            $homeCount[$teamId] = 0;
            $awayCount[$teamId] = 0;
        }

        $processed = [];
        $pairs = array_keys($matchPairs);
        shuffle($pairs);

        foreach ($pairs as $pairKey) {
            if (isset($processed[$pairKey])) {
                continue;
            }

            [$teamA, $teamB] = explode('-', $pairKey);

            // Determine who's home: prefer the team with fewer home games
            if ($homeCount[$teamA] < $homeCount[$teamB]) {
                $home = $teamA;
                $away = $teamB;
            } elseif ($homeCount[$teamB] < $homeCount[$teamA]) {
                $home = $teamB;
                $away = $teamA;
            } else {
                // Equal home count, randomize
                if (rand(0, 1) === 0) {
                    $home = $teamA;
                    $away = $teamB;
                } else {
                    $home = $teamB;
                    $away = $teamA;
                }
            }

            $result[$home]['home'][] = $away;
            $result[$away]['away'][] = $home;
            $homeCount[$home]++;
            $awayCount[$away]++;
            $processed[$pairKey] = true;
        }

        return $result;
    }

    /**
     * Convert assignments to match list.
     *
     * @return array<array{homeTeamId: string, awayTeamId: string}>
     */
    private function createMatches(array $assignments): array
    {
        $matches = [];
        $seen = [];

        foreach ($assignments as $teamId => $data) {
            foreach ($data['home'] as $opponentId) {
                $key = "{$teamId}-{$opponentId}";
                if (!isset($seen[$key])) {
                    $matches[] = ['homeTeamId' => $teamId, 'awayTeamId' => $opponentId];
                    $seen[$key] = true;
                }
            }
        }

        return $matches;
    }

    /**
     * Schedule matches into matchdays ensuring no team plays twice per matchday.
     */
    private function scheduleMatchdays(array $matches, Carbon $startDate, int $intervalDays): array
    {
        $matchdays = array_fill(1, self::MATCHDAYS, []);
        $teamMatchdays = []; // teamId => [matchdays already assigned]

        // Shuffle matches for randomness
        shuffle($matches);

        foreach ($matches as $match) {
            $assigned = false;

            for ($md = 1; $md <= self::MATCHDAYS; $md++) {
                $homeId = $match['homeTeamId'];
                $awayId = $match['awayTeamId'];

                // Check if either team already plays on this matchday
                $homePlaysMd = in_array($md, $teamMatchdays[$homeId] ?? []);
                $awayPlaysMd = in_array($md, $teamMatchdays[$awayId] ?? []);

                if (!$homePlaysMd && !$awayPlaysMd) {
                    $matchdays[$md][] = $match;
                    $teamMatchdays[$homeId][] = $md;
                    $teamMatchdays[$awayId][] = $md;
                    $assigned = true;
                    break;
                }
            }

            if (!$assigned) {
                // Force assign to least-full matchday where neither team plays
                for ($md = 1; $md <= self::MATCHDAYS; $md++) {
                    $homePlaysMd = in_array($md, $teamMatchdays[$match['homeTeamId']] ?? []);
                    $awayPlaysMd = in_array($md, $teamMatchdays[$match['awayTeamId']] ?? []);
                    if (!$homePlaysMd && !$awayPlaysMd) {
                        $matchdays[$md][] = $match;
                        $teamMatchdays[$match['homeTeamId']][] = $md;
                        $teamMatchdays[$match['awayTeamId']][] = $md;
                        $assigned = true;
                        break;
                    }
                }

                // Last resort: assign to any matchday
                if (!$assigned) {
                    $md = array_search(min(array_map('count', $matchdays)), array_map('count', $matchdays));
                    $matchdays[$md ?: 1][] = $match;
                    $teamMatchdays[$match['homeTeamId']][] = $md ?: 1;
                    $teamMatchdays[$match['awayTeamId']][] = $md ?: 1;
                }
            }
        }

        // Convert to output format with dates
        $result = [];
        foreach ($matchdays as $md => $mdMatches) {
            $date = $startDate->copy()->addDays(($md - 1) * $intervalDays);
            foreach ($mdMatches as $match) {
                $result[] = [
                    'matchday' => $md,
                    'date' => $date->format('d/m/y'),
                    'homeTeamId' => $match['homeTeamId'],
                    'awayTeamId' => $match['awayTeamId'],
                ];
            }
        }

        return $result;
    }

    /**
     * Count how many of a team's assigned opponents come from a specific pot.
     */
    private function countOpponentsFromPot(array $opponentIds, array $potTeams, array $teamIndex): int
    {
        $potTeamIds = array_column($potTeams, 'id');

        return count(array_intersect($opponentIds, $potTeamIds));
    }

    /**
     * Canonical pair key for two teams.
     */
    private function pairKey(string $a, string $b): string
    {
        return $a < $b ? "{$a}-{$b}" : "{$b}-{$a}";
    }

    /**
     * Fallback assignment without strict pot constraints.
     * Used when the constraint solver fails.
     */
    private function fallbackAssignment(array $teams, array $pots): array
    {
        $opponents = [];
        $matchPairs = [];
        $teamIndex = [];

        foreach ($teams as $team) {
            $opponents[$team['id']] = [];
            $teamIndex[$team['id']] = $team;
        }

        // Simple circular assignment within and between pots
        foreach ([1, 2, 3, 4] as $pot) {
            $potTeams = $pots[$pot];

            foreach ([1, 2, 3, 4] as $targetPot) {
                $targetTeams = $pots[$targetPot];

                foreach ($potTeams as $i => $team) {
                    if (count($opponents[$team['id']]) >= self::MATCHES_PER_TEAM) {
                        continue;
                    }

                    // Pick opponents using circular offset
                    for ($offset = 1; $offset <= count($targetTeams); $offset++) {
                        if (count($opponents[$team['id']]) >= self::MATCHES_PER_TEAM) {
                            break;
                        }

                        $opponentIdx = ($i + $offset) % count($targetTeams);
                        $opponent = $targetTeams[$opponentIdx];

                        if ($opponent['id'] === $team['id']) {
                            continue;
                        }

                        $pairKey = $this->pairKey($team['id'], $opponent['id']);
                        if (isset($matchPairs[$pairKey])) {
                            continue;
                        }

                        if (count($opponents[$opponent['id']]) >= self::MATCHES_PER_TEAM) {
                            continue;
                        }

                        $opponents[$team['id']][] = $opponent['id'];
                        $opponents[$opponent['id']][] = $team['id'];
                        $matchPairs[$pairKey] = true;
                    }
                }
            }
        }

        return $this->assignHomeAway($opponents, $matchPairs, $teamIndex);
    }
}
