<?php

namespace App\Modules\Lineup\Services;

use App\Modules\Lineup\Enums\Formation;
use App\Support\PositionSlotMapper;
use Illuminate\Support\Collection;

class FormationRecommender
{
    /**
     * Find the best XI for a formation.
     *
     * @return array<array{slot: array, player: array|null, compatibility: int, effectiveRating: int}>
     */
    private function findBestXI(array $slots, Collection $players): array
    {
        $assigned = [];
        $usedPlayerIds = [];

        // Sort slots by specificity (GK first, then positions with fewer compatible players)
        $sortedSlots = collect($slots)->sortBy(function ($slot) {
            $compatibleCount = count(PositionSlotMapper::getCompatiblePositions($slot['label']));
            // GK has priority (only 1 compatible position)
            if ($slot['label'] === 'GK') return 0;
            return $compatibleCount;
        })->values()->all();

        foreach ($sortedSlots as $slot) {
            $bestPlayer = null;
            $bestScore = -1;

            foreach ($players as $player) {
                // Skip if already used
                if (in_array($player->id ?? $player['id'], $usedPlayerIds)) {
                    continue;
                }

                $position = $player->position ?? $player['position'];
                $overallScore = $player->overall_score ?? $player['overallScore'] ?? $player['overall_score'];

                $compatibility = PositionSlotMapper::getCompatibilityScore($position, $slot['label']);

                // Skip if player can't play in this slot
                if ($compatibility === 0) {
                    continue;
                }

                $effectiveRating = PositionSlotMapper::getEffectiveRating($overallScore, $position, $slot['label']);

                // Weighted score: 70% effective rating, 30% compatibility
                $weightedScore = ($effectiveRating * 0.7) + ($compatibility * 0.3);

                if ($weightedScore > $bestScore) {
                    $bestScore = $weightedScore;
                    $bestPlayer = [
                        'id' => $player->id ?? $player['id'],
                        'name' => $player->name ?? $player['name'],
                        'position' => $position,
                        'overallScore' => $overallScore,
                        'compatibility' => $compatibility,
                        'effectiveRating' => $effectiveRating,
                    ];
                }
            }

            $assigned[] = [
                'slot' => $slot,
                'player' => $bestPlayer,
                'compatibility' => $bestPlayer['compatibility'] ?? 0,
                'effectiveRating' => $bestPlayer['effectiveRating'] ?? 0,
            ];

            if ($bestPlayer) {
                $usedPlayerIds[] = $bestPlayer['id'];
            }
        }

        // Re-sort by original slot order
        usort($assigned, fn ($a, $b) => $a['slot']['id'] <=> $b['slot']['id']);

        return $assigned;
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

    /**
     * Get the single best formation recommendation.
     * Pre-computes player data as lightweight arrays to avoid accessor overhead
     * during the O(formations × slots × players) evaluation.
     */
    public function getBestFormation(Collection $players): Formation
    {
        // Pre-compute player data once (avoids ~40,000 accessor calls per batch)
        $preComputed = $players->map(fn ($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'position' => $p->position,
            'overall_score' => $p->overall_score,
        ])->values()->all();

        $bestFormation = Formation::F_4_4_2;
        $bestScore = -1;

        foreach (Formation::cases() as $formation) {
            $slots = $formation->pitchSlots();
            $bestXI = $this->findBestXI($slots, collect($preComputed));
            $coverage = $this->calculateCoverage($bestXI);
            $score = $this->calculateFormationScore($bestXI, $coverage);

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestFormation = $formation;
            }
        }

        return $bestFormation;
    }
}
