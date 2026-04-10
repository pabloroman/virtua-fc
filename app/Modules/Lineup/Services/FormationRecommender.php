<?php

namespace App\Modules\Lineup\Services;

use App\Modules\Lineup\Enums\Formation;
use App\Support\PositionSlotMapper;
use Illuminate\Support\Collection;

class FormationRecommender
{
    /**
     * Compute the best XI for a specific formation using a staged algorithm.
     *
     *   Pass 0 — Manual pins: place players the caller explicitly pinned to
     *                         specific slots (e.g. user drag-drops). These
     *                         slots are locked for the rest of the algorithm.
     *   Pass 1 — Primary:     place players whose PRIMARY matches a slot (compat 100).
     *   Pass 2 — Secondary:   for still-empty slots, place unused players whose
     *                         SECONDARY matches (compat 100).
     *   Pass 3 — Swap:        for still-empty slots, move a primary-pass assignee
     *                         to the empty slot via his secondary, freeing his
     *                         original slot for a less versatile unused player.
     *   Pass 4 — Weighted:    best-available fallback for slots that no natural
     *                         fit can cover.
     *
     * This is the single public entry point other services should use to
     * resolve "given these players and this formation, where does each go?".
     *
     * @param  Collection  $players  Eligible GamePlayer models (or equivalent arrays).
     * @param  array<int|string, string>  $manualAssignments  [slotId => playerId] pins.
     * @return array<array{slot: array, player: array|null, compatibility: int, effectiveRating: int}>
     */
    public function bestXIFor(Formation $formation, Collection $players, array $manualAssignments = []): array
    {
        $preComputed = $this->precomputePlayers($players);

        return $this->findBestXI($formation->pitchSlots(), collect($preComputed), $manualAssignments);
    }

    /**
     * Get the single best formation recommendation for a squad.
     *
     * Evaluates every formation by running the same staged algorithm and
     * picking the one with the highest coverage score.
     */
    public function getBestFormation(Collection $players): Formation
    {
        // Pre-compute player data once (avoids ~40,000 accessor calls per batch).
        $preComputed = $this->precomputePlayers($players);
        $players = collect($preComputed);

        $bestFormation = Formation::F_4_3_3;
        $bestScore = -1;

        foreach (Formation::cases() as $formation) {
            $slots = $formation->pitchSlots();
            $bestXI = $this->findBestXI($slots, $players);
            $coverage = $this->calculateCoverage($bestXI);
            $score = $this->calculateFormationScore($bestXI, $coverage);

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestFormation = $formation;
            }
        }

