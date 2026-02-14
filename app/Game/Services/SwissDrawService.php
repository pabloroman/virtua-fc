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
 * Two-phase approach:
 * 1. Opponent assignment: pot-by-pot with bidirectional tracking
 * 2. Scheduling: extract perfect matchings (one per round) using augmenting paths
 */
class SwissDrawService
{
    private const TEAMS_PER_POT = 9;
    private const MATCHES_PER_TEAM = 8;
    private const MATCHDAYS = 8;

    /**
     * Generate league phase fixtures.
     *
     * @param array<array{id: string, pot: int, country: string}> $teams
     */
    public function generateFixtures(array $teams, Carbon $startDate, int $intervalDays = 14): array
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

        // Retry full pipeline: different random seeds produce different assignments
        for ($attempt = 0; $attempt < 200; $attempt++) {
            $matches = $this->tryAssignMatches($teams, $pots);
            if ($matches === null) {
                continue;
            }

            $schedule = $this->tryScheduleMatchdays($matches);
            if ($schedule !== null) {
                return $this->formatSchedule($schedule, $startDate, $intervalDays);
            }
        }

        throw new \RuntimeException('Failed to generate valid Swiss format fixtures');
    }

    /**
     * Try to assign opponents pot-by-pot. Returns 144 matches or null on failure.
     *
     * For each team, assigns 2 opponents from each pot. Assignments are bidirectional:
     * when A picks B, B also gets A. This means a team can accumulate opponents from
     * other teams' picks, so we guard against exceeding 8 total.
     *
     * @return array<array{homeTeamId: string, awayTeamId: string}>|null
     */
    private function tryAssignMatches(array $teams, array $pots): ?array
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
                    ->filter(function ($c) use ($team, $opponents, $paired, $countryCount) {
                        if ($c['id'] === $team['id']) return false;
                        if (count($opponents[$c['id']]) >= self::MATCHES_PER_TEAM) return false;

                        $key = $team['id'] < $c['id']
                            ? "{$team['id']}|{$c['id']}"
                            : "{$c['id']}|{$team['id']}";
                        if (isset($paired[$key])) return false;

                        if (($countryCount[$team['id']][$c['country']] ?? 0) >= 2) return false;

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

                    $key = $team['id'] < $c['id']
                        ? "{$team['id']}|{$c['id']}"
                        : "{$c['id']}|{$team['id']}";
                    $paired[$key] = true;
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

    private function formatSchedule(array $schedule, Carbon $startDate, int $intervalDays): array
    {
        $result = [];
        foreach ($schedule as $md => $mdMatches) {
            $date = $startDate->copy()->addDays(($md - 1) * $intervalDays);
            foreach ($mdMatches as $match) {
                $result[] = [
                    'matchday' => $md,
                    'date' => $date->format('Y-m-d'),
                    'homeTeamId' => $match['homeTeamId'],
                    'awayTeamId' => $match['awayTeamId'],
                ];
            }
        }
        return $result;
    }
}
