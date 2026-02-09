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
     * Retries the full pipeline (opponent assignment + scheduling) since some
     * valid opponent assignments produce match graphs that are hard to schedule
     * into conflict-free matchdays.
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

        // Retry full pipeline: different assignments produce different match graphs
        $maxAttempts = 200;

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $assignments = $this->tryAssignOpponents($teams, $pots);
            if ($assignments === null) {
                continue;
            }

            $matches = $this->createMatches($assignments);
            $schedule = $this->tryScheduleMatchdays($matches);

            if ($schedule !== null) {
                return $this->formatSchedule($schedule, $startDate, $intervalDays);
            }
        }

        // Fallback: relaxed pot constraints
        $assignments = $this->fallbackAssignment($teams, $pots);
        $matches = $this->createMatches($assignments);
        $schedule = $this->tryScheduleMatchdays($matches);

        if ($schedule !== null) {
            return $this->formatSchedule($schedule, $startDate, $intervalDays);
        }

        throw new \RuntimeException('Failed to generate valid Swiss format fixtures');
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
        // Track match pairs: "id1|id2" => true
        $matchPairs = [];
        // Track opponents from same country
        $countryCount = [];

        foreach ($teams as $team) {
            $opponents[$team['id']] = [];
            $countryCount[$team['id']] = [];
        }

        // For each team, assign 2 opponents from each pot
        $shuffledTeams = $teams;
        shuffle($shuffledTeams);

        foreach ($shuffledTeams as $team) {
            foreach ([1, 2, 3, 4] as $pot) {
                // Stop if team already has all 8 opponents (from bidirectional assignments)
                $remaining = self::MATCHES_PER_TEAM - count($opponents[$team['id']]);
                if ($remaining <= 0) {
                    break;
                }

                $needed = 2 - $this->countOpponentsFromPot($opponents[$team['id']], $pots[$pot], $teamIndex);
                if ($needed <= 0) {
                    continue;
                }

                // Don't exceed 8 total opponents
                $needed = min($needed, $remaining);

                $candidates = collect($pots[$pot])
                    ->filter(function ($candidate) use ($team, $opponents, $matchPairs, $countryCount) {
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

        // Validate all teams have exactly 8 opponents
        foreach ($opponents as $teamId => $opps) {
            if (count($opps) !== self::MATCHES_PER_TEAM) {
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

            [$teamA, $teamB] = explode('|', $pairKey);

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
                $key = "{$teamId}|{$opponentId}";
                if (!isset($seen[$key])) {
                    $matches[] = ['homeTeamId' => $teamId, 'awayTeamId' => $opponentId];
                    $seen[$key] = true;
                }
            }
        }

        return $matches;
    }

    /**
     * Schedule matches by extracting one perfect matching per round.
     *
     * Uses greedy matching + augmenting paths to find perfect matchings.
     * Different random greedy orderings are tried since some extraction
     * sequences leave remaining graphs without perfect matchings.
     *
     * @return array<int, array>|null Matchday => matches, or null on failure
     */
    private function tryScheduleMatchdays(array $matches): ?array
    {
        // Build team → edge indices adjacency (immutable across retries)
        $teamEdges = [];
        foreach ($matches as $i => $match) {
            $teamEdges[$match['homeTeamId']][] = $i;
            $teamEdges[$match['awayTeamId']][] = $i;
        }

        $allTeams = array_keys($teamEdges);

        // Retry with different random greedy orderings
        for ($attempt = 0; $attempt < 50; $attempt++) {
            $available = array_fill(0, count($matches), true);
            $roundMatches = array_fill(1, self::MATCHDAYS, []);
            $success = true;

            for ($round = 1; $round <= self::MATCHDAYS; $round++) {
                $matching = $this->findPerfectMatching($matches, $teamEdges, $available, $allTeams);

                if ($matching === null) {
                    $success = false;
                    break;
                }

                foreach ($matching as $edgeIdx) {
                    $roundMatches[$round][] = $matches[$edgeIdx];
                    $available[$edgeIdx] = false;
                }
            }

            if ($success) {
                return $roundMatches;
            }
        }

        return null;
    }

    /**
     * Find a perfect matching using greedy + augmenting paths.
     *
     * @return int[]|null Array of edge indices forming the matching, or null on failure
     */
    private function findPerfectMatching(
        array $matches,
        array $teamEdges,
        array $available,
        array $allTeams
    ): ?array {
        // matchOf[teamId] = edgeIdx this team is matched on, or absent if free
        $matchOf = [];

        // Greedy phase: assign random available edges
        $edgeIndices = array_keys(array_filter($available));
        shuffle($edgeIndices);

        foreach ($edgeIndices as $edgeIdx) {
            $home = $matches[$edgeIdx]['homeTeamId'];
            $away = $matches[$edgeIdx]['awayTeamId'];

            if (!isset($matchOf[$home]) && !isset($matchOf[$away])) {
                $matchOf[$home] = $edgeIdx;
                $matchOf[$away] = $edgeIdx;
            }
        }

        // Augmenting phase: find augmenting paths for each free team
        foreach ($allTeams as $team) {
            if (isset($matchOf[$team])) {
                continue;
            }

            $path = $this->findAugmentingPath($team, $matches, $teamEdges, $available, $matchOf);

            if ($path === null) {
                return null;
            }

            // Flip matching along augmenting path
            // Path edges alternate: unmatched (pos 0), matched (pos 1), unmatched (pos 2), ...
            // After flip: all even positions become matched, odd positions become unmatched
            foreach ($path as $i => $edgeIdx) {
                $home = $matches[$edgeIdx]['homeTeamId'];
                $away = $matches[$edgeIdx]['awayTeamId'];

                if ($i % 2 === 0) {
                    // Was unmatched → now matched
                    $matchOf[$home] = $edgeIdx;
                    $matchOf[$away] = $edgeIdx;
                }
                // Odd positions: was matched → now unmatched.
                // The vertices are re-matched by adjacent even-position edges.
            }
        }

        // Extract unique edge indices from the matching
        return array_values(array_unique(array_values($matchOf)));
    }

    /**
     * BFS to find an augmenting path from a free vertex.
     *
     * An augmenting path alternates: free → (unmatched edge) → matched vertex →
     * (matched edge) → vertex → (unmatched edge) → ... → free vertex.
     *
     * @return int[]|null Edge indices forming the path, or null if none found
     */
    private function findAugmentingPath(
        string $startTeam,
        array $matches,
        array $teamEdges,
        array $available,
        array $matchOf
    ): ?array {
        $visited = [$startTeam => true];
        // parent[team] = [prevTeam, edgeIdx] used to reach this team
        $parent = [$startTeam => null];
        $queue = [$startTeam];

        while (!empty($queue)) {
            $v = array_shift($queue);

            // Explore all available unmatched edges from v
            foreach ($teamEdges[$v] ?? [] as $edgeIdx) {
                if (!$available[$edgeIdx]) {
                    continue;
                }

                // Skip if this IS v's matched edge (we want unmatched edges)
                if (isset($matchOf[$v]) && $matchOf[$v] === $edgeIdx) {
                    continue;
                }

                $w = $matches[$edgeIdx]['homeTeamId'] === $v
                    ? $matches[$edgeIdx]['awayTeamId']
                    : $matches[$edgeIdx]['homeTeamId'];

                if (isset($visited[$w])) {
                    continue;
                }

                $visited[$w] = true;
                $parent[$w] = [$v, $edgeIdx];

                // If w is free, we found an augmenting path
                if (!isset($matchOf[$w])) {
                    return $this->traceAugmentingPath($parent, $w);
                }

                // w is matched — follow its matched edge to the partner
                $matchedEdge = $matchOf[$w];
                $partner = $matches[$matchedEdge]['homeTeamId'] === $w
                    ? $matches[$matchedEdge]['awayTeamId']
                    : $matches[$matchedEdge]['homeTeamId'];

                if (!isset($visited[$partner])) {
                    $visited[$partner] = true;
                    $parent[$partner] = [$w, $matchedEdge];
                    $queue[] = $partner;
                }
            }
        }

        return null;
    }

    /**
     * Trace augmenting path from end vertex back to start using parent pointers.
     *
     * @return int[] Edge indices in forward order
     */
    private function traceAugmentingPath(array $parent, string $end): array
    {
        $edges = [];
        $current = $end;

        while ($parent[$current] !== null) {
            [$prev, $edgeIdx] = $parent[$current];
            $edges[] = $edgeIdx;
            $current = $prev;
        }

        return array_reverse($edges);
    }

    /**
     * Format matchday schedule into output array with dates.
     */
    private function formatSchedule(array $schedule, Carbon $startDate, int $intervalDays): array
    {
        $result = [];

        foreach ($schedule as $md => $mdMatches) {
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
        return $a < $b ? "{$a}|{$b}" : "{$b}|{$a}";
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