        return $bestFormation;
    }

    /**
     * Normalize a Collection of GamePlayer models (or array-shaped records)
     * into the lightweight array shape that findBestXI consumes.
     *
     * @return array<int, array{id: string, name: string, position: string, secondary_positions: array|null, overall_score: int}>
     */
    private function precomputePlayers(Collection $players): array
    {
        return $players->map(fn ($p) => [
            'id' => $p->id ?? $p['id'],
            'name' => $p->name ?? $p['name'],
            'position' => $p->position ?? $p['position'],
            'secondary_positions' => $p->secondary_positions ?? $p['secondary_positions'] ?? null,
            'overall_score' => $p->overall_score ?? $p['overall_score'] ?? 0,
        ])->values()->all();
    }

    /**
     * Run the four-pass algorithm against a formation's slots and a
     * pre-computed player list, honoring any manual pins up front.
     *
     * @param  array<int|string, string>  $manualAssignments  [slotId => playerId]
     * @return array<array{slot: array, player: array|null, compatibility: int, effectiveRating: int}>
     */
    private function findBestXI(array $slots, Collection $players, array $manualAssignments = []): array
    {
        // Sort players by overall score DESC once; all passes walk this order.
        $sortedPlayers = $players->sortByDesc(fn ($p) => $p['overall_score'] ?? 0)->values()->all();

        // Sort slots by specificity: GK first, then slots with fewer compatible
        // positions. This prevents the cascade problem where a high-rated
        // versatile player eats a rare slot that a less versatile specialist
        // would need — GK and rare slots get locked in first.
        $sortedSlots = collect($slots)->sortBy(function ($slot) {
            if ($slot['label'] === 'GK') {
                return 0;
            }
            return count(PositionSlotMapper::getCompatiblePositions($slot['label']));
        })->values()->all();

        // Result map keyed by slot id. The `pass` field tracks how each
        // assignment happened so Pass 3 can identify genuine primary
        // assignees (valid swap donors) vs secondary/manual/fallback fillers.
        $assigned = [];
        foreach ($slots as $slot) {
            $assigned[$slot['id']] = [
                'slot' => $slot,
                'player' => null,
                'compatibility' => 0,
                'effectiveRating' => 0,
                'pass' => null,
            ];
        }
        $usedPlayerIds = [];

        $this->applyManualPins($manualAssignments, $sortedPlayers, $assigned, $usedPlayerIds);
        $this->fillByPrimary($sortedSlots, $sortedPlayers, $assigned, $usedPlayerIds);
        $this->fillBySecondary($sortedSlots, $sortedPlayers, $assigned, $usedPlayerIds);
        $this->trySwapFill($sortedSlots, $sortedPlayers, $assigned, $usedPlayerIds);
        $this->fillByWeighted($sortedSlots, $sortedPlayers, $assigned, $usedPlayerIds);
        $this->fillByForceAssignment($sortedSlots, $sortedPlayers, $assigned, $usedPlayerIds);

        // Strip internal-only `pass` field and return sorted by original slot id.
        $result = [];
        foreach ($assigned as $row) {
            unset($row['pass']);
            $result[] = $row;
        }
        usort($result, fn ($a, $b) => $a['slot']['id'] <=> $b['slot']['id']);

        return $result;
    }

    /**
     * Pass 0 — honor caller-provided slot pins. Manual assignments are the
     * user's explicit intent; we never second-guess them. Invalid pins
     * (unknown slot id, player not in the squad) are silently ignored.
     */
    private function applyManualPins(array $manualAssignments, array $sortedPlayers, array &$assigned, array &$usedPlayerIds): void
    {
        foreach ($manualAssignments as $slotId => $playerId) {
            if (! isset($assigned[$slotId]) || $assigned[$slotId]['player'] !== null) {
                continue;
            }
            $player = null;
            foreach ($sortedPlayers as $p) {
                if ($p['id'] === $playerId) {
                    $player = $p;
                    break;
                }
            }
            if ($player === null || in_array($playerId, $usedPlayerIds, true)) {
                continue;
            }
            $slot = $assigned[$slotId]['slot'];
            $compat = PositionSlotMapper::getPlayerCompatibilityScore(
                $player['position'],
                $player['secondary_positions'] ?? null,
                $slot['label']
            );
            $this->assignPlayer($assigned, $slot, $player, $compat, 'manual');
            $usedPlayerIds[] = $playerId;
        }
    }

    /**
     * Pass 1 — place each player in a slot where their PRIMARY position gives
     * compatibility 100. Secondary positions are deliberately ignored here so
     * versatile players do not hog slots that less versatile specialists need.
     */
    private function fillByPrimary(array $sortedSlots, array $sortedPlayers, array &$assigned, array &$usedPlayerIds): void
    {
        foreach ($sortedSlots as $slot) {
            if ($assigned[$slot['id']]['player'] !== null) {
                continue;
            }
            foreach ($sortedPlayers as $player) {
                if (in_array($player['id'], $usedPlayerIds, true)) {
                    continue;
                }
                if (PositionSlotMapper::getCompatibilityScore($player['position'], $slot['label']) !== 100) {
                    continue;
                }
                $this->assignPlayer($assigned, $slot, $player, 100, 'primary');
                $usedPlayerIds[] = $player['id'];
                break;
            }
        }
    }

    /**
     * Pass 2 — fill still-empty slots with unused players whose SECONDARY
     * position gives compatibility 100 for that slot.
     */
    private function fillBySecondary(array $sortedSlots, array $sortedPlayers, array &$assigned, array &$usedPlayerIds): void
    {
        foreach ($sortedSlots as $slot) {
            if ($assigned[$slot['id']]['player'] !== null) {
                continue;
            }
            foreach ($sortedPlayers as $player) {
                if (in_array($player['id'], $usedPlayerIds, true)) {
                    continue;
                }
                foreach ($player['secondary_positions'] ?? [] as $secondary) {
                    if (PositionSlotMapper::getCompatibilityScore($secondary, $slot['label']) === 100) {
                        $this->assignPlayer($assigned, $slot, $player, 100, 'secondary');
                        $usedPlayerIds[] = $player['id'];
                        continue 3; // next slot
                    }
                }
            }
        }
    }

    /**
     * Pass 3 — for still-empty slots, see if an already-assigned primary-pass
     * player can move here via one of his secondaries, freeing his original
     * slot for a less versatile unused player whose primary fits there.
     *
     * Only Pass-1 assignees are considered as donors. Pass-0 (manual),
     * Pass-2 (secondary), and Pass-4 (fallback) assignees are deliberately
     * excluded — moving them would either override user intent or fail to
     * open up any new primary slot.
     */
    private function trySwapFill(array $sortedSlots, array $sortedPlayers, array &$assigned, array &$usedPlayerIds): void
    {
        foreach ($sortedSlots as $emptySlot) {
            if ($assigned[$emptySlot['id']]['player'] !== null) {
                continue;
            }

            foreach ($assigned as $donor) {
                if ($donor['pass'] !== 'primary' || $donor['player'] === null) {
                    continue;
                }

                // Find the donor's full player record for secondary_positions lookup.
                $donorPlayer = null;
                foreach ($sortedPlayers as $p) {
                    if ($p['id'] === $donor['player']['id']) {
                        $donorPlayer = $p;
                        break;
                    }
                }
                if ($donorPlayer === null || empty($donorPlayer['secondary_positions'])) {
                    continue;
                }

                // Can the donor cover the empty slot via any of his secondaries?
                $donorFits = false;
                foreach ($donorPlayer['secondary_positions'] as $sec) {
                    if (PositionSlotMapper::getCompatibilityScore($sec, $emptySlot['label']) === 100) {
                        $donorFits = true;
                        break;
                    }
                }
                if (! $donorFits) {
                    continue;
                }

                // Find an unused replacement whose PRIMARY fits the donor's current slot.
                $donorSlot = $donor['slot'];
                $replacement = null;
                foreach ($sortedPlayers as $candidate) {
                    if (in_array($candidate['id'], $usedPlayerIds, true)) {
                        continue;
                    }
                    if (PositionSlotMapper::getCompatibilityScore($candidate['position'], $donorSlot['label']) === 100) {
                        $replacement = $candidate;
                        break;
                    }
                }
                if ($replacement === null) {
                    continue;
                }

                // Execute the swap:
                //   donor       → emptySlot (marked 'swap', not a donor for later iterations)
                //   replacement → donorSlot (marked 'primary', may become a donor)
                $this->assignPlayer($assigned, $emptySlot, $donorPlayer, 100, 'swap');
                $this->assignPlayer($assigned, $donorSlot, $replacement, 100, 'primary');
                $usedPlayerIds[] = $replacement['id'];
                break; // this empty slot is filled; move to the next one
            }
        }
    }

    /**
     * Pass 4 — weighted best-available fallback for any slot still empty
     * (squad genuinely lacks a natural fit for this slot). Keeps the
     * historical 70% effective rating + 30% compatibility scoring so
     * calculateFormationScore() still has data to compare formations.
     */
    private function fillByWeighted(array $sortedSlots, array $sortedPlayers, array &$assigned, array &$usedPlayerIds): void
    {
        foreach ($sortedSlots as $slot) {
            if ($assigned[$slot['id']]['player'] !== null) {
                continue;
            }

            $bestPlayer = null;
            $bestCompat = 0;
            $bestScore = -1;

            foreach ($sortedPlayers as $player) {
                if (in_array($player['id'], $usedPlayerIds, true)) {
                    continue;
                }
                $compat = PositionSlotMapper::getPlayerCompatibilityScore(
                    $player['position'],
                    $player['secondary_positions'] ?? null,
                    $slot['label']
                );
                if ($compat === 0) {
                    continue;
                }
                $effective = PositionSlotMapper::getEffectiveRatingFromCompatibility($player['overall_score'], $compat);
                $weighted = ($effective * 0.7) + ($compat * 0.3);
                if ($weighted > $bestScore) {
                    $bestScore = $weighted;
                    $bestCompat = $compat;
                    $bestPlayer = $player;
                }
            }

            if ($bestPlayer !== null) {
                $this->assignPlayer($assigned, $slot, $bestPlayer, $bestCompat, 'fallback');
                $usedPlayerIds[] = $bestPlayer['id'];
            }
        }
    }

    /**
     * Pass 5 — force-place any remaining unused players into any remaining
     * empty slots, regardless of compatibility. Mirrors the "second pass"
     * behavior of the deleted client-side `slot-assignment.js` module.
     *
     * This is intentionally ugly: a Centre-Forward can end up in a CM slot
     * with compat 0, and the UI will show a bright red "poor fit" badge.
     * That is strictly better than dropping a selected player off the pitch
     * entirely — which is what happens if the algorithm ends with 10 placed
     * and 1 orphaned (the "ghost selected player" bug).
     *
     * Triggered in practice when the squad + formation combination leaves
     * an unused player whose primary and secondaries all give compat 0 for
     * every still-empty slot (e.g. a pure Centre-Forward with no secondaries
     * after switching 4-3-3 → 4-4-2 and the two CF slots are already taken).
     */
    private function fillByForceAssignment(array $sortedSlots, array $sortedPlayers, array &$assigned, array &$usedPlayerIds): void
    {
        foreach ($sortedSlots as $slot) {
            if ($assigned[$slot['id']]['player'] !== null) {
                continue;
            }

            foreach ($sortedPlayers as $player) {
                if (in_array($player['id'], $usedPlayerIds, true)) {
                    continue;
                }
                $compat = PositionSlotMapper::getPlayerCompatibilityScore(
                    $player['position'],
                    $player['secondary_positions'] ?? null,
                    $slot['label']
                );
                $this->assignPlayer($assigned, $slot, $player, $compat, 'force');
                $usedPlayerIds[] = $player['id'];
                break;
            }
        }
    }

    /**
     * Record a player assignment in the $assigned map.
     */
    private function assignPlayer(array &$assigned, array $slot, array $player, int $compatibility, string $pass): void
    {
        $effective = PositionSlotMapper::getEffectiveRatingFromCompatibility($player['overall_score'], $compatibility);
        $assigned[$slot['id']] = [
            'slot' => $slot,
            'player' => [
                'id' => $player['id'],
                'name' => $player['name'],
                'position' => $player['position'],
                'overallScore' => $player['overall_score'],
                'compatibility' => $compatibility,
                'effectiveRating' => $effective,
            ],
            'compatibility' => $compatibility,
            'effectiveRating' => $effective,
            'pass' => $pass,
        ];
    }

    /**
     * Calculate slot coverage statistics.
     */
    private function calculateCoverage(array $bestXI): array
    {
        $total = count($bestXI);
        $filled = 0;
        $natural = 0;
        $good = 0;
        $acceptable = 0;
        $poor = 0;

        foreach ($bestXI as $assignment) {
            if ($assignment['player']) {
                $filled++;
                $compat = $assignment['compatibility'];

                if ($compat >= 100) $natural++;
                elseif ($compat >= 60) $good++;
                elseif ($compat >= 40) $acceptable++;
                else $poor++;
            }
        }

        return [
            'total' => $total,
            'filled' => $filled,
            'natural' => $natural,
            'good' => $good,
            'acceptable' => $acceptable,
            'poor' => $poor,
            'naturalPercent' => $total > 0 ? round(($natural / $total) * 100) : 0,
            'coveragePercent' => $total > 0 ? round(($filled / $total) * 100) : 0,
        ];
    }

    /**
     * Calculate overall formation score (0-100).
     */
    private function calculateFormationScore(array $bestXI, array $coverage): int
    {
        // Base score from average effective rating
        $totalEffective = array_sum(array_column($bestXI, 'effectiveRating'));
        $avgEffective = count($bestXI) > 0 ? $totalEffective / count($bestXI) : 0;

        // Bonus for natural positions
        $naturalBonus = $coverage['natural'] * 3;

        // Penalty for poor fits
        $poorPenalty = $coverage['poor'] * 5;

        // Penalty for unfilled slots
        $unfilledPenalty = ($coverage['total'] - $coverage['filled']) * 10;

        $score = $avgEffective + $naturalBonus - $poorPenalty - $unfilledPenalty;

        return (int) max(0, min(100, $score));
    }
}
