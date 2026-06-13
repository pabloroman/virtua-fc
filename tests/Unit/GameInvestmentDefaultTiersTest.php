<?php

namespace Tests\Unit;

use App\Models\GameInvestment;
use Tests\TestCase;

/**
 * defaultTiersForReputation() must reserve a transfer budget. The old
 * all-or-nothing reduction spent the full reputation default whenever a club
 * could just afford it, so the highest-revenue club in a division could end up
 * with the smallest transfer budget (Depor: €0.5M on the highest Segunda
 * revenue). It now trims the most expensive area one tier at a time until the
 * infrastructure spend fits within surplus minus the reserve.
 */
class GameInvestmentDefaultTiersTest extends TestCase
{
    /** @param array<string,int> $tiers */
    private function infraCost(array $tiers): int
    {
        $total = 0;
        foreach ($tiers as $area => $tier) {
            $total += $tier === 0
                ? GameInvestment::TIER_0_THRESHOLDS[$area]
                : GameInvestment::TIER_THRESHOLDS[$area][$tier];
        }

        return $total;
    }

    public function test_rich_club_keeps_the_full_reputation_default(): void
    {
        config(['finances.default_infra_transfer_reserve' => 0.45]);

        // €300M surplus dwarfs the €65M elite default, so nothing is trimmed.
        $tiers = GameInvestment::defaultTiersForReputation('elite', 300_000_000_00);

        $this->assertSame(
            ['youth_academy' => 4, 'medical' => 4, 'scouting' => 4, 'facilities' => 4],
            $tiers,
        );
    }

    public function test_tight_surplus_reserves_a_transfer_budget(): void
    {
        config(['finances.default_infra_transfer_reserve' => 0.45]);

        // Depor-like: a €14.5M surplus only just clears the €14M established
        // default, so infrastructure must be trimmed to leave a transfer reserve.
        $available = 14_500_000_00;
        $tiers = GameInvestment::defaultTiersForReputation('established', $available);
        $cost = $this->infraCost($tiers);

        $fullDefault = $this->infraCost([
            'youth_academy' => 2, 'medical' => 3, 'scouting' => 3, 'facilities' => 2,
        ]);

        $this->assertLessThan($fullDefault, $cost, 'infrastructure should be trimmed below the full default');
        $this->assertLessThanOrEqual((int) floor($available * 0.55), $cost, 'infra must fit within the reserve-adjusted budget');
        $this->assertGreaterThan(0, $available - $cost, 'a transfer budget must remain');
    }

    public function test_reduction_never_drops_below_the_minimum_tier(): void
    {
        config(['finances.default_infra_transfer_reserve' => 0.45]);

        // A surplus too small even for the all-minimum infra: the minTier floor
        // wins (minimum infrastructure is mandatory) rather than dropping lower.
        $tiers = GameInvestment::defaultTiersForReputation('established', 1_000_000_00, 1);

        $this->assertSame(
            ['youth_academy' => 1, 'medical' => 1, 'scouting' => 1, 'facilities' => 1],
            $tiers,
        );
    }
}
