<?php

namespace App\Modules\Competition\Services;

use Illuminate\Support\Facades\Log;

/**
 * Generates league phase fixtures for Swiss format competitions.
 *
 * 36 teams in 4 pots of 9. Each team plays 8 matches (4 home, 4 away):
 * - 2 opponents from each pot (1 home, 1 away)
 * - Country protection: teams from the same country never face each other
 * - Diversity cap: max 2 opponents from any single foreign country
 *
 * Uses progressive constraint relaxation to guarantee fixture generation:
 * Level 0: Full constraints (country protection + diversity cap)
 * Level 1: No diversity cap, keep country protection
 * Level 2: No country protection, no diversity cap
 * Level 3: Circle-method fallback (mathematically guaranteed)
 *
 * Two-phase approach per level:
 * 1. Opponent assignment: pot-by-pot with bidirectional tracking
 * 2. Scheduling: extract perfect matchings (one per round) using augmenting paths
 */
class SwissDrawService
{
    private const TEAMS_PER_POT = 9;
    private const MATCHES_PER_TEAM = 8;
    private const MATCHDAYS = 8;

    /**
     * Generate league phase fixtures with progressive constraint relaxation.
     *
     * Never throws — falls back through increasingly relaxed constraint levels
     * until fixtures are produced. The circle-method fallback (Level 3) is
     * mathematically guaranteed to succeed.
     *
     * @param array<array{id: string, pot: int, country: string}> $teams
     * @param array<int, string> $matchdayDates Matchday number => ISO date (YYYY-MM-DD)
     */
    public function generateFixtures(array $teams, array $matchdayDates): array
    {
        $pots = [];
        foreach ($teams as $team) {
            $pots[$team['pot']][] = $team;
        }

        foreach ([1, 2, 3, 4] as $pot) {
            if (!isset($pots[$pot]) || count($pots[$pot]) !== self::TEAMS_PER_POT) {
                throw new \InvalidArgumentException(
                    "Pot {$pot} must have exactly " . self::TEAMS_PER_POT . " teams, got " . count($pots[$pot] ?? [])
                );
            }
        }

        // Level 0: Full constraints (country protection + diversity cap ≤ 2)
        $result = $this->tryLevel($teams, $pots, $matchdayDates, 500, true, 2);
        if ($result !== null) {
            return $result;
        }

        $distribution = $this->countryDistribution($pots);

        // Level 1: No diversity cap, keep country protection
        $this->logEscalation('Level 1 (no diversity cap)', $distribution);
        $result = $this->tryLevel($teams, $pots, $matchdayDates, 200, true, 0);
        if ($result !== null) {
            return $result;
        }

        // Level 2: No country protection, no diversity cap
        $this->logEscalation('Level 2 (no country protection)', $distribution);
        $result = $this->tryLevel($teams, $pots, $matchdayDates, 200, false, 0);
        if ($result !== null) {
            return $result;
        }

        // Level 3: Circle-method fallback (guaranteed)
        $this->logEscalation('Level 3 (circle-method fallback)', $distribution);

        return $this->generateCircleMethodFixtures($teams, $matchdayDates);
    }

    /**
     * Attempt fixture generation at a given constraint level.
     *
     * @return array|null Formatted fixtures or null if all attempts exhausted
     */
    private function tryLevel(array $teams, array $pots, array $matchdayDates, int $maxAttempts, bool $enforceCountryProtection, int $maxCountryOpponents): ?array
    {
        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $matches = $this->tryAssignMatches($teams, $pots, $enforceCountryProtection, $maxCountryOpponents);
            if ($matches === null) {
                continue;
            }

            $schedule = $this->tryScheduleMatchdays($matches);
            if ($schedule !== null) {
                return $this->formatSchedule($schedule, $matchdayDates);
            }
        }

