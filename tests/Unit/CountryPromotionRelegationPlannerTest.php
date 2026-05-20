<?php

namespace Tests\Unit;

use App\Modules\Competition\Enums\PlayoffState;
use App\Modules\Competition\Exceptions\PlayoffInProgressException;
use App\Modules\Competition\Promotions\CountryPromotionRelegationPlanner;
use App\Modules\Competition\Promotions\CountrySeasonSnapshot;
use App\Modules\Competition\Promotions\PromotionMove;
use App\Modules\Competition\Promotions\PromotionRelegationPlan;
use App\Modules\Competition\Promotions\SkippedRelegation;
use PHPUnit\Framework\TestCase;

/**
 * Pure unit tests for the country-aware promotion/relegation planner.
 *
 * Tests drive the planner with synthetic CountrySeasonSnapshots — no DB,
 * no Laravel container, no migrations. Each test names a specific reserve/
 * parent/cascade scenario from the rewrite plan's truth table.
 *
 * Spain config used throughout:
 *   - Tier 1: ESP1 (20 teams)         relegated positions [18,19,20]
 *   - Tier 2: ESP2 (22 teams)         relegated positions [19,20,21,22]
 *   - Tier 3: ESP3A (20) + ESP3B (20) — deepest tier, siblings
 *
 * Rules:
 *   - ESP1↔ESP2: 2 direct + 4 playoff (playoff in ESP2)
 *   - ESP2↔(ESP3A+ESP3B): 1 direct from each group + 2 playoff winners (ESP3PO)
 */
class CountryPromotionRelegationPlannerTest extends TestCase
{
    private CountryPromotionRelegationPlanner $planner;
    private array $spainConfig;

    protected function setUp(): void
    {
        parent::setUp();
        $this->planner = new CountryPromotionRelegationPlanner();
        $this->spainConfig = $this->buildSpainConfig();
    }

    // ──────────────────────────────────────────────────
    // Country has no promotion config (e.g. England)
    // ──────────────────────────────────────────────────

    public function test_country_without_promotions_returns_empty_plan(): void
    {
        $snapshot = new CountrySeasonSnapshot(
            countryCode: 'EN',
            standingsByCompetition: ['ENG1' => $this->ids(20)],
            reserveToParent: [],
        );

        $plan = $this->planner->planFromSnapshot($snapshot, ['tiers' => [1 => ['competition' => 'ENG1', 'teams' => 20]], 'promotions' => []]);

        $this->assertTrue($plan->isEmpty());
        $this->assertSame('EN', $plan->countryCode);
    }

    // ──────────────────────────────────────────────────
    // Happy path
    // ──────────────────────────────────────────────────

    public function test_simple_two_tier_swap_no_reserves(): void
    {
        $esp1 = $this->ids(20, 'a1');
        $esp2 = $this->ids(22, 'a2');

        $snapshot = new CountrySeasonSnapshot(
            countryCode: 'ES',
            standingsByCompetition: [
                'ESP1' => $esp1,
                'ESP2' => $esp2,
                'ESP3A' => $this->ids(20, 'a3'),
                'ESP3B' => $this->ids(20, 'b3'),
            ],
            reserveToParent: [],
            playoffStates: ['ESP2' => PlayoffState::NotStarted, 'ESP3PO' => PlayoffState::NotStarted],
        );

        $plan = $this->planner->planFromSnapshot($snapshot, $this->spainConfig);

        // ESP1↔ESP2: positions 18,19,20 from ESP1 relegate; 1,2,3,4,5,6 from ESP2 promote (2 direct + 4 playoff stand-in).
        $promotionsToEsp1 = $plan->promotionsInto('ESP1');
        $relegationsToEsp2 = $plan->relegationsInto('ESP2');

        $this->assertCount(3, $promotionsToEsp1, 'ESP2→ESP1 promotions');
        $this->assertCount(3, $relegationsToEsp2, 'ESP1→ESP2 relegations');

        $promotedIds = array_map(fn ($m) => $m->teamId, $promotionsToEsp1);
        $relegatedIds = array_map(fn ($m) => $m->teamId, $relegationsToEsp2);

        $this->assertEqualsCanonicalizing(
            [$esp2[0], $esp2[1], $esp2[2]],
            $promotedIds,
            'Top 3 of ESP2 (after stand-in fallback) promote',
        );
        $this->assertSame(
            [$esp1[17], $esp1[18], $esp1[19]],
            $relegatedIds,
            'ESP1 positions 18,19,20 relegate',
        );

        // Each team moves at most once
        $this->assertNoTeamMovedTwice($plan);
    }

