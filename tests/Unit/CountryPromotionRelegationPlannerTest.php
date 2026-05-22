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
    // Legacy game: tier(s) referenced by a rule are absent from the snapshot
    // (e.g. ESP3A/ESP3B never existed for a 140-season-old game predating
    // Primera RFEF). The rule is dropped; rules whose tiers all exist still
    // run.
    // ──────────────────────────────────────────────────

    public function test_rule_skipped_when_bottom_division_absent_from_snapshot(): void
    {
        $esp1 = $this->ids(20, 'a1');
        $esp2 = $this->ids(22, 'a2');

        // ESP3A and ESP3B intentionally omitted — the snapshot builder
        // skipped them because the game has no CompetitionEntry rows for
        // them.
        $snapshot = new CountrySeasonSnapshot(
            countryCode: 'ES',
            standingsByCompetition: [
                'ESP1' => $esp1,
                'ESP2' => $esp2,
            ],
            reserveToParent: [],
            playoffStates: [
                'ESP2' => PlayoffState::NotStarted,
                'ESP3PO' => PlayoffState::NotStarted,
            ],
        );

        $plan = $this->planner->planFromSnapshot($snapshot, $this->spainConfig);

        // Rule #1 (ESP1↔ESP2) still runs: 3 down, 3 up (2 direct + 1 playoff
        // stand-in). Rule #2 (ESP2↔ESP3A/ESP3B) is dropped entirely because
        // ESP3A and ESP3B aren't in the snapshot.
        $this->assertCount(3, $plan->promotionsInto('ESP1'));
        $this->assertCount(3, $plan->relegationsInto('ESP2'));

        foreach ($plan->moves as $move) {
            $this->assertNotContains(
                $move->fromCompetitionId,
                ['ESP3A', 'ESP3B'],
                'No move should originate from an absent tier',
            );
            $this->assertNotContains(
                $move->toCompetitionId,
                ['ESP3A', 'ESP3B'],
                'No move should land in an absent tier',
            );
        }
    }

    public function test_split_rule_drops_reserve_when_parent_promoted_from_sibling_group(): void
    {
        $esp1 = $this->ids(20, 'a1');
        $esp2 = $this->ids(22, 'a2');
        $esp3a = $this->ids(20, 'a3');
        $esp3b = $this->ids(20, 'b3');

        // Promesas at top of ESP3A, Osasuna at top of ESP3B. Each passes
        // its own group's reserve filter (parent isn't in current ESP2
        // standings) — but if both get promoted to ESP2 via Rule #2 they
        // coexist there. The planner must see the cross-group conflict
        // and drop the reserve in favour of the next eligible ESP3A team.
        $parent = $esp3b[0];   // Osasuna at top of ESP3B
        $reserve = $esp3a[0];  // Promesas at top of ESP3A

        $snapshot = new CountrySeasonSnapshot(
            countryCode: 'ES',
            standingsByCompetition: [
                'ESP1' => $esp1,
                'ESP2' => $esp2,
                'ESP3A' => $esp3a,
                'ESP3B' => $esp3b,
            ],
            reserveToParent: [$reserve => $parent],
            playoffStates: [
                'ESP2' => PlayoffState::NotStarted,
                'ESP3PO' => PlayoffState::NotStarted,
            ],
        );

        $plan = $this->planner->planFromSnapshot($snapshot, $this->spainConfig);

        // Parent promoted to ESP2.
        $parentPromoted = false;
        foreach ($plan->moves as $move) {
            if ($move->teamId === $parent
                && $move->toCompetitionId === 'ESP2'
                && $move->isPromotion()
            ) {
                $parentPromoted = true;
            }
        }
        $this->assertTrue($parentPromoted, 'Parent should be promoted to ESP2 from ESP3B');

        // Reserve NOT promoted to ESP2 (filtered, ESP3A's slot goes to position 2).
        foreach ($plan->moves as $move) {
            if ($move->teamId === $reserve) {
                $this->fail('Reserve should not be moved when parent is being promoted to the same destination');
            }
        }

        // ESP3A's direct slot still filled — by the next eligible (position 2).
        $esp3aDirect = null;
        foreach ($plan->moves as $move) {
            if ($move->fromCompetitionId === 'ESP3A'
                && $move->toCompetitionId === 'ESP2'
                && $move->reason === PromotionMove::REASON_PROMOTION
            ) {
                $esp3aDirect = $move;
                break;
            }
        }
        $this->assertNotNull($esp3aDirect, 'ESP3A should still have a direct promoter');
        $this->assertSame($esp3a[1], $esp3aDirect->teamId, 'ESP3A direct should fall through to position 2');
    }

    public function test_inherited_coexistence_in_intermediate_tier_cascades_reserve_down(): void
    {
        $esp1 = $this->ids(20, 'a1');
        $esp2 = $this->ids(22, 'a2');
        $esp3a = $this->ids(20, 'a3');
        $esp3b = $this->ids(20, 'b3');

        // Parent and reserve already coexist mid-table in ESP2 — data
        // carried in from earlier seasons when the planner wasn't strict.
        // The parent isn't being relegated this season (mid-table) and
        // the reserve isn't being promoted (also mid-table after filter).
        // The planner must repair the coexistence on its own rather than
        // throwing in validatePlan.
        $parent = $esp2[10];
        $reserve = $esp2[15];

        $snapshot = new CountrySeasonSnapshot(
            countryCode: 'ES',
            standingsByCompetition: [
                'ESP1' => $esp1,
                'ESP2' => $esp2,
                'ESP3A' => $esp3a,
                'ESP3B' => $esp3b,
            ],
            reserveToParent: [$reserve => $parent],
            playoffStates: [
                'ESP2' => PlayoffState::NotStarted,
                'ESP3PO' => PlayoffState::NotStarted,
            ],
        );

        $plan = $this->planner->planFromSnapshot($snapshot, $this->spainConfig);

        // Reserve cascaded out of ESP2 into ESP3A or ESP3B.
        $cascade = null;
        foreach ($plan->moves as $move) {
            if ($move->teamId === $reserve
                && $move->reason === PromotionMove::REASON_RESERVE_CASCADE
            ) {
                $cascade = $move;
                break;
            }
        }
        $this->assertNotNull($cascade, 'Reserve should be cascaded down');
        $this->assertSame('ESP2', $cascade->fromCompetitionId);
        $this->assertContains($cascade->toCompetitionId, ['ESP3A', 'ESP3B']);

        // A compensation team from the cascade destination backfills ESP2.
        $compensation = null;
        foreach ($plan->moves as $move) {
            if ($move->reason === PromotionMove::REASON_CASCADE_COMPENSATION
                && $move->fromCompetitionId === $cascade->toCompetitionId
                && $move->toCompetitionId === 'ESP2'
            ) {
                $compensation = $move;
                break;
            }
        }
        $this->assertNotNull($compensation, 'ESP2 should be backfilled by a team from the cascade destination');

        // Parent left untouched.
        foreach ($plan->moves as $move) {
            $this->assertNotSame($parent, $move->teamId, 'Parent should not be moved');
        }
    }

    public function test_inherited_coexistence_in_legacy_game_promotes_parent_up_when_no_deeper_tier(): void
    {
        $esp1 = $this->ids(20, 'a1');
        $esp2 = $this->ids(22, 'a2');

        // Legacy game (no ESP3A/B in snapshot). Parent and reserve coexist
        // mid-table in ESP2. Cascade-down is impossible (no deeper tier),
        // so the planner must promote the parent up to ESP1 instead and
        // swap a non-relegating ESP1 team down to ESP2 to balance.
        $parent = $esp2[10];
        $reserve = $esp2[15];

        $snapshot = new CountrySeasonSnapshot(
            countryCode: 'ES',
            standingsByCompetition: [
                'ESP1' => $esp1,
                'ESP2' => $esp2,
            ],
            reserveToParent: [$reserve => $parent],
            playoffStates: [
                'ESP2' => PlayoffState::NotStarted,
                'ESP3PO' => PlayoffState::NotStarted,
            ],
        );

        $plan = $this->planner->planFromSnapshot($snapshot, $this->spainConfig);

        // Parent should be moved up to ESP1.
        $parentMove = null;
        foreach ($plan->moves as $move) {
            if ($move->teamId === $parent) {
                $parentMove = $move;
                break;
            }
        }
        $this->assertNotNull($parentMove, 'Parent should be moved');
        $this->assertSame('ESP2', $parentMove->fromCompetitionId);
        $this->assertSame('ESP1', $parentMove->toCompetitionId);

        // Backfill team comes from the lowest non-relegating ESP1 position
        // (positions 18-20 are relegating via Rule #1, so the bottom-up
        // walk picks position 17 = $esp1[16]).
        $backfillMove = null;
        foreach ($plan->moves as $move) {
            if ($move->fromCompetitionId === 'ESP1' && $move->toCompetitionId === 'ESP2'
                && $move->reason === PromotionMove::REASON_CASCADE_COMPENSATION
            ) {
                $backfillMove = $move;
                break;
            }
        }
        $this->assertNotNull($backfillMove, 'A non-relegating ESP1 team should backfill ESP2');
        $this->assertSame($esp1[16], $backfillMove->teamId);

        // Reserve untouched.
        foreach ($plan->moves as $move) {
            $this->assertNotSame($reserve, $move->teamId, 'Reserve should not be moved');
        }
    }

    public function test_legacy_game_cancels_relegation_when_reserve_in_destination_and_no_deeper_tier_in_snapshot(): void
    {
        $esp1 = $this->ids(20, 'a1');
        $esp2 = $this->ids(22, 'a2');

        // ESP1 position 18 (relegating) is the parent of a reserve sitting
        // in ESP2. In a full snapshot the cascade would push the reserve
        // down to ESP3A; here ESP3A/B are absent from the snapshot (legacy
        // game), so the cascade must escape-hatch instead of emitting a
        // phantom move into a non-existent league (which would leave ESP2
        // one team short and trip validatePlan's per-tier count check).
        $parent = $esp1[17];
        $reserve = $esp2[5];

        $snapshot = new CountrySeasonSnapshot(
            countryCode: 'ES',
            standingsByCompetition: [
                'ESP1' => $esp1,
                'ESP2' => $esp2,
            ],
            reserveToParent: [$reserve => $parent],
            playoffStates: [
                'ESP2' => PlayoffState::NotStarted,
                'ESP3PO' => PlayoffState::NotStarted,
            ],
        );

        $plan = $this->planner->planFromSnapshot($snapshot, $this->spainConfig);

        // Cancellation: the parent stays in ESP1 and one promotion is
        // dropped to keep tier counts balanced. So 2 promotions instead of
        // 3, and 2 relegations instead of 3.
        $this->assertCount(2, $plan->promotionsInto('ESP1'));
        $this->assertCount(2, $plan->relegationsInto('ESP2'));

        $this->assertCount(1, $plan->skippedRelegations);
        $this->assertSame($parent, $plan->skippedRelegations[0]->parentTeamId);
        $this->assertSame(
            SkippedRelegation::REASON_RESERVE_AT_FLOOR,
            $plan->skippedRelegations[0]->reason,
        );

        // No cascade move emitted into the absent tiers.
        foreach ($plan->moves as $move) {
            $this->assertNotContains($move->fromCompetitionId, ['ESP3A', 'ESP3B']);
            $this->assertNotContains($move->toCompetitionId, ['ESP3A', 'ESP3B']);
        }
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

    public function test_split_rule_escape_hatch_iterates_until_no_residual_coexistence(): void
    {
        // Production regression (planner produced a coexistence violation in
        // ESP3A): every ESP2 relegator has a reserve in ESP3A, so all four
        // need to land in ESP3B. ESP3B has only one promotion slot (1 direct
        // + both playoff winners coming from ESP3A), so three relegators
        // collide in the first assignment pass.
        //
        // The escape hatch cancels those three relegations and drops three
        // promotions. The drop removes ESP3B's only direct promoter, leaving
        // cap_B = 0 in the second assignment pass. The fourth relegator —
        // which sat safely in ESP3B in the first pass — now has nowhere to
        // go but the colliding sibling. Without iteration the planner emits
        // a plan with that residual collision and validatePlan trips.
        $esp1 = $this->ids(20, 'a1');
        $esp2 = $this->ids(18, 'a2');
        $parents = ['parent-A', 'parent-B', 'parent-C', 'parent-D'];
        // ESP2 positions 19-22 (indexes 18-21) all relegate.
        $esp2 = array_merge($esp2, $parents);

        // All four reserves sit in ESP3A. Place them past the direct-promotion
        // slot so they don't muddy the eligibility check; cap_A still adds up
        // to 3 (direct + 2 playoff winners) for the standard four-team flow.
        $reserves = ['reserve-A', 'reserve-B', 'reserve-C', 'reserve-D'];
        $esp3a = array_merge(
            [$this->ids(1, 'a3-top')[0]], // pos 1 (direct promoter)
            $reserves,                     // pos 2-5 (reserves of ESP2 relegators)
            $this->ids(15, 'a3-rest'),
        );
        $esp3b = $this->ids(20, 'b3');

        $snapshot = new CountrySeasonSnapshot(
            countryCode: 'ES',
            standingsByCompetition: [
                'ESP1' => $esp1,
                'ESP2' => $esp2,
                'ESP3A' => $esp3a,
                'ESP3B' => $esp3b,
            ],
            reserveToParent: [
                'reserve-A' => 'parent-A',
                'reserve-B' => 'parent-B',
                'reserve-C' => 'parent-C',
                'reserve-D' => 'parent-D',
            ],
            playoffStates: ['ESP2' => PlayoffState::NotStarted, 'ESP3PO' => PlayoffState::Completed],
            // Both bracket winners from ESP3A → cap_A = 3, cap_B = 1.
            playoffWinners: ['ESP3PO' => [$esp3a[6], $esp3a[7]]],
        );

        $plan = $this->planner->planFromSnapshot($snapshot, $this->spainConfig);

        // All four parents stay in ESP2 (every ESP3 slot would coexist with
        // their reserve once capacities collapse).
        foreach ($parents as $parent) {
            $this->assertNull(
                $this->findMove($plan, $parent),
                "Parent {$parent} should not relegate when capacity is exhausted",
            );
        }
        $this->assertCount(4, $plan->skippedRelegations);
        foreach ($plan->skippedRelegations as $skipped) {
            $this->assertSame(SkippedRelegation::REASON_RESERVE_AT_FLOOR, $skipped->reason);
        }

        $this->assertNoReserveParentCoexistenceAfterPlan($plan, $snapshot);
        $this->assertTierCountsPreserved($plan, $snapshot, $this->spainConfig);
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

    public function test_parent_relegating_into_already_relegating_reserve_tier_does_not_double_move_reserve(): void
    {
        // Production regression (game 03449aaa, season 2039): parent (e.g.
        // Real Sociedad) at ESP1 pos 18 is relegating to ESP2. Reserve (Real
        // Sociedad B) at ESP2 pos 22 is itself relegating to ESP3. The
        // per-rule cascade for rule #1 would naively emit a cascade move
        // pushing the reserve from ESP2 to ESP3 (parent's destination ==
        // reserve's current comp), but buildRuleMoves ALREADY emits a
        // relegation move for the reserve from rule #2's relegators. Two
        // moves for the same team trip validatePlan's no-double-move check.
        //
        // The reserve's own relegation leaves the collision tier naturally,
        // so the cascade is redundant — it must be skipped.
        $esp1 = $this->ids(20, 'a1');
        $parent = $esp1[17]; // pos 18, relegating to ESP2
        $reserve = 'reserve-of-' . $parent;
        // Reserve at the bottom of ESP2 (pos 22) — also relegating.
        $esp2 = array_merge($this->ids(21, 'a2'), [$reserve]);

        $snapshot = new CountrySeasonSnapshot(
            countryCode: 'ES',
            standingsByCompetition: [
                'ESP1' => $esp1,
                'ESP2' => $esp2,
                'ESP3A' => $this->ids(20, 'a3'),
                'ESP3B' => $this->ids(20, 'b3'),
            ],
            reserveToParent: [$reserve => $parent],
            playoffStates: [
                'ESP2' => PlayoffState::NotStarted,
                'ESP3PO' => PlayoffState::NotStarted,
            ],
        );

        $plan = $this->planner->planFromSnapshot($snapshot, $this->spainConfig);

        // Reserve has exactly one move: its own relegation ESP2 → ESP3*.
        $reserveMoves = array_values(array_filter(
            $plan->moves,
            fn (PromotionMove $m) => $m->teamId === $reserve,
        ));
        $this->assertCount(1, $reserveMoves, 'Reserve should only appear in one move');
        $this->assertSame(PromotionMove::REASON_RELEGATION, $reserveMoves[0]->reason);
        $this->assertSame('ESP2', $reserveMoves[0]->fromCompetitionId);
        $this->assertContains($reserveMoves[0]->toCompetitionId, ['ESP3A', 'ESP3B']);

        // Parent still relegates to ESP2.
        $parentMove = $this->findMove($plan, $parent);
        $this->assertNotNull($parentMove);
        $this->assertSame('ESP2', $parentMove->toCompetitionId);

        $this->assertNoTeamMovedTwice($plan);
        $this->assertNoReserveParentCoexistenceAfterPlan($plan, $snapshot);
        $this->assertTierCountsPreserved($plan, $snapshot, $this->spainConfig);
    }

    public function test_inherited_coexistence_skipped_when_reserve_is_relegating(): void
    {
        // Pre-pass companion to the above: reserve and parent already
        // coexist in ESP2 (data drift), parent is mid-table, but reserve
        // is at ESP2 pos 22 — relegating to ESP3 under its own rule. The
        // pre-pass must not cascade the reserve, because rule #2's
        // relegation move already moves it out of the coexistence tier.
        $esp1 = $this->ids(20, 'a1');
        $parent = 'midtable-parent';
        $reserve = 'relegating-reserve';
        // Parent mid-table, reserve at bottom (pos 22) — both in ESP2.
        $esp2 = array_merge(
            $this->ids(10, 'a2-top'),
            [$parent],
            $this->ids(10, 'a2-mid'),
            [$reserve],
        );

        $snapshot = new CountrySeasonSnapshot(
            countryCode: 'ES',
            standingsByCompetition: [
                'ESP1' => $esp1,
                'ESP2' => $esp2,
                'ESP3A' => $this->ids(20, 'a3'),
                'ESP3B' => $this->ids(20, 'b3'),
            ],
            reserveToParent: [$reserve => $parent],
            playoffStates: [
                'ESP2' => PlayoffState::NotStarted,
                'ESP3PO' => PlayoffState::NotStarted,
            ],
        );

        $plan = $this->planner->planFromSnapshot($snapshot, $this->spainConfig);

        // Reserve has exactly one move (its own relegation).
        $reserveMoves = array_values(array_filter(
            $plan->moves,
            fn (PromotionMove $m) => $m->teamId === $reserve,
        ));
        $this->assertCount(1, $reserveMoves);
        $this->assertSame(PromotionMove::REASON_RELEGATION, $reserveMoves[0]->reason);

        // Parent untouched.
        $this->assertNull($this->findMove($plan, $parent));

        $this->assertNoTeamMovedTwice($plan);
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
