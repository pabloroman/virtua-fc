<?php

namespace Tests\Unit;

use App\Modules\Competition\Services\Draw\CrossCategoryPairing;
use App\Modules\Competition\Services\Draw\RandomPairing;
use PHPUnit\Framework\TestCase;

class CupDrawPairingTest extends TestCase
{
    public function test_cross_category_pairs_different_tiers_together(): void
    {
        $strategy = new CrossCategoryPairing();

        $teams = collect(['t1a', 't1b', 't1c', 't1d', 't99a', 't99b', 't99c', 't99d']);
        $tierMap = [
            't1a' => 1, 't1b' => 1, 't1c' => 1, 't1d' => 1,
            't99a' => 99, 't99b' => 99, 't99c' => 99, 't99d' => 99,
        ];

        $result = $strategy->pairTeams($teams, $tierMap);

        $this->assertCount(8, $result);

        // Every pair should cross categories: teams[0] vs teams[1], etc.
        for ($i = 0; $i < 4; $i++) {
            $first = $result[$i * 2];
            $second = $result[$i * 2 + 1];

            $firstTier = $tierMap[$first];
            $secondTier = $tierMap[$second];

            $this->assertNotEquals(
                $firstTier,
                $secondTier,
                "Pair {$i}: {$first} (tier {$firstTier}) vs {$second} (tier {$secondTier}) should be cross-category"
            );
        }
    }

    public function test_cross_category_maximizes_cross_tier_with_unequal_groups(): void
    {
        $strategy = new CrossCategoryPairing();

        // 2 tier-1 teams + 6 tier-99 teams = 8 total, 4 pairs
        $teams = collect(['t1a', 't1b', 't99a', 't99b', 't99c', 't99d', 't99e', 't99f']);
        $tierMap = [
            't1a' => 1, 't1b' => 1,
            't99a' => 99, 't99b' => 99, 't99c' => 99, 't99d' => 99, 't99e' => 99, 't99f' => 99,
        ];

        $result = $strategy->pairTeams($teams, $tierMap);

        $this->assertCount(8, $result);

        // Count cross-category pairings
        $crossCategoryCount = 0;

        for ($i = 0; $i < 4; $i++) {
            $firstTier = $tierMap[$result[$i * 2]];
            $secondTier = $tierMap[$result[$i * 2 + 1]];

            if ($firstTier !== $secondTier) {
                $crossCategoryCount++;
            }
        }

        // Maximum possible cross-category pairings is 2 (one per tier-1 team)
        $this->assertEquals(2, $crossCategoryCount);
    }

    public function test_cross_category_handles_all_same_tier(): void
    {
        $strategy = new CrossCategoryPairing();

        $teams = collect(['a', 'b', 'c', 'd', 'e', 'f']);
        $tierMap = ['a' => 99, 'b' => 99, 'c' => 99, 'd' => 99, 'e' => 99, 'f' => 99];

        $result = $strategy->pairTeams($teams, $tierMap);

        // Should still produce 6 teams (3 pairs worth)
        $this->assertCount(6, $result);

        // All 6 original teams should be present
        $this->assertCount(6, $result->unique());
    }

    public function test_cross_category_handles_odd_number_of_teams(): void
    {
        $strategy = new CrossCategoryPairing();

        $teams = collect(['a', 'b', 'c', 'd', 'e']);
        $tierMap = ['a' => 1, 'b' => 1, 'c' => 99, 'd' => 99, 'e' => 99];

        $result = $strategy->pairTeams($teams, $tierMap);

        // intdiv(5, 2) = 2 pairs = 4 teams
        $this->assertCount(4, $result);
        $this->assertCount(4, $result->unique());
    }

    public function test_cross_category_handles_three_tiers(): void
    {
        $strategy = new CrossCategoryPairing();

        // 2 tier-1 + 2 tier-2 + 4 tier-99 = 8 teams, 4 pairs
        $teams = collect(['t1a', 't1b', 't2a', 't2b', 't99a', 't99b', 't99c', 't99d']);
        $tierMap = [
            't1a' => 1, 't1b' => 1,
            't2a' => 2, 't2b' => 2,
            't99a' => 99, 't99b' => 99, 't99c' => 99, 't99d' => 99,
        ];

        $result = $strategy->pairTeams($teams, $tierMap);

        $this->assertCount(8, $result);

        // Higher half (sorted): t1a, t1b, t2a, t2b (tiers 1,1,2,2)
        // Lower half: t99a, t99b, t99c, t99d (all tier 99)
        // All 4 pairs should be cross-category
        for ($i = 0; $i < 4; $i++) {
            $firstTier = $tierMap[$result[$i * 2]];
            $secondTier = $tierMap[$result[$i * 2 + 1]];

            $this->assertNotEquals(
                $firstTier,
                $secondTier,
                "Pair {$i} should be cross-category"
            );
        }
    }

    public function test_cross_category_uses_default_tier_for_unmapped_teams(): void
    {
        $strategy = new CrossCategoryPairing();

        $teams = collect(['mapped1', 'mapped2', 'unmapped1', 'unmapped2']);
        $tierMap = ['mapped1' => 1, 'mapped2' => 1];
        // unmapped1, unmapped2 default to tier 99

        $result = $strategy->pairTeams($teams, $tierMap);

        $this->assertCount(4, $result);

        // Both pairs should be cross-category (tier 1 vs tier 99)
        for ($i = 0; $i < 2; $i++) {
            $firstTier = $tierMap[$result[$i * 2]] ?? 99;
            $secondTier = $tierMap[$result[$i * 2 + 1]] ?? 99;

            $this->assertNotEquals($firstTier, $secondTier);
        }
    }

    public function test_random_pairing_returns_all_teams(): void
    {
        $strategy = new RandomPairing();

        $teams = collect(['a', 'b', 'c', 'd']);
        $result = $strategy->pairTeams($teams, []);

        $this->assertCount(4, $result);
        $this->assertEqualsCanonicalizing(['a', 'b', 'c', 'd'], $result->all());
    }
}