    // ──────────────────────────────────────────────────
    // Reserve filter — reserve at top of ESP2, parent in ESP1
    // ──────────────────────────────────────────────────

    public function test_reserve_at_esp2_position_one_is_filtered_when_parent_is_in_esp1(): void
    {
        $esp1 = $this->ids(20, 'a1');
        $parent = $esp1[5];

        $reserve = 'reserve-of-' . $parent;
        $esp2 = array_merge([$reserve], $this->ids(21, 'a2'));

        $snapshot = new CountrySeasonSnapshot(
            countryCode: 'ES',
            standingsByCompetition: [
                'ESP1' => $esp1,
                'ESP2' => $esp2,
                'ESP3A' => $this->ids(20, 'a3'),
                'ESP3B' => $this->ids(20, 'b3'),
            ],
            reserveToParent: [$reserve => $parent],
            playoffStates: ['ESP2' => PlayoffState::NotStarted, 'ESP3PO' => PlayoffState::NotStarted],
        );

        $plan = $this->planner->planFromSnapshot($snapshot, $this->spainConfig);

        $promotedToEsp1 = array_map(fn ($m) => $m->teamId, $plan->promotionsInto('ESP1'));

        $this->assertNotContains($reserve, $promotedToEsp1, 'Reserve must not be promoted');
        $this->assertCount(3, $promotedToEsp1);
        // Positions 2, 3, 4 from ESP2 promote (position 1 was reserve, skipped)
        $this->assertEqualsCanonicalizing(
            [$esp2[1], $esp2[2], $esp2[3]],
            $promotedToEsp1,
        );
    }

    // ──────────────────────────────────────────────────
    // Cascade-down: the production bug
    // Parent relegates ESP1→ESP2 INTO reserve's current tier
    // ──────────────────────────────────────────────────

    public function test_parent_relegated_into_reserve_tier_cascades_reserve_down(): void
    {
        $esp1 = $this->ids(20, 'a1');
        // Parent is at relegation position (position 18, index 17)
        $parent = $esp1[17];

        $reserve = 'reserve-of-' . $parent;
        $esp2 = array_merge($this->ids(10, 'a2'), [$reserve], $this->ids(11, 'b2'));

        $esp3a = $this->ids(20, 'a3');
        $esp3b = $this->ids(20, 'b3');

        $snapshot = new CountrySeasonSnapshot(
            countryCode: 'ES',
            standingsByCompetition: [
                'ESP1' => $esp1,
                'ESP2' => $esp2,
                'ESP3A' => $esp3a,
                'ESP3B' => $esp3b,
            ],
            reserveToParent: [$reserve => $parent],
            playoffStates: ['ESP2' => PlayoffState::NotStarted, 'ESP3PO' => PlayoffState::NotStarted],
        );

        $plan = $this->planner->planFromSnapshot($snapshot, $this->spainConfig);

        // Parent must relegate to ESP2
        $parentMove = $this->findMove($plan, $parent);
        $this->assertNotNull($parentMove, 'Parent must be moved');
        $this->assertSame('ESP1', $parentMove->fromCompetitionId);
        $this->assertSame('ESP2', $parentMove->toCompetitionId);

        // Reserve must cascade down to ESP3A or ESP3B
        $reserveMove = $this->findMove($plan, $reserve);
        $this->assertNotNull($reserveMove, 'Reserve must cascade down');
        $this->assertSame('ESP2', $reserveMove->fromCompetitionId);
        $this->assertContains($reserveMove->toCompetitionId, ['ESP3A', 'ESP3B']);
        $this->assertSame(PromotionMove::REASON_RESERVE_CASCADE, $reserveMove->reason);

        // After moves, reserve and parent are in different tiers
        $this->assertNoReserveParentCoexistenceAfterPlan($plan, $snapshot);
        // Tier sizes preserved
        $this->assertTierCountsPreserved($plan, $snapshot, $this->spainConfig);
    }

