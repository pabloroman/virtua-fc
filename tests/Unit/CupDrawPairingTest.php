<?php

namespace Tests\Unit;

use App\Modules\Competition\Services\Draw\CrossCategoryPairing;
use App\Modules\Competition\Services\Draw\RandomPairing;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class CupDrawPairingTest extends TestCase
{
    // ---------------------------------------------------------------------
    // CrossCategoryPairing — even inputs (cross-tier behaviour)
    // ---------------------------------------------------------------------

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
        $this->assertEqualsCanonicalizing($teams->all(), $result->all());

        for ($i = 0; $i < 4; $i++) {
            $first = $result[$i * 2];
            $second = $result[$i * 2 + 1];

            $this->assertNotEquals(
                $tierMap[$first],
                $tierMap[$second],
                "Pair {$i}: {$first} vs {$second} should be cross-category"
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

        $crossCategoryCount = 0;
        for ($i = 0; $i < 4; $i++) {
            if ($tierMap[$result[$i * 2]] !== $tierMap[$result[$i * 2 + 1]]) {
                $crossCategoryCount++;
            }
        }

        // Maximum possible cross-category pairings is 2 (one per tier-1 team)
        $this->assertEquals(2, $crossCategoryCount);
    }

    public function test_cross_category_handles_three_tiers(): void
    {
        $strategy = new CrossCategoryPairing();

        $teams = collect(['t1a', 't1b', 't2a', 't2b', 't99a', 't99b', 't99c', 't99d']);
        $tierMap = [
            't1a' => 1, 't1b' => 1,
            't2a' => 2, 't2b' => 2,
            't99a' => 99, 't99b' => 99, 't99c' => 99, 't99d' => 99,
        ];

        $result = $strategy->pairTeams($teams, $tierMap);

        $this->assertCount(8, $result);
        for ($i = 0; $i < 4; $i++) {
            $this->assertNotEquals(
                $tierMap[$result[$i * 2]],
                $tierMap[$result[$i * 2 + 1]],
                "Pair {$i} should be cross-category"
            );
        }
    }

    public function test_cross_category_uses_default_tier_for_unmapped_teams(): void
    {
        $strategy = new CrossCategoryPairing();

        $teams = collect(['mapped1', 'mapped2', 'unmapped1', 'unmapped2']);
        $tierMap = ['mapped1' => 1, 'mapped2' => 1];

        $result = $strategy->pairTeams($teams, $tierMap);

        $this->assertCount(4, $result);

        for ($i = 0; $i < 2; $i++) {
            $firstTier = $tierMap[$result[$i * 2]] ?? 99;
            $secondTier = $tierMap[$result[$i * 2 + 1]] ?? 99;

            $this->assertNotEquals($firstTier, $secondTier);
        }
    }

    public function test_cross_category_handles_all_same_tier(): void
    {
        $strategy = new CrossCategoryPairing();

        $teams = collect(['a', 'b', 'c', 'd', 'e', 'f']);
        $tierMap = ['a' => 99, 'b' => 99, 'c' => 99, 'd' => 99, 'e' => 99, 'f' => 99];

        $result = $strategy->pairTeams($teams, $tierMap);

        $this->assertCount(6, $result);
        $this->assertCount(6, $result->unique());
        $this->assertEqualsCanonicalizing($teams->all(), $result->all());
    }

    // ---------------------------------------------------------------------
    // CrossCategoryPairing — odd inputs (the regression: nothing dropped)
    // ---------------------------------------------------------------------

    /**
     * Regression test for the silent-drop bug where the strategy used
     * `slice($half, $half)` and discarded the median team on odd inputs.
     * That produced 93 broken Copa del Rey ties in production — semifinals
     * with one game, etc. Every team must now survive the pairing pass.
     */
    public function test_odd_count_keeps_all_teams_with_one_unpaired_at_end(): void
    {
        $strategy = new CrossCategoryPairing();

        $teams = collect(['a', 'b', 'c', 'd', 'e']);
        $tierMap = ['a' => 1, 'b' => 1, 'c' => 99, 'd' => 99, 'e' => 99];

        $result = $strategy->pairTeams($teams, $tierMap);

        $this->assertCount(5, $result, 'Every input team must appear in the output');
        $this->assertEqualsCanonicalizing($teams->all(), $result->all());
        $this->assertCount(5, $result->unique(), 'No team duplicated');
    }

    public function test_odd_count_first_pairs_are_cross_category(): void
    {
        $strategy = new CrossCategoryPairing();

        // Higher-tier numbers = lower category. Top 2 = best (a, b).
        $teams = collect(['a', 'b', 'c', 'd', 'e']);
        $tierMap = ['a' => 1, 'b' => 1, 'c' => 99, 'd' => 99, 'e' => 99];

        // Run multiple times — randomness inside, so make the assertion robust.
        for ($trial = 0; $trial < 25; $trial++) {
            $result = $strategy->pairTeams($teams, $tierMap);

            $this->assertCount(5, $result);

            // First two pairs should be cross-category every single time.
            for ($i = 0; $i < 2; $i++) {
                $this->assertNotEquals(
                    $tierMap[$result[$i * 2]],
                    $tierMap[$result[$i * 2 + 1]],
                    "Trial {$trial} pair {$i} should be cross-category"
                );
            }

            // The trailing (unpaired) team comes from the larger half — the
            // lower-category bucket — so it's a tier-99 team.
            $this->assertEquals(99, $tierMap[$result[4]]);
        }
    }

    public function test_odd_count_three_teams(): void
    {
        $strategy = new CrossCategoryPairing();

        $teams = collect(['a', 'b', 'c']);
        $tierMap = ['a' => 1, 'b' => 99, 'c' => 99];

        $result = $strategy->pairTeams($teams, $tierMap);

        $this->assertCount(3, $result);
        $this->assertEqualsCanonicalizing(['a', 'b', 'c'], $result->all());
        // The single pair must be cross-category, leaving a tier-99 team unpaired.
        $this->assertNotEquals($tierMap[$result[0]], $tierMap[$result[1]]);
        $this->assertEquals(99, $tierMap[$result[2]]);
    }

    public function test_single_team_returned_unpaired(): void
    {
        $strategy = new CrossCategoryPairing();

        $result = $strategy->pairTeams(collect(['lonely']), ['lonely' => 1]);

        $this->assertCount(1, $result);
        $this->assertSame('lonely', $result[0]);
    }

    public function test_empty_input_returns_empty(): void
    {
        $strategy = new CrossCategoryPairing();

        $result = $strategy->pairTeams(collect(), []);

        $this->assertCount(0, $result);
    }

    public function test_two_teams_pair_directly(): void
    {
        $strategy = new CrossCategoryPairing();

        $teams = collect(['a', 'b']);
        $tierMap = ['a' => 1, 'b' => 99];

        $result = $strategy->pairTeams($teams, $tierMap);

        $this->assertCount(2, $result);
        $this->assertEqualsCanonicalizing(['a', 'b'], $result->all());
    }

    /**
     * Property-style sweep: hammer many odd and even sizes to make sure no
     * team is ever dropped. This is the catch-all guard against any future
     * regression of the original off-by-one slice.
     */
    public function test_no_team_is_ever_dropped_for_sizes_2_through_60(): void
    {
        $strategy = new CrossCategoryPairing();

        for ($n = 2; $n <= 60; $n++) {
            $teams = collect(range(1, $n))->map(fn ($i) => "t{$i}");
            $tierMap = $teams->mapWithKeys(fn ($t, $i) => [$t => ($i % 4) + 1])->all();

            $result = $strategy->pairTeams($teams, $tierMap);

            $this->assertCount(
                $n,
                $result,
                "n={$n}: result must contain every input team"
            );
            $this->assertEqualsCanonicalizing(
                $teams->all(),
                $result->all(),
                "n={$n}: output must be a permutation of input"
            );
        }
    }

    /**
     * Reproduces the production scenario: 57 winners feeding round 2 of
     * Copa del Rey (no entries that round). Before the fix this returned
     * 56 teams and the loop in CupDrawService produced 28 ties — losing
     * one team. Now the strategy must return all 57 teams and the trailing
     * one falls out as the surplus for the caller to handle.
     */
    public function test_reproduces_round_2_cascade_with_57_teams(): void
    {
        $strategy = new CrossCategoryPairing();

        // Mix of tiers so the sort/split doesn't degenerate.
        $teams = collect(range(1, 57))->map(fn ($i) => "team-{$i}");
        $tierMap = $teams->mapWithKeys(function ($t, $i) {
            // Rough mix: tier 1 (top), tier 2, tier 3
            $tier = match (true) {
                $i < 20 => 1,
                $i < 40 => 2,
                default => 3,
            };
            return [$t => $tier];
        })->all();

        $result = $strategy->pairTeams($teams, $tierMap);

        $this->assertCount(57, $result, 'No team should be dropped from a 57-team draw');
        $this->assertEqualsCanonicalizing($teams->all(), $result->all());
    }

    // ---------------------------------------------------------------------
    // RandomPairing
    // ---------------------------------------------------------------------

    public function test_random_pairing_returns_all_teams(): void
    {
        $strategy = new RandomPairing();

        $teams = collect(['a', 'b', 'c', 'd']);
        $result = $strategy->pairTeams($teams, []);

        $this->assertCount(4, $result);
        $this->assertEqualsCanonicalizing(['a', 'b', 'c', 'd'], $result->all());
    }

    public function test_random_pairing_keeps_all_teams_for_odd_input(): void
    {
        $strategy = new RandomPairing();

        $teams = collect(['a', 'b', 'c', 'd', 'e']);
        $result = $strategy->pairTeams($teams, []);

        $this->assertCount(5, $result);
        $this->assertEqualsCanonicalizing(['a', 'b', 'c', 'd', 'e'], $result->all());
    }

    public function test_random_pairing_keeps_single_team(): void
    {
        $strategy = new RandomPairing();

        $result = $strategy->pairTeams(collect(['lonely']), []);

        $this->assertCount(1, $result);
        $this->assertSame('lonely', $result[0]);
    }

    public function test_random_pairing_handles_empty(): void
    {
        $strategy = new RandomPairing();

        $result = $strategy->pairTeams(collect(), []);

        $this->assertCount(0, $result);
    }

    public function test_random_pairing_no_team_dropped_for_sizes_1_through_60(): void
    {
        $strategy = new RandomPairing();

        for ($n = 1; $n <= 60; $n++) {
            $teams = collect(range(1, $n))->map(fn ($i) => "t{$i}");
            $result = $strategy->pairTeams($teams, []);

            $this->assertCount($n, $result, "n={$n}: every team must be retained");
            $this->assertEqualsCanonicalizing($teams->all(), $result->all());
        }
    }
}
