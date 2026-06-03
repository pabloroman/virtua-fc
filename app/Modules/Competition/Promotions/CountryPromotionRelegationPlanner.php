<?php

namespace App\Modules\Competition\Promotions;

use App\Modules\Competition\Enums\PlayoffState;
use App\Modules\Competition\Exceptions\PlayoffInProgressException;
use App\Modules\Competition\Exceptions\ReserveParentCoexistenceException;

/**
 * Pure country-aware planner for end-of-season promotion/relegation.
 *
 * Input: a {@see CountrySeasonSnapshot} (final standings, reserve/parent map,
 * playoff states + winners) and the country's `tiers` + `promotions` config.
 *
 * Output: a {@see PromotionRelegationPlan} listing every team move plus any
 * relegations the escape hatch cancelled.
 *
 * The planner does not read or write the database. The processor's executor
 * applies the plan inside a transaction.
 *
 * Invariants enforced before returning a plan:
 *   - Each tier (and sibling) ends with its configured team count.
 *   - No team appears as source or destination of two moves.
 *   - No reserve shares a competition with its parent.
 *   - No team is both source and destination of the same move.
 *
 * Game-design rules baked in:
 *   1. Reserve cannot sit in or be promoted to the same competition as its parent.
 *   2. If a parent's relegation would land it in its reserve's competition,
 *      the reserve cascades down one tier (with a compensating non-reserve
 *      promotion to keep counts balanced).
 *   3. If the reserve is already at the deepest tier and cannot cascade,
 *      the parent's relegation is cancelled, and the lowest-priority promotion
 *      from the same rule is cancelled too to preserve tier counts.
 */
class CountryPromotionRelegationPlanner
{
    /**
     * @param  array<string, mixed>  $config  A country config block from
     *     config('countries').{country_code}. Expected keys: `tiers`, `promotions`.
     */
    public function planFromSnapshot(CountrySeasonSnapshot $snapshot, array $config): PromotionRelegationPlan
    {
        $promotionRules = $config['promotions'] ?? [];
        if (empty($promotionRules)) {
            return new PromotionRelegationPlan($snapshot->countryCode, []);
        }

        // Drop rules whose tiers aren't all present in the snapshot. The
        // snapshot builder skips tiers that are structurally absent for a
        // given game (legacy games predating a tier addition), and a rule
        // that references a missing tier has no work to do — running it
        // would just produce empty/unbalanced moves. This is the general
        // case of "no division below to relegate to".
        $promotionRules = array_values($this->filterApplicableRules($promotionRules, $snapshot));
        if (empty($promotionRules)) {
            return new PromotionRelegationPlan($snapshot->countryCode, []);
        }

        $tierStructure = $this->buildTierStructure($config);

        // Step 1: Reject any promotion rule whose playoff is mid-flight. Doing
        // this up front turns a tangled half-applied transaction into a clear
        // signal the caller can defer the whole pipeline on.
        $this->assertNoPlayoffInProgress($snapshot, $promotionRules);

        // Step 2: Pre-compute the relegators for every rule. We need ALL rules'
        // relegators before computing any promoters, so each rule's reserve
        // filter can see what's about to land in its destination from other
        // rules (the cross-rule "incoming" context).
        $relegatorsByRule = [];
        foreach ($promotionRules as $i => $rule) {
            $relegatorsByRule[$i] = $this->initialRelegators($snapshot, $rule);
        }

        $incomingByDestination = $this->buildIncomingMap($promotionRules, $relegatorsByRule);

        // Step 3: Compute promoters with the incoming context.
        $promotersByRule = [];
        foreach ($promotionRules as $i => $rule) {
            $promotersByRule[$i] = $this->computePromoters(
                $snapshot,
                $rule,
                $incomingByDestination,
            );
        }

        // Steps 4-5: Iterate reserve protection + cancellation until stable.
        //
        // A single pass isn't enough on split rules (ESP2 ↔ ESP3A/ESP3B): the
        // first cancellation pass also drops promotions, which shrinks the
        // capacity of one of the sibling destinations. Recomputing the split
        // assignment with the reduced capacity can push a previously
        // non-colliding parent into the colliding sibling — but the original
        // resolveReserveProtection only saw the pre-cancellation assignment,
        // so that residual collision is never escape-hatched and trips
        // validatePlan's coexistence check.
        //
        // Re-running resolveReserveProtection against the post-cancellation
        // state surfaces those residual collisions. The loop terminates
        // because each iteration either applies new cancellations (strictly
        // reducing relegators + promoters) or stabilises.
        $skippedRelegations = [];
        $cascadeMoves = [];
        $compensationMoves = [];
        $iterationLimit = $this->reserveProtectionIterationLimit($relegatorsByRule);

        for ($iter = 0; $iter <= $iterationLimit; $iter++) {
            [$cascadeMoves, $compensationMoves, $cancellations] = $this->resolveReserveProtection(
                $snapshot,
                $tierStructure,
                $promotionRules,
                $relegatorsByRule,
                $promotersByRule,
            );

            $hadCancellation = false;
            foreach ($cancellations['relegations'] as $ruleIdx => $cancelledTeamIds) {
                if (empty($cancelledTeamIds)) {
                    continue;
                }
                $relegatorsByRule[$ruleIdx] = array_values(
                    array_diff($relegatorsByRule[$ruleIdx], $cancelledTeamIds),
                );
                $hadCancellation = true;
            }
            foreach ($cancellations['promotionsCount'] as $ruleIdx => $count) {
                if ($count <= 0) {
                    continue;
                }
                // Drop the lowest-priority promoters (end of list = playoff
                // winners / lowest-seeded direct slot, depending on rule order).
                $current = $promotersByRule[$ruleIdx];
                $keep = max(0, count($current) - $count);
                $promotersByRule[$ruleIdx] = array_slice($current, 0, $keep);
                $hadCancellation = true;
            }
            $skippedRelegations = array_merge($skippedRelegations, $cancellations['skipped']);

            if (!$hadCancellation) {
                break;
            }
        }

        // Step 6: Build the rule-driven moves (relegations + promotions).
        $ruleMoves = $this->buildRuleMoves(
            $snapshot,
            $tierStructure,
            $promotionRules,
            $relegatorsByRule,
            $promotersByRule,
        );

        // Step 7: Merge in cascade + compensation moves.
        $moves = array_merge($ruleMoves, $cascadeMoves, $compensationMoves);

        $touched = $this->touchedCompetitions($moves);

        $plan = new PromotionRelegationPlan(
            countryCode: $snapshot->countryCode,
            moves: array_values($moves),
            skippedRelegations: $skippedRelegations,
            touchedCompetitionIds: $touched,
        );

        $this->validatePlan($plan, $snapshot, $tierStructure);

        return $plan;
    }