    // ──────────────────────────────────────────────────
     // Playoff winner ↔ direct promoter dedupe
     // (production bug: bracket seeded mid-season, winner climbs to direct
     // slot by season-end → planner emits two moves for the same team)
     // ──────────────────────────────────────────────────

    public function test_simple_rule_playoff_winner_also_direct_promoter_dedupes(): void
    {
        // Reproduces the Racing Santander scenario: ESP2 finalist climbs to
        // pos 2 (a direct-promotion slot) by season-end and also wins the
        // bracket. Without dedupe the planner emits two ESP2→ESP1 moves for
        // the same team and trips the no-double-move invariant.
        $esp1 = $this->ids(20, 'a1');
        $esp2 = $this->ids(22, 'a2');
        $playoffWinner = $esp2[1]; // pos 2 = direct promoter

        $snapshot = new CountrySeasonSnapshot(
            countryCode: 'ES',
            standingsByCompetition: [
                'ESP1' => $esp1,
                'ESP2' => $esp2,
                'ESP3A' => $this->ids(20, 'a3'),
                'ESP3B' => $this->ids(20, 'b3'),
            ],
            reserveToParent: [],
            playoffStates: ['ESP2' => PlayoffState::Completed, 'ESP3PO' => PlayoffState::NotStarted],
            playoffWinners: ['ESP2' => [$playoffWinner]],
        );

        $plan = $this->planner->planFromSnapshot($snapshot, $this->spainConfig);

        $promotedToEsp1 = array_map(fn ($m) => $m->teamId, $plan->promotionsInto('ESP1'));

        // Three unique promoters: the two direct slots + a fallback playoff
        // pick (the next eligible team in standings after the direct range).
        $this->assertCount(3, $promotedToEsp1);
        $this->assertSame(
            [$esp2[0], $esp2[1], $esp2[2]],
            $promotedToEsp1,
            'Direct slots take pos 1-2; playoff slot falls through to pos 3 because pos 2 is already direct',
        );

        $this->assertNoTeamMovedTwice($plan);
        $this->assertTierCountsPreserved($plan, $snapshot, $this->spainConfig);
    }

    public function test_split_rule_playoff_winner_also_direct_promoter_dedupes(): void
    {
        // Same dedupe applied to the PrimeraRFEF split rule: a bracket winner
        // that's also taking a direct slot from its feeder group falls back
        // to the next eligible team in the same group.
        $esp1 = $this->ids(20, 'a1');
        $esp2 = $this->ids(22, 'a2');
        $esp3a = $this->ids(20, 'a3');
        $esp3b = $this->ids(20, 'b3');

        // ESP3PO completed with two winners; the first is also ESP3A's
        // direct promoter (pos 1) — that's the dedupe target.
        $snapshot = new CountrySeasonSnapshot(
            countryCode: 'ES',
            standingsByCompetition: [
                'ESP1' => $esp1,
                'ESP2' => $esp2,
                'ESP3A' => $esp3a,
                'ESP3B' => $esp3b,
            ],
            reserveToParent: [],
            playoffStates: ['ESP2' => PlayoffState::NotStarted, 'ESP3PO' => PlayoffState::Completed],
            playoffWinners: ['ESP3PO' => [$esp3a[0], $esp3a[2]]], // pos 1 (also direct) + pos 3 (clean)
        );

        $plan = $this->planner->planFromSnapshot($snapshot, $this->spainConfig);

        $promotedToEsp2 = array_map(fn ($m) => $m->teamId, $plan->promotionsInto('ESP2'));

        // 4 unique promoters expected (1 direct from each of A and B, plus 2 playoff slots).
        $this->assertCount(4, $promotedToEsp2);
        // The duplicate (esp3a[0]) appears only once; the missing playoff slot
        // falls back to a stand-in past the direct range.
        $this->assertSame(
            1,
            count(array_keys($promotedToEsp2, $esp3a[0], true)),
            'Duplicate winner appears only once in promotion moves',
        );

        $this->assertNoTeamMovedTwice($plan);
        $this->assertTierCountsPreserved($plan, $snapshot, $this->spainConfig);
    }