        return null;
    }

    /**
     * Generate fixtures using the circle (polygon) method — guaranteed to succeed.
     *
     * 1. Fix teams[0], rotate teams[1..35] through 35 rounds, pick 8 rounds
     * 2. Each round produces 18 undirected pairings (no conflicts)
     * 3. Orient using Euler circuit for exactly 4H/4A per team
     * 4. Map directed edges back to their original round assignments
     *
     * Mathematical guarantee: circle method produces valid rounds (no double-booking).
     * Every team has degree 8 (even), so an Euler circuit exists and gives exactly
     * 4 home / 4 away.
     */
    private function generateCircleMethodFixtures(array $teams, array $matchdayDates): array
    {
        // Build a stable ordering of team IDs
        $teamIds = array_map(fn($t) => $t['id'], $teams);

        // Fix the first team, rotate the rest (circle method)
        $fixed = $teamIds[0];
        $rotating = array_slice($teamIds, 1); // 35 elements
        $n = count($teamIds); // 36

        // Generate all 35 possible rounds
        $allRounds = [];
        for ($round = 0; $round < $n - 1; $round++) {
            $roundPairings = [];

            // Fixed team pairs with rotating[0]
            $roundPairings[] = [$fixed, $rotating[0]];

            // Remaining pairs: rotating[i] pairs with rotating[n-1-i]
            $halfRotating = intdiv(count($rotating), 2);
            for ($i = 1; $i <= $halfRotating; $i++) {
                $roundPairings[] = [$rotating[$i], $rotating[$n - 1 - $i]];
            }

            $allRounds[] = $roundPairings;

            // Rotate: shift rotating array by 1 position
            $last = array_pop($rotating);
            array_unshift($rotating, $last);
        }

        // Pick 8 rounds randomly for variety
        $roundIndices = array_keys($allRounds);
        shuffle($roundIndices);
        $selectedRounds = array_slice($roundIndices, 0, self::MATCHDAYS);

        // Build undirected pairings with round tracking
        $paired = [];
        $roundOf = []; // pairKey => matchday (1-indexed)
        $matchday = 1;
        foreach ($selectedRounds as $roundIdx) {
            foreach ($allRounds[$roundIdx] as [$a, $b]) {
                $key = $this->pairKey($a, $b);
                $paired[$key] = true;
                $roundOf[$key] = $matchday;
            }
            $matchday++;
        }

        // Orient edges for balanced home/away using Euler circuit
        $directedMatches = $this->orientEdgesBalanced($paired);

        // Assign each match to its original round
        $schedule = array_fill(1, self::MATCHDAYS, []);
        foreach ($directedMatches as $match) {
            $key = $this->pairKey($match['homeTeamId'], $match['awayTeamId']);
            $schedule[$roundOf[$key]][] = $match;
        }

        return $this->formatSchedule($schedule, $matchdayDates);
    }

    /**
     * Per-pot country distribution for diagnostic logging.
     *
     * @return array<int, array<string, int>> Pot => [country => count]
     */
    private function countryDistribution(array $pots): array
    {
        $distribution = [];
        foreach ($pots as $pot => $teams) {
            $counts = [];
            foreach ($teams as $team) {
                $counts[$team['country']] = ($counts[$team['country']] ?? 0) + 1;
            }
            arsort($counts);
            $distribution[$pot] = $counts;
        }

        return $distribution;
    }

    /**
     * Log a constraint escalation warning. Gracefully skips if no app context (e.g. unit tests).
     */
    private function logEscalation(string $level, array $distribution): void
    {
        try {
            Log::warning("Swiss draw: escalating to {$level}", [
                'country_distribution' => $distribution,
            ]);
        } catch (\RuntimeException) {
            // No facade root (unit tests without Laravel container) — skip
        }
    }

    private function pairKey(string $a, string $b): string
    {
        return $a < $b ? "{$a}|{$b}" : "{$b}|{$a}";
    }

    // ──────────────────────────────────────────────────────────────────────
    // Opponent assignment
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Try to assign opponents pot-by-pot. Returns 144 matches or null on failure.
     *
     * For each team, assigns 2 opponents from each pot. Assignments are bidirectional:
     * when A picks B, B also gets A. This means a team can accumulate opponents from
     * other teams' picks, so we guard against exceeding 8 total.
     *
     * @param  bool  $enforceCountryProtection  When true, teams from the same country never face each other
     * @param  int   $maxCountryOpponents  Max opponents from any single foreign country (0 = no limit)
     * @return array<array{homeTeamId: string, awayTeamId: string}>|null
     */
    private function tryAssignMatches(array $teams, array $pots, bool $enforceCountryProtection = true, int $maxCountryOpponents = 2): ?array
    {
        $teamPot = [];
        foreach ($teams as $team) {
            $teamPot[$team['id']] = $team['pot'];
        }

        $opponents = [];    // teamId => [opponentId, ...]
        $potCount = [];     // teamId => [pot => count]
        $countryCount = []; // teamId => [country => count]
        $paired = [];       // "idA|idB" => true (canonical key, sorted)

        foreach ($teams as $team) {
            $opponents[$team['id']] = [];
            $potCount[$team['id']] = [1 => 0, 2 => 0, 3 => 0, 4 => 0];
            $countryCount[$team['id']] = [];
        }

        $shuffled = $teams;
        shuffle($shuffled);

        foreach ($shuffled as $team) {
            foreach ([1, 2, 3, 4] as $pot) {
                $remaining = self::MATCHES_PER_TEAM - count($opponents[$team['id']]);
                if ($remaining <= 0) {
                    break;
                }

                $needed = 2 - $potCount[$team['id']][$pot];
                if ($needed <= 0) {
                    continue;
                }
                $needed = min($needed, $remaining);

                $candidates = collect($pots[$pot])
                    ->filter(function ($c) use ($team, $opponents, $paired, $countryCount, $enforceCountryProtection, $maxCountryOpponents) {
                        if ($c['id'] === $team['id']) return false;
                        if (count($opponents[$c['id']]) >= self::MATCHES_PER_TEAM) return false;

                        if (isset($paired[$this->pairKey($team['id'], $c['id'])])) return false;

                        // Country protection (relaxable)
                        if ($enforceCountryProtection && $team['country'] === $c['country']) return false;

                        // Diversity cap (relaxable — 0 = no limit)
                        if ($maxCountryOpponents > 0) {
                            if (($countryCount[$team['id']][$c['country']] ?? 0) >= $maxCountryOpponents) return false;
                            if (($countryCount[$c['id']][$team['country']] ?? 0) >= $maxCountryOpponents) return false;
                        }

                        return true;
                    })
                    ->shuffle()
                    ->take($needed);

                foreach ($candidates as $c) {
                    $opponents[$team['id']][] = $c['id'];
                    $opponents[$c['id']][] = $team['id'];

                    $potCount[$team['id']][$c['pot']]++;
                    $potCount[$c['id']][$teamPot[$team['id']]]++;

                    $countryCount[$team['id']][$c['country']] = ($countryCount[$team['id']][$c['country']] ?? 0) + 1;
                    $countryCount[$c['id']][$team['country']] = ($countryCount[$c['id']][$team['country']] ?? 0) + 1;

                    $paired[$this->pairKey($team['id'], $c['id'])] = true;
                }
            }
        }

        // Validate all teams have exactly 8 opponents
        foreach ($opponents as $opps) {
            if (count($opps) !== self::MATCHES_PER_TEAM) {
                return null;
            }
        }

        // Orient pairings with exactly 4 home, 4 away per team using Euler circuit
        return $this->orientEdgesBalanced($paired);
    }

    /**
     * Orient undirected pairings into directed matches with exactly 4 home, 4 away per team.
     *
     * Uses Hierholzer's Euler circuit algorithm. Each team has even degree (8),
     * so Euler circuits exist. Orienting edges along the circuit direction gives
     * each team exactly degree/2 = 4 home and 4 away games.
     *
     * @param  array<string, true>  $paired  Canonical pair keys ("idA|idB" where idA < idB)
     * @return array<array{homeTeamId: string, awayTeamId: string}>
     */
    private function orientEdgesBalanced(array $paired): array
    {
        $adj = [];
        $edgeUsed = [];

        foreach (array_keys($paired) as $key) {
            [$a, $b] = explode('|', $key);
            $adj[$a][] = ['neighbor' => $b, 'key' => $key];
            $adj[$b][] = ['neighbor' => $a, 'key' => $key];
            $edgeUsed[$key] = false;
        }

        $adjPos = array_fill_keys(array_keys($adj), 0);
        $matches = [];

        // Process each connected component
        foreach (array_keys($adj) as $start) {
            $hasUnused = false;
            for ($i = $adjPos[$start]; $i < count($adj[$start]); $i++) {
                if (!$edgeUsed[$adj[$start][$i]['key']]) {
                    $hasUnused = true;
                    break;
                }
            }
            if (!$hasUnused) {
                continue;
            }

            // Hierholzer's algorithm (stack-based)
            $stack = [$start];
            $circuit = [];

            while (!empty($stack)) {
                $v = end($stack);
                $found = false;

                while ($adjPos[$v] < count($adj[$v])) {
                    $edge = $adj[$v][$adjPos[$v]];
                    $adjPos[$v]++;

                    if (!$edgeUsed[$edge['key']]) {
                        $edgeUsed[$edge['key']] = true;
                        $stack[] = $edge['neighbor'];
                        $found = true;
                        break;
                    }
                }

                if (!$found) {
                    $circuit[] = array_pop($stack);
                }
            }

            // Orient edges along the circuit: consecutive vertices define home→away
            for ($i = 0; $i < count($circuit) - 1; $i++) {
                $matches[] = [
                    'homeTeamId' => $circuit[$i],
                    'awayTeamId' => $circuit[$i + 1],
                ];
            }
        }

        return $matches;
    }

    // ──────────────────────────────────────────────────────────────────────
    // Scheduling: assign matches to rounds using perfect matching extraction
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Schedule 144 matches into 8 rounds of 18, with no team playing twice per round.
     *
     * Extracts one perfect matching per round using greedy + augmenting paths.
     * Retries with different random seeds since some extraction orders can fail.
     */
    private function tryScheduleMatchdays(array $matches): ?array
    {
        $teamEdges = [];
        foreach ($matches as $i => $match) {
            $teamEdges[$match['homeTeamId']][] = $i;
            $teamEdges[$match['awayTeamId']][] = $i;
        }
        $allTeams = array_keys($teamEdges);

        for ($attempt = 0; $attempt < 50; $attempt++) {
            $available = array_fill(0, count($matches), true);
            $schedule = array_fill(1, self::MATCHDAYS, []);
            $ok = true;

            for ($round = 1; $round <= self::MATCHDAYS; $round++) {
                $matching = $this->findPerfectMatching($matches, $teamEdges, $available, $allTeams);
                if ($matching === null) {
                    $ok = false;
                    break;
                }
                foreach ($matching as $idx) {
                    $schedule[$round][] = $matches[$idx];
                    $available[$idx] = false;
                }
            }

            if ($ok) {
                return $schedule;
            }
        }

        return null;
    }

    /**
     * Find a perfect matching (18 edges covering all 36 teams) in the available edges.
     *
     * Phase 1 — Greedy: pick random edges where both endpoints are free.
     * Phase 2 — Augment: for each still-free team, BFS for an augmenting path
     * (alternating unmatched→matched edges ending at another free team),
     * then flip the path to grow the matching by 1.
     */
    private function findPerfectMatching(array $matches, array $teamEdges, array $available, array $allTeams): ?array
    {
        $matchOf = []; // teamId => edgeIdx

        // Greedy
        $edges = array_keys(array_filter($available));
        shuffle($edges);
        foreach ($edges as $idx) {
            $h = $matches[$idx]['homeTeamId'];
            $a = $matches[$idx]['awayTeamId'];
            if (!isset($matchOf[$h]) && !isset($matchOf[$a])) {
                $matchOf[$h] = $idx;
                $matchOf[$a] = $idx;
            }
        }

        // Augment each free team
        foreach ($allTeams as $team) {
            if (isset($matchOf[$team])) continue;

            $visited = [$team => true];
            $parent = [$team => null];
            $queue = [$team];
            $end = null;

            while (!empty($queue) && $end === null) {
                $v = array_shift($queue);
                foreach ($teamEdges[$v] as $idx) {
                    if (!$available[$idx]) continue;
                    if (isset($matchOf[$v]) && $matchOf[$v] === $idx) continue;

                    $w = $matches[$idx]['homeTeamId'] === $v
                        ? $matches[$idx]['awayTeamId']
                        : $matches[$idx]['homeTeamId'];
                    if (isset($visited[$w])) continue;

                    $visited[$w] = true;
                    $parent[$w] = [$v, $idx];

                    if (!isset($matchOf[$w])) {
                        $end = $w;
                        break;
                    }

                    // Follow matched edge to partner
                    $mIdx = $matchOf[$w];
                    $partner = $matches[$mIdx]['homeTeamId'] === $w
                        ? $matches[$mIdx]['awayTeamId']
                        : $matches[$mIdx]['homeTeamId'];
                    if (!isset($visited[$partner])) {
                        $visited[$partner] = true;
                        $parent[$partner] = [$w, $mIdx];
                        $queue[] = $partner;
                    }
                }
            }

            if ($end === null) return null;

            // Trace and flip
            $path = [];
            $cur = $end;
            while ($parent[$cur] !== null) {
                $path[] = $parent[$cur][1];
                $cur = $parent[$cur][0];
            }

            for ($i = count($path) - 1; $i >= 0; $i--) {
                if ((count($path) - 1 - $i) % 2 === 0) {
                    $matchOf[$matches[$path[$i]]['homeTeamId']] = $path[$i];
                    $matchOf[$matches[$path[$i]]['awayTeamId']] = $path[$i];
                }
            }
        }

        return array_values(array_unique(array_values($matchOf)));
    }

    /**
     * @param array<int, array<array{homeTeamId: string, awayTeamId: string}>> $schedule
     * @param array<int, string> $matchdayDates Matchday number => ISO date (YYYY-MM-DD)
     */
    private function formatSchedule(array $schedule, array $matchdayDates): array
    {
        $result = [];
        foreach ($schedule as $md => $mdMatches) {
            foreach ($mdMatches as $match) {
                $result[] = [
                    'matchday' => $md,
                    'date' => $matchdayDates[$md],
                    'homeTeamId' => $match['homeTeamId'],
                    'awayTeamId' => $match['awayTeamId'],
                ];
            }
        }
        return $result;
    }
}
