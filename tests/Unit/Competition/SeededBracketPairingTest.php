<?php

namespace Tests\Unit\Competition;

use App\Modules\Competition\Services\Draw\SeededBracketPairing;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

/**
 * A supercup's ties follow from how each club qualified, so the pairing is
 * arithmetic over seeds rather than a draw. Pinned here without a database.
 */
class SeededBracketPairingTest extends TestCase
{
    private SeededBracketPairing $pairing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pairing = new SeededBracketPairing();
    }

    public function test_final_four_pairs_the_top_seed_against_the_bottom_one(): void
    {
        // Spain's seeds: 1 cup winner, 2 cup runner-up, 3 league champion,
        // 4 league runner-up. RFEF fixtures are 1 v 4 and 2 v 3.
        $ordered = $this->pair(['cup-w' => 1, 'cup-r' => 2, 'lge-1' => 3, 'lge-2' => 4]);

        $this->assertSame(['cup-w', 'lge-2', 'cup-r', 'lge-1'], $ordered);
    }

    public function test_input_order_does_not_affect_the_result(): void
    {
        $seeds = ['cup-w' => 1, 'cup-r' => 2, 'lge-1' => 3, 'lge-2' => 4];

        $this->assertSame(
            $this->pair($seeds, ['lge-1', 'lge-2', 'cup-r', 'cup-w']),
            $this->pair($seeds, ['cup-w', 'cup-r', 'lge-1', 'lge-2']),
        );
    }

    public function test_two_club_supercup_is_the_single_tie(): void
    {
        $this->assertSame(['cup-w', 'lge-1'], $this->pair(['cup-w' => 1, 'lge-1' => 2]));
    }

    public function test_unseeded_field_still_returns_every_club(): void
    {
        // A game's first season has no qualification history to seed from.
        $ordered = $this->pair([], ['a', 'b', 'c', 'd']);

        $this->assertEqualsCanonicalizing(['a', 'b', 'c', 'd'], $ordered);
        $this->assertCount(4, $ordered);
    }

    public function test_odd_field_leaves_the_median_club_unpaired_rather_than_dropping_it(): void
    {
        $ordered = $this->pair(['a' => 1, 'b' => 2, 'c' => 3]);

        $this->assertSame(['a', 'c', 'b'], $ordered);
    }

    /**
     * @param  array<string, int>  $seeds
     * @param  string[]|null  $teams
     * @return string[]
     */
    private function pair(array $seeds, ?array $teams = null): array
    {
        $teams = $teams ?? array_keys($seeds);

        return $this->pairing->pairTeams(new Collection($teams), [], $seeds)->all();
    }
}