    public function test_escape_hatch_cancels_parent_relegation_when_reserve_at_deepest_tier(): void
    {
        // Spain's tier 3 (ESP3A/B) has siblings, so the split rule can always
        // place a relegating parent in the sibling group its reserve is not in
        // — the escape hatch can't trigger there.
        //
        // To exercise the escape hatch we use a hypothetical 3-tier country
        // with one competition per tier (no siblings, no playoffs). The
        // parent's only relegation destination is the same competition the
        // reserve already lives in, and the deepest tier has no level below.
        $config = [
            'tiers' => [
                1 => ['competition' => 'XX1', 'teams' => 4],
                2 => ['competition' => 'XX2', 'teams' => 4],
                3 => ['competition' => 'XX3', 'teams' => 4],
            ],
            'promotions' => [
                [
                    'top_division' => 'XX1',
                    'bottom_division' => 'XX2',
                    'relegated_positions' => [4],
                    'direct_count' => 1,
                    'playoff_count' => 0,
                ],
                [
                    'top_division' => 'XX2',
                    'bottom_division' => 'XX3',
                    'relegated_positions' => [4],
                    'direct_count' => 1,
                    'playoff_count' => 0,
                ],
            ],
        ];

        $xx1 = ['p1', 'p2', 'p3', 'parent']; // parent at relegation slot (position 4)
        $xx2 = ['m1', 'm2', 'm3', 'm4'];
        $xx3 = ['reserve', 'z1', 'z2', 'z3']; // reserve already at floor

        $snapshot = new CountrySeasonSnapshot(
            countryCode: 'XX',
            standingsByCompetition: [
                'XX1' => $xx1,
                'XX2' => $xx2,
                'XX3' => $xx3,
            ],
            reserveToParent: ['reserve' => 'parent'],
        );

        // The cascade isn't strictly needed here (parent goes XX1→XX2, reserve
        // is in XX3 — different tiers, no coexistence). Use a different setup
        // where the escape hatch fires: parent at XX2 relegating to XX3, with
        // reserve already in XX3.
        $xx2 = ['m1', 'm2', 'm3', 'parent2']; // parent2 at XX2 pos 4 (relegating)
        $xx3 = ['reserveOf2', 'z1', 'z2', 'z3'];

        $snapshot = new CountrySeasonSnapshot(
            countryCode: 'XX',
            standingsByCompetition: [
                'XX1' => ['p1', 'p2', 'p3', 'p4'],
                'XX2' => $xx2,
                'XX3' => $xx3,
            ],
            reserveToParent: ['reserveOf2' => 'parent2'],
        );

        $plan = $this->planner->planFromSnapshot($snapshot, $config);

        // Parent2 did NOT relegate
        $parentMove = $this->findMove($plan, 'parent2');
        $this->assertNull($parentMove, 'Parent at floor must not relegate');

        // The skipped_relegations list records this
        $this->assertCount(1, $plan->skippedRelegations);
        $this->assertSame('parent2', $plan->skippedRelegations[0]->parentTeamId);
        $this->assertSame(SkippedRelegation::REASON_RESERVE_AT_FLOOR, $plan->skippedRelegations[0]->reason);

        // Reserve stayed put
        $this->assertNull($this->findMove($plan, 'reserveOf2'));

        // Counts preserved
        $this->assertTierCountsPreserved($plan, $snapshot, $config);
    }

    // ──────────────────────────────────────────────────
    // Reserve promotion + parent relegation (the user's stated case)
    // ──────────────────────────────────────────────────