    /**
     * Upper bound on reserve-protection iterations. Each iteration that
     * makes progress strictly reduces relegators (and matching promoters),
     * so the loop terminates in at most one iteration per relegator plus
     * one stabilisation pass.
     *
     * @param  array<int, list<string>>  $relegatorsByRule
     */
    private function reserveProtectionIterationLimit(array $relegatorsByRule): int
    {
        $total = 0;
        foreach ($relegatorsByRule as $ids) {
            $total += count($ids);
        }
        return $total + 1;
    }

    /**
     * Drop promotion rules whose top, bottom, or sibling tier isn't in the
     * snapshot. The snapshot builder omits tiers that have no entries for
     * the game (a legacy game predating that tier's addition), so a rule
     * referencing one of them has nowhere to move teams to or from. This is
     * the general "no division below" case — a rule whose bottom_division
     * doesn't exist for this game (e.g. ESP2↔ESP3 in a pre-Primera-RFEF
     * Spanish game) can't relegate anyone, so it's skipped.
     *
     * playoff_competition is intentionally not validated: it's a cup
     * (e.g. ESP3PO), not a league tier, and lives outside standingsByCompetition.
     *
     * @param  list<array<string, mixed>>  $rules
     * @return list<array<string, mixed>>
     */
    private function filterApplicableRules(array $rules, CountrySeasonSnapshot $snapshot): array
    {
        $applicable = [];
        foreach ($rules as $rule) {
            $required = [$rule['top_division'], $rule['bottom_division']];
            if (!empty($rule['playoff_source_divisions'])) {
                $required = array_merge($required, $rule['playoff_source_divisions']);
            }
            $allPresent = true;
            foreach ($required as $comp) {
                if (!array_key_exists($comp, $snapshot->standingsByCompetition)) {
                    $allPresent = false;
                    break;
                }
            }
            if ($allPresent) {
                $applicable[] = $rule;
            }
        }
        return $applicable;
    }

    // ──────────────────────────────────────────────────
    // Step builders
    // ──────────────────────────────────────────────────

