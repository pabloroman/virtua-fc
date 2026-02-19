<?php

namespace App\Modules\Lineup\Services;

use App\Modules\Lineup\Enums\Formation;
use App\Support\PositionSlotMapper;
use Illuminate\Support\Collection;

class FormationRecommender
{
    /**
     * Analyze a squad and recommend formations with scores.
     *
     * @param Collection $players Collection of players with 'position' and 'overall_score' attributes
     * @return array<array{formation: Formation, score: int, coverage: array, strengths: array, weaknesses: array}>
     */
    public function recommend(Collection $players): array
    {
        $recommendations = [];

        foreach (Formation::cases() as $formation) {
            $analysis = $this->analyzeFormation($formation, $players);
            $recommendations[] = [
                'formation' => $formation,
                'score' => $analysis['score'],
                'coverage' => $analysis['coverage'],
                'strengths' => $analysis['strengths'],
                'weaknesses' => $analysis['weaknesses'],
                'bestXI' => $analysis['bestXI'],
                'averageRating' => $analysis['averageRating'],
            ];
        }

        // Sort by score descending
        usort($recommendations, fn ($a, $b) => $b['score'] <=> $a['score']);

        return $recommendations;
    }

    /**
     * Analyze how well a formation fits a squad.
     */
    public function analyzeFormation(Formation $formation, Collection $players): array
    {
        $slots = $formation->pitchSlots();
        $bestXI = $this->findBestXI($slots, $players);

        // Calculate coverage (how many slots have natural/good players)
        $coverage = $this->calculateCoverage($bestXI);

        // Calculate formation score
        $score = $this->calculateFormationScore($bestXI, $coverage);

        // Find strengths and weaknesses
        $strengths = $this->findStrengths($bestXI, $players);
        $weaknesses = $this->findWeaknesses($bestXI);

        // Calculate average rating of best XI
        $averageRating = $this->calculateAverageRating($bestXI);

        return [
            'score' => $score,
            'coverage' => $coverage,
            'strengths' => $strengths,
            'weaknesses' => $weaknesses,
            'bestXI' => $bestXI,
            'averageRating' => $averageRating,
        ];
    }

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
     * Find formation strengths based on player positions.
     */
    private function findStrengths(array $bestXI, Collection $players): array
    {
        $strengths = [];

        // Check for strong areas
        $naturalByArea = [
            'attack' => 0,
            'midfield' => 0,
            'defense' => 0,
        ];

        foreach ($bestXI as $assignment) {
            if (!$assignment['player'] || $assignment['compatibility'] < 80) {
                continue;
            }

            $slotGroup = PositionSlotMapper::getSlotPositionGroup($assignment['slot']['label']);
            if ($slotGroup === 'Forward') $naturalByArea['attack']++;
            elseif ($slotGroup === 'Midfielder') $naturalByArea['midfield']++;
            elseif ($slotGroup === 'Defender') $naturalByArea['defense']++;
        }

        if ($naturalByArea['attack'] >= 2) {
            $strengths[] = 'Strong attacking options';
        }
        if ($naturalByArea['midfield'] >= 3) {
            $strengths[] = 'Excellent midfield depth';
        }
        if ($naturalByArea['defense'] >= 4) {
            $strengths[] = 'Solid defensive foundation';
        }

        // Check for specific tactical strengths
        $hasAM = collect($bestXI)->contains(fn ($a) =>
            $a['slot']['label'] === 'AM' && $a['compatibility'] >= 80
        );
        if ($hasAM) {
            $strengths[] = 'Creative playmaker available';
        }

        $hasWingers = collect($bestXI)->filter(fn ($a) =>
            in_array($a['slot']['label'], ['LW', 'RW', 'LM', 'RM']) && $a['compatibility'] >= 80
        )->count() >= 2;
        if ($hasWingers) {
            $strengths[] = 'Width in attack';
        }

        return $strengths;
    }

    /**
     * Find formation weaknesses.
     */
    private function findWeaknesses(array $bestXI): array
    {
        $weaknesses = [];

        foreach ($bestXI as $assignment) {
            if (!$assignment['player']) {
                $weaknesses[] = "No suitable player for {$assignment['slot']['label']}";
            } elseif ($assignment['compatibility'] < 40) {
                $slotName = PositionSlotMapper::getSlotDisplayName($assignment['slot']['label']);
                $weaknesses[] = "Weak coverage at {$slotName}";
            }
        }

        return array_slice($weaknesses, 0, 3); // Limit to top 3 weaknesses
    }

    /**
     * Calculate average rating of the best XI.
     */
    private function calculateAverageRating(array $bestXI): int
    {
        $ratings = array_filter(array_column(
            array_column($bestXI, 'player'),
            'overallScore'
        ));

        if (empty($ratings)) {
            return 0;
        }

        return (int) round(array_sum($ratings) / count($ratings));
    }

    /**
     * Get the single best formation recommendation.
     */
    public function getBestFormation(Collection $players): Formation
    {
        $recommendations = $this->recommend($players);
        return $recommendations[0]['formation'] ?? Formation::F_4_4_2;
    }
}