    public function test_reserve_promotion_and_parent_relegation_collide_gap_one(): void
    {
        // Parent ESP1 (pos 18, relegating), reserve at ESP2 pos 1 (would promote).
        // Combined: both would land in ESP2 → block reserve promotion + cascade
        // reserve to ESP3 to maintain gap.
        $esp1 = $this->ids(20, 'a1');
        $parent = $esp1[17]; // relegating
        $reserve = 'reserve-of-' . $parent;
        $esp2 = array_merge([$reserve], $this->ids(21, 'a2'));

        $snapshot = new CountrySeasonSnapshot(
            countryCode: 'ES',
            standingsByCompetition: [
                'ESP1' => $esp1,
                'ESP2' => $esp2,
                'ESP3A' => $this->ids(20, 'a3'),
                'ESP3B' => $this->ids(20, 'b3'),
            ],
            reserveToParent: [$reserve => $parent],
            playoffStates: ['ESP2' => PlayoffState::NotStarted, 'ESP3PO' => PlayoffState::NotStarted],
        );

        $plan = $this->planner->planFromSnapshot($snapshot, $this->spainConfig);

        // Reserve does NOT promote
        $promotedToEsp1 = array_map(fn ($m) => $m->teamId, $plan->promotionsInto('ESP1'));
        $this->assertNotContains($reserve, $promotedToEsp1);

        // Reserve cascades to ESP3
        $reserveMove = $this->findMove($plan, $reserve);
        $this->assertNotNull($reserveMove);
        $this->assertContains($reserveMove->toCompetitionId, ['ESP3A', 'ESP3B']);

        // Parent relegates as planned
        $parentMove = $this->findMove($plan, $parent);
        $this->assertNotNull($parentMove);
        $this->assertSame('ESP2', $parentMove->toCompetitionId);

        $this->assertNoReserveParentCoexistenceAfterPlan($plan, $snapshot);
        $this->assertTierCountsPreserved($plan, $snapshot, $this->spainConfig);
    }

    public function test_reserve_promotion_and_parent_relegation_collide_gap_two(): void
    {
        // Parent ESP1 (pos 18, relegating to ESP2), reserve at ESP3A pos 1 (would promote to ESP2).
        // Combined: both would land in ESP2 → block reserve promotion. Reserve stays in ESP3A.
        $esp1 = $this->ids(20, 'a1');
        $parent = $esp1[17];
        $reserve = 'reserve-of-' . $parent;

        $esp3a = array_merge([$reserve], $this->ids(19, 'a3'));

        $snapshot = new CountrySeasonSnapshot(
            countryCode: 'ES',
            standingsByCompetition: [
                'ESP1' => $esp1,
                'ESP2' => $this->ids(22, 'a2'),
                'ESP3A' => $esp3a,
                'ESP3B' => $this->ids(20, 'b3'),
            ],
            reserveToParent: [$reserve => $parent],
            playoffStates: ['ESP2' => PlayoffState::NotStarted, 'ESP3PO' => PlayoffState::NotStarted],
        );

        $plan = $this->planner->planFromSnapshot($snapshot, $this->spainConfig);

        $promotedToEsp2 = array_map(fn ($m) => $m->teamId, $plan->promotionsInto('ESP2'));
        $this->assertNotContains($reserve, $promotedToEsp2, 'Reserve at ESP3A pos 1 must not promote when parent is about to land in ESP2');

        // Reserve does not move
        $this->assertNull($this->findMove($plan, $reserve));

        // Parent relegated normally
        $parentMove = $this->findMove($plan, $parent);
        $this->assertNotNull($parentMove);
        $this->assertSame('ESP2', $parentMove->toCompetitionId);

        $this->assertNoReserveParentCoexistenceAfterPlan($plan, $snapshot);
        $this->assertTierCountsPreserved($plan, $snapshot, $this->spainConfig);
    }