    /**
     * @param  array<int, array<string, mixed>>  $promotionRules
     */
    private function assertNoPlayoffInProgress(CountrySeasonSnapshot $snapshot, array $promotionRules): void
    {
        foreach ($promotionRules as $rule) {
            $playoffComp = $rule['playoff_competition'] ?? $rule['bottom_division'];
            if ($snapshot->playoffState($playoffComp) === PlayoffState::InProgress) {
                throw PlayoffInProgressException::forCompetition($playoffComp);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $rule
     * @return list<string>  Team IDs at this rule's relegation positions in the top division.
     */
    private function initialRelegators(CountrySeasonSnapshot $snapshot, array $rule): array
    {
        $standings = $snapshot->standings($rule['top_division']);
        $out = [];
        foreach ($rule['relegated_positions'] as $position) {
            $idx = $position - 1;
            if (isset($standings[$idx])) {
                $out[] = $standings[$idx];
            }
        }
        return $out;
    }

    /**
     * @param  array<int, array<string, mixed>>  $promotionRules
     * @param  array<int, list<string>>  $relegatorsByRule
     * @return array<string, list<string>>  destination_competition_id => incoming team IDs.
     */
    private function buildIncomingMap(array $promotionRules, array $relegatorsByRule): array
    {
        $incoming = [];
        foreach ($promotionRules as $i => $rule) {
            $destinations = $this->relegationDestinations($rule);
            foreach ($relegatorsByRule[$i] as $teamId) {
                foreach ($destinations as $dest) {
                    $incoming[$dest][] = $teamId;
                }
            }
        }
        foreach ($incoming as $dest => $ids) {
            $incoming[$dest] = array_values(array_unique($ids));
        }
        return $incoming;
    }

    /**
     * Where this rule's relegated teams MAY land. For simple rules, just
     * the bottom division. For split rules (PrimeraRFEF) the relegated set
     * spreads across `playoff_source_divisions`.
     *
     * @param  array<string, mixed>  $rule
     * @return list<string>
     */
    private function relegationDestinations(array $rule): array
    {
        return $rule['playoff_source_divisions'] ?? [$rule['bottom_division']];
    }

    /**
     * Compute the promoters for one rule: direct slots + playoff slots,
     * with reserve filtering against parents currently in or about to land in
     * the destination competition.
     *
     * @param  array<string, mixed>  $rule
     * @param  array<string, list<string>>  $incomingByDestination
     * @return list<array{teamId: string, kind: string, source: string}>
     *     `kind` is one of REASON_PROMOTION / REASON_PROMOTION_PLAYOFF.
     *     `source` is the competition the team is currently in.
     */
    private function computePromoters(CountrySeasonSnapshot $snapshot, array $rule, array $incomingByDestination): array
    {
        $isSplit = !empty($rule['playoff_source_divisions']);

        if ($isSplit) {
            return $this->computeSplitPromoters($snapshot, $rule, $incomingByDestination);
        }

        return $this->computeSimplePromoters($snapshot, $rule, $incomingByDestination);
    }

    /**
     * Standard one-up-one-down rule: walk the bottom division, skip reserves
     * whose parent is in the top division or is about to be relegated there,
     * take direct + playoff slots in standings order. Playoff branch picks
     * winners from the snapshot when state == Completed, falls back to
     * stand-ins when NotStarted.
     *
     * @return list<array{teamId: string, kind: string, source: string}>
     */
    private function computeSimplePromoters(CountrySeasonSnapshot $snapshot, array $rule, array $incomingByDestination): array
    {
        $topDiv = $rule['top_division'];
        $bottomDiv = $rule['bottom_division'];
        $playoffComp = $rule['playoff_competition'] ?? $bottomDiv;
        $directCount = (int) ($rule['direct_count'] ?? 0);
        $playoffCount = (int) ($rule['playoff_count'] ?? 0);

        $topRoster = $snapshot->standings($topDiv);
        $incoming = $incomingByDestination[$topDiv] ?? [];

        // Effective top roster: who will be in $topDiv after relegations from
        // other rules land. We subtract this rule's own relegators (they'll
        // leave $topDiv) and add incoming teams (other rules' relegators
        // landing here).
        $effectiveTopRoster = $this->effectiveTopRoster($snapshot, $rule, $topRoster, $incoming);

        $eligible = $this->eligibleInOrder(
            $snapshot,
            $snapshot->standings($bottomDiv),
            $effectiveTopRoster,
        );

        $direct = array_slice($eligible, 0, $directCount);

        $playoffSlots = $this->resolvePlayoffSlots(
            $snapshot,
            $playoffComp,
            $eligible,
            $direct,
            $playoffCount,
        );

        $out = [];
        foreach ($direct as $teamId) {
            $out[] = ['teamId' => $teamId, 'kind' => PromotionMove::REASON_PROMOTION, 'source' => $bottomDiv];
        }
        foreach ($playoffSlots as $teamId) {
            $out[] = ['teamId' => $teamId, 'kind' => PromotionMove::REASON_PROMOTION_PLAYOFF, 'source' => $bottomDiv];
        }
        return $out;
    }

    /**
     * PrimeraRFEF-style split rule: 1 direct from each of `playoff_source_divisions`
     * (e.g. ESP3A pos 1, ESP3B pos 1) plus 2 playoff winners from `playoff_competition`
     * (ESP3PO). Each direct slot is computed against its own group's standings;
     * the playoff branch picks from ESP3PO.
     *
     * @return list<array{teamId: string, kind: string, source: string}>
     */
    private function computeSplitPromoters(CountrySeasonSnapshot $snapshot, array $rule, array $incomingByDestination): array
    {
        $topDiv = $rule['top_division'];
        $playoffComp = $rule['playoff_competition'] ?? $rule['bottom_division'];
        $directCount = (int) ($rule['direct_count'] ?? 1);
        $playoffCount = (int) ($rule['playoff_count'] ?? 0);
        $sources = $rule['playoff_source_divisions'];

        // Effective top roster (ESP2 minus this rule's relegators, plus other
        // rules' incoming). Each direct slot uses this to block reserves whose
        // parent is going to share the destination.
        $topRoster = $snapshot->standings($topDiv);
        $incoming = $incomingByDestination[$topDiv] ?? [];
        $effectiveTopRoster = $this->effectiveTopRoster($snapshot, $rule, $topRoster, $incoming);

        // Pre-pick the tentative direct promoters per source so we can extend
        // the effective top roster with them. Without this the per-source
        // filters can't see each other: if ESP3A's top is a reserve and
        // ESP3B's top is its parent, both pass their individual filters
        // (each parent/reserve is currently outside ESP2) and both land in
        // ESP2 together, tripping validatePlan's coexistence check.
        // The extended roster makes the parent visible to the reserve's
        // source filter, which then skips the reserve in favour of the
        // next eligible team in that group.
        $tentativeDirects = [];
        foreach ($sources as $sourceDiv) {
            $eligible = $this->eligibleInOrder(
                $snapshot,
                $snapshot->standings($sourceDiv),
                $effectiveTopRoster,
            );
            foreach (array_slice($eligible, 0, $directCount) as $tid) {
                $tentativeDirects[] = $tid;
            }
        }
        $extendedTopRoster = array_values(array_unique(
            array_merge($effectiveTopRoster, $tentativeDirects),
        ));

        $out = [];

        foreach ($sources as $sourceDiv) {
            $eligible = $this->eligibleInOrder(
                $snapshot,
                $snapshot->standings($sourceDiv),
                $extendedTopRoster,
            );

            $sourceDirect = array_slice($eligible, 0, $directCount);
            foreach ($sourceDirect as $teamId) {
                $out[] = ['teamId' => $teamId, 'kind' => PromotionMove::REASON_PROMOTION, 'source' => $sourceDiv];
            }
        }

        // Playoff slots: 2 winners from ESP3PO. The bracket spans both
        // groups, so winners may come from either source. The playoff
        // count here is per-group (4 per group, so the bracket has 8 entrants
        // total) but only 2 winners (the two bracket finals). For now we
        // expose the snapshot's ESP3PO winners list, which the test fixtures
        // populate with the expected (teamId, sourceGroup) entries.
        //
        // The extended top roster (including the directs we just emitted)
        // also guards real playoff winners and stand-ins: a bracket winner
        // that's a reserve of one of the directs gets skipped in favour of
        // the next non-conflicting winner / stand-in.
        $state = $snapshot->playoffState($playoffComp);
        $playoffPickCount = 2; // Two bracket finals → two promotion spots.

        $playoffPicks = match ($state) {
            PlayoffState::Completed => $this->primeraRfefCompletedWinners(
                $snapshot,
                $playoffComp,
                $playoffPickCount,
                array_column($out, 'teamId'),
                $sources,
                $directCount,
                $extendedTopRoster,
            ),
            PlayoffState::NotStarted => $this->primeraRfefStandIns(
                $snapshot,
                $sources,
                $directCount,
                $extendedTopRoster,
                array_column($out, 'teamId'),
                $playoffPickCount,
            ),
            PlayoffState::InProgress => throw PlayoffInProgressException::forCompetition($playoffComp),
        };

        foreach ($playoffPicks as $pick) {
            $out[] = [
                'teamId' => $pick['teamId'],
                'kind' => PromotionMove::REASON_PROMOTION_PLAYOFF,
                'source' => $pick['source'],
            ];
        }

        return $out;
    }

    /**
     * Walk a competition's standings and return the eligible team IDs in order,
     * skipping reserve teams whose parent is in the effective top roster.
     *
     * @param  list<string>  $standings
     * @param  list<string>  $effectiveTopRoster
     * @return list<string>
     */
    private function eligibleInOrder(CountrySeasonSnapshot $snapshot, array $standings, array $effectiveTopRoster): array
    {
        $top = array_flip($effectiveTopRoster);
        $out = [];
        foreach ($standings as $teamId) {
            $parent = $snapshot->parentOf($teamId);
            if ($parent !== null && isset($top[$parent])) {
                continue;
            }
            $out[] = $teamId;
        }
        return $out;
    }

    /**
     * The reference set the reserve filter uses for promotions into this top
     * division. Includes the current top roster (so a reserve whose parent is
     * already there is blocked) plus other rules' incoming relegators (so a
     * reserve whose parent is about to land there via a sibling rule — the
     * pro-manager regression — is also blocked).
     *
     * Crucially we do NOT subtract this rule's own relegators. If the parent
     * is being relegated and the reserve is being promoted, the two would
     * cross paths and end up inverted (parent below reserve), which violates
     * the always-above invariant just as badly as coexistence. Blocking the
     * reserve here leaves the cascade pass to push the reserve down a tier
     * instead.
     *
     * @param  list<string>  $topRoster  Current standings of $rule['top_division'].
     * @param  list<string>  $incoming  Teams from other rules' relegators headed
     *     to this top division (always empty in single-rule countries).
     * @return list<string>
     */
    private function effectiveTopRoster(CountrySeasonSnapshot $snapshot, array $rule, array $topRoster, array $incoming): array
    {
        if (empty($incoming)) {
            return $topRoster;
        }

        return array_values(array_unique(array_merge($topRoster, $incoming)));
    }

    /**
     * Resolve the $playoffCount playoff slots for a simple rule, picking from
     * the eligible pool the standings allocator already filtered. Three lifecycle
     * branches mirror the existing system: real winners on Completed, throw on
     * InProgress, stand-ins on NotStarted.
     *
     * @param  list<string>  $eligible
     * @param  list<string>  $directAlreadyTaken
     * @return list<string>
     */
    private function resolvePlayoffSlots(
        CountrySeasonSnapshot $snapshot,
        string $playoffComp,
        array $eligible,
        array $directAlreadyTaken,
        int $playoffCount,
    ): array {
        if ($playoffCount === 0) {
            return [];
        }

        $state = $snapshot->playoffState($playoffComp);

        return match ($state) {
            PlayoffState::Completed => $this->completedPlayoffSlots(
                $snapshot,
                $playoffComp,
                $eligible,
                $directAlreadyTaken,
                1,
            ),
            PlayoffState::InProgress => throw PlayoffInProgressException::forCompetition($playoffComp),
            PlayoffState::NotStarted => array_slice(
                array_values(array_diff($eligible, $directAlreadyTaken)),
                0,
                1, // One stand-in (the bracket produces one winner from the playoff range)
            ),
        };
    }

    /**
     * Build the playoff-promoted team list for the Completed branch, skipping
     * any winner that's already a direct promoter and falling back to the next
     * eligible team in standings order to keep the promotion count consistent.
     *
     * The bracket is seeded mid-season (at the trigger matchday). A team
     * seeded into the playoff range can climb into direct-promotion territory
     * by season-end and then win the bracket — without this dedupe the planner
     * would emit two promotion moves for the same team and trip the
     * no-double-move invariant. The direct promotion (earned by final
     * standings) is treated as the stronger claim; the playoff slot falls
     * through to the next eligible team.
     *
     * @param  list<string>  $eligible
     * @param  list<string>  $directAlreadyTaken
     * @return list<string>
     */
    private function completedPlayoffSlots(
        CountrySeasonSnapshot $snapshot,
        string $playoffComp,
        array $eligible,
        array $directAlreadyTaken,
        int $slots,
    ): array {
        $taken = array_flip($directAlreadyTaken);
        $out = [];

        foreach ($snapshot->playoffWinners($playoffComp) as $teamId) {
            if (isset($taken[$teamId])) {
                continue;
            }
            $out[] = $teamId;
            $taken[$teamId] = true;
            if (count($out) >= $slots) {
                return $out;
            }
        }

        // Fallback: at least one winner was also a direct promoter, leaving
        // the playoff slot short. Pull the next eligible team(s) past the
        // direct range to fill the gap.
        foreach (array_slice($eligible, count($directAlreadyTaken)) as $teamId) {
            if (isset($taken[$teamId])) {
                continue;
            }
            $out[] = $teamId;
            $taken[$teamId] = true;
            if (count($out) >= $slots) {
                break;
            }
        }

        return $out;
    }

    /**
     * Build playoff-promoted picks for the split (PrimeraRFEF) rule, skipping
     * any bracket winner that's already a direct promoter from one of the
     * feeder groups. Same rationale as {@see completedPlayoffSlots}: bracket
     * seeding is a mid-season snapshot and the standings can shift before
     * season-end. Stand-in logic fills any gaps left by the dedupe so the
     * total promotion count stays consistent.
     *
     * @param  list<string>  $directAlreadyTaken
     * @param  list<string>  $sources
     * @param  list<string>  $effectiveTopRoster
     * @return list<array{teamId: string, source: string}>
     */
    private function primeraRfefCompletedWinners(
        CountrySeasonSnapshot $snapshot,
        string $playoffComp,
        int $count,
        array $directAlreadyTaken,
        array $sources,
        int $directCount,
        array $effectiveTopRoster,
    ): array {
        $taken = array_flip($directAlreadyTaken);
        $topRosterSet = array_flip($effectiveTopRoster);
        $out = [];

        foreach ($snapshot->playoffWinners($playoffComp) as $teamId) {
            if (isset($taken[$teamId])) {
                continue;
            }
            // Skip winners whose parent is already going to the top division
            // (either via this rule's directs, or via another rule's
            // incoming relegators that the extended roster carries). The
            // stand-in fallback below picks up the freed slot.
            $parent = $snapshot->parentOf($teamId);
            if ($parent !== null && isset($topRosterSet[$parent])) {
                continue;
            }
            $source = $snapshot->competitionOf($teamId) ?? '';
            $out[] = ['teamId' => $teamId, 'source' => $source];
            $taken[$teamId] = true;
            if (count($out) >= $count) {
                return $out;
            }
        }

        if (count($out) < $count) {
            $standIns = $this->primeraRfefStandIns(
                $snapshot,
                $sources,
                $directCount,
                $effectiveTopRoster,
                array_keys($taken),
                $count - count($out),
            );
            foreach ($standIns as $pick) {
                $out[] = $pick;
            }
        }

        return $out;
    }

    /**
     * @param  list<string>  $sources
     * @param  list<string>  $effectiveTopRoster
     * @param  list<string>  $alreadyPromoted
     * @return list<array{teamId: string, source: string}>
     */
    private function primeraRfefStandIns(
        CountrySeasonSnapshot $snapshot,
        array $sources,
        int $directCount,
        array $effectiveTopRoster,
        array $alreadyPromoted,
        int $count,
    ): array {
        $taken = array_flip($alreadyPromoted);
        $out = [];

        foreach ($sources as $source) {
            if (count($out) >= $count) {
                break;
            }
            $standings = $snapshot->standings($source);
            // Skip the direct-promotion slot(s).
            $eligible = $this->eligibleInOrder($snapshot, $standings, $effectiveTopRoster);
            $candidates = array_slice($eligible, $directCount);
            foreach ($candidates as $teamId) {
                if (isset($taken[$teamId])) {
                    continue;
                }
                $out[] = ['teamId' => $teamId, 'source' => $source];
                $taken[$teamId] = true;
                if (count($out) >= $count) {
                    break;
                }
            }
        }
        return $out;
    }

    // ──────────────────────────────────────────────────
    // Reserve protection (cascade / escape hatch)
    // ──────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $tierStructure
     * @param  array<int, array<string, mixed>>  $promotionRules
     * @param  array<int, list<string>>  $relegatorsByRule
     * @param  array<int, list<array{teamId: string, kind: string, source: string}>>  $promotersByRule
     * @return array{0: list<PromotionMove>, 1: list<PromotionMove>, 2: array{relegations: array<int, list<string>>, promotionsCount: array<int, int>, skipped: list<SkippedRelegation>}}
     */
    private function resolveReserveProtection(
        CountrySeasonSnapshot $snapshot,
        array $tierStructure,
        array $promotionRules,
        array $relegatorsByRule,
        array $promotersByRule,
    ): array {
        $cascadeMoves = [];
        $compensationMoves = [];
        $cancellations = [
            'relegations' => [],
            'promotionsCount' => [],
            'skipped' => [],
        ];

        // Track which teams already used as cascade or compensation, plus
        // teams already being promoted by any rule (so we don't backfill with
        // a team that's about to move up anyway).
        $usedAsCompensation = [];
        $alreadyPromoted = [];
        foreach ($promotersByRule as $promoters) {
            foreach ($promoters as $p) {
                $alreadyPromoted[$p['teamId']] = true;
            }
        }

        // Track planned destinations within this loop so cascading reserves
        // don't pick the same group as their parent for the same rule.
        $relegationDestByTeam = [];

        // Pre-pass: inherited coexistence in the snapshot. Pairs where the
        // reserve and parent already share a competition before the planner
        // runs (data carried over from earlier seasons before the planner
        // became strict). validatePlan trips on these even when no rule
        // would move either team, so resolve them up front with the same
        // cascade-down repair the relegation case uses.
        //
        // Skip pairs the per-rule loop will resolve naturally:
        //   - parent being relegated: it leaves the coexistence tier, so
        //     coexistence ends without any reserve-side action.
        //   - reserve being promoted: it leaves the coexistence tier
        //     naturally too (the resulting inverted pair — reserve in
        //     a higher tier than parent — is a separate concern; this
        //     pass scopes to same-tier coexistence only).
        $beingRelegated = [];
        foreach ($relegatorsByRule as $teamIds) {
            foreach ($teamIds as $tid) {
                $beingRelegated[$tid] = true;
            }
        }
        foreach ($snapshot->reserveToParent as $reserve => $parent) {
            // Skip pairs the per-rule loop will resolve naturally:
            //   - parent being relegated: leaves the coexistence tier.
            //   - parent being promoted: leaves the coexistence tier.
            //   - reserve being relegated: leaves the coexistence tier.
            //   - reserve being promoted: leaves the coexistence tier.
            // In all four cases the post-plan finalComp puts the two
            // teams in different competitions without any repair move —
            // emitting a cascade move for a team that already has a
            // relegation/promotion move trips validatePlan's
            // no-double-move check.
            if (isset($beingRelegated[$parent])) {
                continue;
            }
            if (isset($beingRelegated[$reserve])) {
                continue;
            }
            if (isset($alreadyPromoted[$parent])) {
                continue;
            }
            if (isset($alreadyPromoted[$reserve])) {
                continue;
            }

            $reserveCurrentComp = $snapshot->competitionOf($reserve);
            $parentCurrentComp = $snapshot->competitionOf($parent);
            if ($reserveCurrentComp === null || $parentCurrentComp === null) {
                continue;
            }
            if ($reserveCurrentComp !== $parentCurrentComp) {
                continue;
            }

            $reserveTier = $tierStructure['tierByCompetition'][$reserveCurrentComp] ?? null;
            if ($reserveTier === null) {
                continue;
            }

            // Repair strategy A — cascade reserve down to the tier below
            // (preferred when a deeper tier is present in the snapshot).
            $deeperTier = $reserveTier + 1;
            $deeperComps = array_values(array_filter(
                $tierStructure['competitionsByTier'][$deeperTier] ?? [],
                fn ($comp) => array_key_exists($comp, $snapshot->standingsByCompetition),
            ));

            if (!empty($deeperComps)) {
                $cascadeDest = $this->chooseCascadeDestination(
                    $deeperComps,
                    $snapshot,
                    $parent,
                );

                $cascadeMoves[] = new PromotionMove(
                    teamId: $reserve,
                    fromCompetitionId: $reserveCurrentComp,
                    toCompetitionId: $cascadeDest,
                    reason: PromotionMove::REASON_RESERVE_CASCADE,
                );

                $compTeam = $this->pickCompensation(
                    $snapshot,
                    $cascadeDest,
                    $reserveCurrentComp,
                    $usedAsCompensation,
                    $alreadyPromoted,
                    $reserve,
                );
                if ($compTeam !== null) {
                    $compensationMoves[] = new PromotionMove(
                        teamId: $compTeam,
                        fromCompetitionId: $cascadeDest,
                        toCompetitionId: $reserveCurrentComp,
                        reason: PromotionMove::REASON_CASCADE_COMPENSATION,
                    );
                    $usedAsCompensation[$compTeam] = true;
                }
                continue;
            }

            // Repair strategy B — promote parent up to the tier above.
            // Used when cascade-down is impossible (coexistence at the
            // deepest tier present in the snapshot — typically a legacy
            // game whose deeper tiers were never seeded, e.g. ESP3A/ESP3B
            // missing from a pre-Primera-RFEF Spanish game).
            //
            // Reuses RESERVE_CASCADE / CASCADE_COMPENSATION reasons even
            // though the directions are inverted. The named buckets in
            // PromotionRelegationQuery::summaryForCompetition key on
            // from/to competition IDs (not reason), so these repair
            // moves don't show up in the user-facing promoted/relegated
            // lists — which is the right behaviour (the parent didn't
            // "earn" the promotion, and the backfill team didn't "earn"
            // the relegation; this is silent data-drift repair).
            $shallowerTier = $reserveTier - 1;
            $shallowerComps = array_values(array_filter(
                $tierStructure['competitionsByTier'][$shallowerTier] ?? [],
                fn ($comp) => array_key_exists($comp, $snapshot->standingsByCompetition),
            ));
            if (empty($shallowerComps)) {
                // Coexistence at the only tier present in the snapshot
                // (no tier above OR below). Cannot self-repair; leave
                // for validatePlan to surface so the operator runs the
                // one-off repair command.
                continue;
            }

            $promotionDest = $this->chooseCascadeDestination(
                $shallowerComps,
                $snapshot,
                $reserve,
            );

            $backfillTeam = $this->pickPromotionBackfill(
                $snapshot,
                $promotionDest,
                $reserveCurrentComp,
                $beingRelegated,
                $usedAsCompensation,
                $alreadyPromoted,
                $parent,
            );
            if ($backfillTeam === null) {
                continue;
            }

            $cascadeMoves[] = new PromotionMove(
                teamId: $parent,
                fromCompetitionId: $reserveCurrentComp,
                toCompetitionId: $promotionDest,
                reason: PromotionMove::REASON_RESERVE_CASCADE,
            );
            $compensationMoves[] = new PromotionMove(
                teamId: $backfillTeam,
                fromCompetitionId: $promotionDest,
                toCompetitionId: $reserveCurrentComp,
                reason: PromotionMove::REASON_CASCADE_COMPENSATION,
            );
            $usedAsCompensation[$backfillTeam] = true;
        }

        foreach ($promotionRules as $i => $rule) {
            $isSplit = !empty($rule['playoff_source_divisions']);
            $destinations = $this->relegationDestinations($rule);

            // For split rules, decide the per-team destination greedily,
            // preferring the group the team's reserve is NOT in.
            $teamDestinations = $isSplit
                ? $this->assignSplitRelegationDestinations($snapshot, $rule, $relegatorsByRule[$i], $promotersByRule[$i])
                : array_fill_keys($relegatorsByRule[$i], $destinations[0]);

            $relegationDestByTeam += $teamDestinations;

            foreach ($relegatorsByRule[$i] as $parentCandidate) {
                $reserve = $this->findReserve($snapshot, $parentCandidate);
                if ($reserve === null) {
                    continue;
                }

                $reserveCurrentComp = $snapshot->competitionOf($reserve);
                if ($reserveCurrentComp === null) {
                    continue;
                }

                $parentDest = $teamDestinations[$parentCandidate] ?? $destinations[0];

                if ($reserveCurrentComp !== $parentDest) {
                    // Parent landing in a different competition than the reserve.
                    // For sibling tiers (ESP3A/ESP3B), that means no collision at all.
                    continue;
                }

                if (isset($beingRelegated[$reserve])) {
                    // The reserve is itself being relegated out of this tier by
                    // a deeper rule (e.g. parent relegating ESP1→ESP2 while the
                    // reserve relegates ESP2→ESP3 on its own merits). The reserve
                    // leaves the collision tier under its own rule's move, so
                    // adding a cascade here would emit a second move for the
                    // same team and trip validatePlan's no-double-move check.
                    continue;
                }

                $reserveTier = $tierStructure['tierByCompetition'][$reserveCurrentComp] ?? null;
                if ($reserveTier === null) {
                    continue;
                }

                $deeperTier = $reserveTier + 1;
                // Restrict the cascade target to deeper-tier competitions
                // that actually exist in the snapshot. The config-declared
                // deeper tier may be absent for this game (legacy game
                // predating that tier's addition — see filterApplicableRules).
                // Cascading into an absent league would emit a phantom move
                // that pulls the reserve out of its current tier with no
                // compensating backfill, leaving the donor tier short and
                // tripping validatePlan's per-tier count check.
                $deeperComps = array_values(array_filter(
                    $tierStructure['competitionsByTier'][$deeperTier] ?? [],
                    fn ($comp) => array_key_exists($comp, $snapshot->standingsByCompetition),
                ));
                if (empty($deeperComps)) {
                    // Escape hatch: reserve at deepest tier (or no deeper tier
                    // exists for this game), cannot cascade.
                    $cancellations['relegations'][$i][] = $parentCandidate;
                    $cancellations['promotionsCount'][$i] = ($cancellations['promotionsCount'][$i] ?? 0) + 1;
                    $cancellations['skipped'][] = new SkippedRelegation(
                        parentTeamId: $parentCandidate,
                        fromCompetitionId: $rule['top_division'],
                        wouldHaveLandedIn: $parentDest,
                        reason: SkippedRelegation::REASON_RESERVE_AT_FLOOR,
                    );
                    continue;
                }

                // Cascade the reserve to the deeper tier. Pick the sibling that
                // doesn't already host the parent (which won't happen for tier 3,
                // but defensive).
                $cascadeDest = $this->chooseCascadeDestination(
                    $deeperComps,
                    $snapshot,
                    $parentCandidate,
                );

                $cascadeMoves[] = new PromotionMove(
                    teamId: $reserve,
                    fromCompetitionId: $reserveCurrentComp,
                    toCompetitionId: $cascadeDest,
                    reason: PromotionMove::REASON_RESERVE_CASCADE,
                );

                $compTeam = $this->pickCompensation(
                    $snapshot,
                    $cascadeDest,
                    $reserveCurrentComp,
                    $usedAsCompensation,
                    $alreadyPromoted,
                    $reserve,
                );
                if ($compTeam !== null) {
                    $compensationMoves[] = new PromotionMove(
                        teamId: $compTeam,
                        fromCompetitionId: $cascadeDest,
                        toCompetitionId: $reserveCurrentComp,
                        reason: PromotionMove::REASON_CASCADE_COMPENSATION,
                    );
                    $usedAsCompensation[$compTeam] = true;
                }
            }
        }

        return [$cascadeMoves, $compensationMoves, $cancellations];
    }

    /**
     * For a split rule (PrimeraRFEF), decide which sibling each relegator
     * lands in. Strategy: avoid putting a parent into the same competition
     * as its reserve. After that, distribute the rest so the per-group
     * promotion counts still balance.
     *
     * @param  array<string, mixed>  $rule
     * @param  list<string>  $relegators
     * @param  list<array{teamId: string, kind: string, source: string}>  $promoters
     * @return array<string, string>  team_id => destination_competition_id
     */
    private function assignSplitRelegationDestinations(
        CountrySeasonSnapshot $snapshot,
        array $rule,
        array $relegators,
        array $promoters,
    ): array {
        $sources = $rule['playoff_source_divisions'];
        // Capacity per group = how many teams left that group via promotion.
        $capacity = array_fill_keys($sources, 0);
        foreach ($promoters as $p) {
            if (isset($capacity[$p['source']])) {
                $capacity[$p['source']]++;
            }
        }

        $assignment = [];
        $remaining = $relegators;

        // First pass: prefer placements that avoid reserve/parent coexistence.
        foreach ($remaining as $teamId) {
            $reserve = $this->findReserve($snapshot, $teamId);
            if ($reserve === null) {
                continue;
            }
            $reserveComp = $snapshot->competitionOf($reserve);
            if ($reserveComp === null || !in_array($reserveComp, $sources, true)) {
                continue;
            }
            // Prefer the OTHER sibling.
            $preferred = null;
            foreach ($sources as $candidate) {
                if ($candidate === $reserveComp) {
                    continue;
                }
                if (($capacity[$candidate] ?? 0) > 0) {
                    $preferred = $candidate;
                    break;
                }
            }
            if ($preferred !== null) {
                $assignment[$teamId] = $preferred;
                $capacity[$preferred]--;
            }
        }

        // Second pass: fill the rest in source order until capacity is exhausted.
        foreach ($remaining as $teamId) {
            if (isset($assignment[$teamId])) {
                continue;
            }
            foreach ($sources as $candidate) {
                if (($capacity[$candidate] ?? 0) > 0) {
                    $assignment[$teamId] = $candidate;
                    $capacity[$candidate]--;
                    break;
                }
            }
        }

        return $assignment;
    }

    private function findReserve(CountrySeasonSnapshot $snapshot, string $parentTeamId): ?string
    {
        foreach ($snapshot->reserveToParent as $reserve => $parent) {
            if ($parent === $parentTeamId) {
                return $reserve;
            }
        }
        return null;
    }

    /**
     * @param  list<string>  $candidates
     */
    private function chooseCascadeDestination(array $candidates, CountrySeasonSnapshot $snapshot, string $parentTeamId): string
    {
        // Prefer a sibling that does NOT already contain the parent (rare,
        // but the parent could in theory be relegated from above into one of
        // these competitions via the same rule). If both are eligible, just
        // pick the first deterministically.
        foreach ($candidates as $candidate) {
            if (!in_array($parentTeamId, $snapshot->standings($candidate), true)) {
                return $candidate;
            }
        }
        return $candidates[0];
    }

    /**
     * Pick a non-reserve from the deeper-tier $sourceComp to backfill the
     * hole left by the cascading reserve. Skip teams already promoted or
     * already used as compensation. Walk bottom-up (worst position first)
     * so the best teams in the deeper tier aren't randomly elevated.
     *
     * @param  array<string, bool>  $usedAsCompensation
     * @param  array<string, bool>  $alreadyPromoted
     */
    private function pickCompensation(
        CountrySeasonSnapshot $snapshot,
        string $sourceComp,
        string $destinationComp,
        array $usedAsCompensation,
        array $alreadyPromoted,
        string $cascadingReserve,
    ): ?string {
        // Walk top-of-table down (so we pick the best non-reserve that wasn't
        // already promoted — the "lucky next eligible team"). The reserve filter
        // here matches eligibleInOrder: skip reserves whose parent is in
        // the destination tier.
        $destRoster = $snapshot->standings($destinationComp);
        $destRosterPlusReserve = array_merge($destRoster, [$cascadingReserve]); // reserve will be there post-swap
        $destAfter = array_flip($destRosterPlusReserve);

        foreach ($snapshot->standings($sourceComp) as $teamId) {
            if ($teamId === $cascadingReserve) {
                continue;
            }
            if (isset($usedAsCompensation[$teamId])) {
                continue;
            }
            if (isset($alreadyPromoted[$teamId])) {
                continue;
            }
            $parent = $snapshot->parentOf($teamId);
            if ($parent !== null && isset($destAfter[$parent])) {
                continue;
            }
            return $teamId;
        }
        return null;
    }

    /**
     * Pick a backfill team from the shallower tier $sourceComp to swap
     * down into $destinationComp as part of an inherited-coexistence
     * "promote parent up" repair. Mirrors pickCompensation in shape but
     * walks the standings BOTTOM-UP (worst position first) — we want the
     * weakest team in the shallower tier, since this is a forced demotion
     * to make room for the promoting parent.
     *
     * Skips teams already in motion (relegating via some rule, already
     * being promoted, already used as compensation) and reserves whose
     * parent will be in the destination after the parent move lands.
     *
     * @param  array<string, bool>  $beingRelegated
     * @param  array<string, bool>  $usedAsCompensation
     * @param  array<string, bool>  $alreadyPromoted
     */
    private function pickPromotionBackfill(
        CountrySeasonSnapshot $snapshot,
        string $sourceComp,
        string $destinationComp,
        array $beingRelegated,
        array $usedAsCompensation,
        array $alreadyPromoted,
        string $promotingParent,
    ): ?string {
        $destRoster = $snapshot->standings($destinationComp);
        $destRosterPlusParent = array_merge($destRoster, [$promotingParent]);
        $destAfter = array_flip($destRosterPlusParent);

        foreach (array_reverse($snapshot->standings($sourceComp)) as $teamId) {
            if ($teamId === $promotingParent) {
                continue;
            }
            if (isset($beingRelegated[$teamId])) {
                continue;
            }
            if (isset($alreadyPromoted[$teamId])) {
                continue;
            }
            if (isset($usedAsCompensation[$teamId])) {
                continue;
            }
            $parent = $snapshot->parentOf($teamId);
            if ($parent !== null && isset($destAfter[$parent])) {
                continue;
            }
            return $teamId;
        }
        return null;
    }

    // ──────────────────────────────────────────────────
    // Move emission
    // ──────────────────────────────────────────────────

    /**
     * @param  array<int, array<string, mixed>>  $promotionRules
     * @param  array<int, list<string>>  $relegatorsByRule
     * @param  array<int, list<array{teamId: string, kind: string, source: string}>>  $promotersByRule
     * @return list<PromotionMove>
     */
    private function buildRuleMoves(
        CountrySeasonSnapshot $snapshot,
        array $tierStructure,
        array $promotionRules,
        array $relegatorsByRule,
        array $promotersByRule,
    ): array {
        $moves = [];

        foreach ($promotionRules as $i => $rule) {
            $isSplit = !empty($rule['playoff_source_divisions']);
            $relegators = $relegatorsByRule[$i];
            $promoters = $promotersByRule[$i];

            // Promoters first (so a reserve cascading doesn't pick up a team
            // promotion order isn't important for correctness — the executor
            // applies moves atomically — but consistent ordering helps tests).
            foreach ($promoters as $p) {
                $moves[] = new PromotionMove(
                    teamId: $p['teamId'],
                    fromCompetitionId: $p['source'],
                    toCompetitionId: $rule['top_division'],
                    reason: $p['kind'],
                );
            }

            if ($isSplit) {
                $assignment = $this->assignSplitRelegationDestinations(
                    $snapshot,
                    $rule,
                    $relegators,
                    $promoters,
                );
                foreach ($relegators as $teamId) {
                    $dest = $assignment[$teamId] ?? $rule['bottom_division'];
                    $moves[] = new PromotionMove(
                        teamId: $teamId,
                        fromCompetitionId: $rule['top_division'],
                        toCompetitionId: $dest,
                        reason: PromotionMove::REASON_RELEGATION,
                    );
                }
            } else {
                foreach ($relegators as $teamId) {
                    $moves[] = new PromotionMove(
                        teamId: $teamId,
                        fromCompetitionId: $rule['top_division'],
                        toCompetitionId: $rule['bottom_division'],
                        reason: PromotionMove::REASON_RELEGATION,
                    );
                }
            }
        }

        return $moves;
    }

    /**
     * @param  list<PromotionMove>  $moves
     * @return list<string>
     */
    private function touchedCompetitions(array $moves): array
    {
        $touched = [];
        foreach ($moves as $m) {
            $touched[$m->fromCompetitionId] = true;
            $touched[$m->toCompetitionId] = true;
        }
        return array_keys($touched);
    }

    // ──────────────────────────────────────────────────
    // Tier structure + validation
    // ──────────────────────────────────────────────────

    /**
     * @return array{
     *     tierByCompetition: array<string, int>,
     *     competitionsByTier: array<int, list<string>>,
     *     tierSizes: array<string, int>,
     * }
     */
    private function buildTierStructure(array $config): array
    {
        $tierByCompetition = [];
        $competitionsByTier = [];
        $tierSizes = [];

        foreach ($config['tiers'] ?? [] as $depth => $tier) {
            $tierByCompetition[$tier['competition']] = (int) $depth;
            $competitionsByTier[(int) $depth][] = $tier['competition'];
            $tierSizes[$tier['competition']] = (int) ($tier['teams'] ?? 0);
            foreach ($tier['siblings'] ?? [] as $sibling) {
                $tierByCompetition[$sibling['competition']] = (int) $depth;
                $competitionsByTier[(int) $depth][] = $sibling['competition'];
                $tierSizes[$sibling['competition']] = (int) ($sibling['teams'] ?? 0);
            }
        }

        return [
            'tierByCompetition' => $tierByCompetition,
            'competitionsByTier' => $competitionsByTier,
            'tierSizes' => $tierSizes,
        ];
    }

    /**
     * Confirm the plan respects every invariant before returning it. The
     * processor's belt-and-suspenders check is the same set, just against the
     * post-swap DB; failing here means the planner has a bug.
     */
    private function validatePlan(PromotionRelegationPlan $plan, CountrySeasonSnapshot $snapshot, array $tierStructure): void
    {
        // No team moves twice
        $movedTeams = [];
        foreach ($plan->moves as $m) {
            if (isset($movedTeams[$m->teamId])) {
                throw new \LogicException("Planner produced two moves for team {$m->teamId}.");
            }
            if ($m->fromCompetitionId === $m->toCompetitionId) {
                throw new \LogicException("Planner produced a no-op move for team {$m->teamId} in {$m->fromCompetitionId}.");
            }
            $movedTeams[$m->teamId] = true;
        }

        // Per-tier counts preserved
        $counts = [];
        foreach ($snapshot->standingsByCompetition as $comp => $teams) {
            $counts[$comp] = count($teams);
        }
        foreach ($plan->moves as $m) {
            $counts[$m->fromCompetitionId] = ($counts[$m->fromCompetitionId] ?? 0) - 1;
            $counts[$m->toCompetitionId] = ($counts[$m->toCompetitionId] ?? 0) + 1;
        }
        foreach ($tierStructure['tierSizes'] as $comp => $expectedSize) {
            if (!isset($snapshot->standingsByCompetition[$comp])) {
                continue;
            }
            if (($counts[$comp] ?? 0) !== $expectedSize) {
                throw new \LogicException(
                    "Planner produced an unbalanced plan: {$comp} ends with " .
                    ($counts[$comp] ?? 0) . " teams, expected {$expectedSize}.",
                );
            }
        }

        // No reserve/parent coexistence post-plan
        $finalComp = [];
        foreach ($snapshot->standingsByCompetition as $comp => $teams) {
            foreach ($teams as $teamId) {
                $finalComp[$teamId] = $comp;
            }
        }
        foreach ($plan->moves as $m) {
            $finalComp[$m->teamId] = $m->toCompetitionId;
        }
        // Collect every coexistence so the typed exception carries all pairs in
        // one pass — the repairer that catches it can then resolve them together.
        $violations = [];
        foreach ($snapshot->reserveToParent as $reserve => $parent) {
            if (!isset($finalComp[$reserve], $finalComp[$parent])) {
                continue;
            }
            if ($finalComp[$reserve] === $finalComp[$parent]) {
                $violations[] = [
                    'reserve' => (string) $reserve,
                    'parent' => (string) $parent,
                    'competition' => (string) $finalComp[$reserve],
                ];
            }
        }
        if (!empty($violations)) {
            throw ReserveParentCoexistenceException::forViolations($violations);
        }
    }
}