    public function test_reserve_promotion_blocked_when_parent_in_destination_no_relegation(): void
    {
        // Standard case: parent stays in ESP1, reserve at ESP2 pos 1 — blocked.
        $esp1 = $this->ids(20, 'a1');
        $parent = $esp1[5]; // mid-table, not relegating
        $reserve = 'reserve-of-' . $parent;
        $esp2 = array_merge([$reserve], $this->ids(21, 'a2'));

        $snapshot = new CountrySeasonSnapshot(
            countryCode: 'ES',
            standingsByCompetition: [
                'ESP1' => $esp1,
                'ESP2' => $esp2,
                'ESP3A' => $this->ids(20, 'a3'),
                'ESP3B' => $this->ids(20, 'b3'),
            ],
            reserveToParent: [$reserve => $parent],
            playoffStates: ['ESP2' => PlayoffState::NotStarted, 'ESP3PO' => PlayoffState::NotStarted],
        );

        $plan = $this->planner->planFromSnapshot($snapshot, $this->spainConfig);

        $promoted = array_map(fn ($m) => $m->teamId, $plan->promotionsInto('ESP1'));
        $this->assertNotContains($reserve, $promoted);

        // No cascade needed — parent stays put, reserve stays put
        $this->assertNull($this->findMove($plan, $reserve));
    }

    // ──────────────────────────────────────────────────
    // PrimeraRFEF: ESP2 ↔ ESP3A + ESP3B split
    // ──────────────────────────────────────────────────

    public function test_primera_rfef_splits_relegators_between_groups_by_promotion_count(): void
    {
        // ESP3A and ESP3B each have a position-1 team that gets direct promotion
        // (so 1 leaves each group). ESP3PO has 2 playoff winners; both come from
        // ESP3A in this scenario. So 3 teams leave A, 1 leaves B → 3 relegators
        // go to A, 1 to B.
        $esp2 = $this->ids(22, 'a2');
        $esp3a = $this->ids(20, 'a3');
        $esp3b = $this->ids(20, 'b3');

        $snapshot = new CountrySeasonSnapshot(
            countryCode: 'ES',
            standingsByCompetition: [
                'ESP1' => $this->ids(20, 'a1'),
                'ESP2' => $esp2,
                'ESP3A' => $esp3a,
                'ESP3B' => $esp3b,
            ],
            reserveToParent: [],
            playoffStates: ['ESP2' => PlayoffState::NotStarted, 'ESP3PO' => PlayoffState::Completed],
            playoffWinners: ['ESP3PO' => [$esp3a[1], $esp3a[2]]], // Both ESP3A
        );

        $plan = $this->planner->planFromSnapshot($snapshot, $this->spainConfig);

        $relegatedToA = array_map(fn ($m) => $m->teamId, $plan->relegationsInto('ESP3A'));
        $relegatedToB = array_map(fn ($m) => $m->teamId, $plan->relegationsInto('ESP3B'));
        $promotedToEsp2 = array_map(fn ($m) => $m->teamId, $plan->promotionsInto('ESP2'));

        $this->assertCount(3, $relegatedToA, '3 teams leave ESP3A (1 direct + 2 playoff) → 3 ESP2 teams relegate to ESP3A');
        $this->assertCount(1, $relegatedToB, '1 team leaves ESP3B (direct only) → 1 ESP2 team relegates to ESP3B');
        $this->assertCount(4, $promotedToEsp2, '4 teams promote to ESP2');

        $expectedPromoted = [$esp3a[0], $esp3b[0], $esp3a[1], $esp3a[2]];
        $this->assertEqualsCanonicalizing($expectedPromoted, $promotedToEsp2);

        $this->assertTierCountsPreserved($plan, $snapshot, $this->spainConfig);
    }

    // ──────────────────────────────────────────────────
    // Playoff in progress
    // ──────────────────────────────────────────────────

    public function test_playoff_in_progress_throws(): void
    {
        $snapshot = new CountrySeasonSnapshot(
            countryCode: 'ES',
            standingsByCompetition: [
                'ESP1' => $this->ids(20, 'a1'),
                'ESP2' => $this->ids(22, 'a2'),
                'ESP3A' => $this->ids(20, 'a3'),
                'ESP3B' => $this->ids(20, 'b3'),
            ],
            reserveToParent: [],
            playoffStates: ['ESP2' => PlayoffState::InProgress, 'ESP3PO' => PlayoffState::NotStarted],
        );

        $this->expectException(PlayoffInProgressException::class);
        $this->planner->planFromSnapshot($snapshot, $this->spainConfig);
    }

    // ──────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────

    /**
     * Generate $n synthetic team IDs with a prefix.
     *
     * @return list<string>
     */
    private function ids(int $n, string $prefix = 'team'): array
    {
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $out[] = sprintf('%s-%03d', $prefix, $i);
        }
        return $out;
    }

    private function findMove(PromotionRelegationPlan $plan, string $teamId): ?PromotionMove
    {
        foreach ($plan->moves as $m) {
            if ($m->teamId === $teamId) {
                return $m;
            }
        }
        return null;
    }

    private function assertNoTeamMovedTwice(PromotionRelegationPlan $plan): void
    {
        $seen = [];
        foreach ($plan->moves as $m) {
            $this->assertArrayNotHasKey($m->teamId, $seen, "Team {$m->teamId} appears in moves twice");
            $seen[$m->teamId] = true;
        }
    }

    private function assertNoReserveParentCoexistenceAfterPlan(PromotionRelegationPlan $plan, CountrySeasonSnapshot $snapshot): void
    {
        $finalCompetition = [];
        // Start from snapshot
        foreach ($snapshot->standingsByCompetition as $comp => $teams) {
            foreach ($teams as $teamId) {
                $finalCompetition[$teamId] = $comp;
            }
        }
        // Apply moves
        foreach ($plan->moves as $m) {
            $finalCompetition[$m->teamId] = $m->toCompetitionId;
        }

        foreach ($snapshot->reserveToParent as $reserve => $parent) {
            if (!isset($finalCompetition[$reserve]) || !isset($finalCompetition[$parent])) {
                continue;
            }
            $this->assertNotSame(
                $finalCompetition[$reserve],
                $finalCompetition[$parent],
                "Reserve {$reserve} and parent {$parent} share competition {$finalCompetition[$reserve]} after plan applies",
            );
        }
    }

    private function assertTierCountsPreserved(PromotionRelegationPlan $plan, CountrySeasonSnapshot $snapshot, array $config): void
    {
        // Apply moves
        $counts = [];
        foreach ($snapshot->standingsByCompetition as $comp => $teams) {
            $counts[$comp] = count($teams);
        }

        foreach ($plan->moves as $m) {
            $counts[$m->fromCompetitionId] = ($counts[$m->fromCompetitionId] ?? 0) - 1;
            $counts[$m->toCompetitionId] = ($counts[$m->toCompetitionId] ?? 0) + 1;
        }

        // Verify each tier matches its configured size
        foreach ($config['tiers'] ?? [] as $tier) {
            $this->assertSame($tier['teams'], $counts[$tier['competition']], "Tier {$tier['competition']} size drifted");
            foreach ($tier['siblings'] ?? [] as $sibling) {
                $this->assertSame($sibling['teams'], $counts[$sibling['competition']], "Tier {$sibling['competition']} size drifted");
            }
        }
    }

    private function buildSpainConfig(): array
    {
        return [
            'tiers' => [
                1 => ['competition' => 'ESP1', 'teams' => 20],
                2 => ['competition' => 'ESP2', 'teams' => 22],
                3 => [
                    'competition' => 'ESP3A',
                    'teams' => 20,
                    'siblings' => [
                        ['competition' => 'ESP3B', 'teams' => 20],
                    ],
                ],
            ],
            'promotions' => [
                [
                    'top_division' => 'ESP1',
                    'bottom_division' => 'ESP2',
                    'relegated_positions' => [18, 19, 20],
                    'direct_count' => 2,
                    'playoff_count' => 4,
                    'playoff_competition' => 'ESP2',
                ],
                [
                    'top_division' => 'ESP2',
                    'bottom_division' => 'ESP3A',
                    'relegated_positions' => [19, 20, 21, 22],
                    'direct_count' => 1,
                    'playoff_count' => 4,
                    'playoff_competition' => 'ESP3PO',
                    'playoff_source_divisions' => ['ESP3A', 'ESP3B'],
                ],
            ],
        ];
    }
}
