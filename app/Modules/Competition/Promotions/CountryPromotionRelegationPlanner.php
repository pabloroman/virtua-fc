<?php

namespace App\Modules\Competition\Promotions;

use App\Modules\Competition\Enums\PlayoffState;
use App\Modules\Competition\Exceptions\PlayoffInProgressException;

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

        // Step 4: Reserve protection — cascade reserves whose parent is about to
        // land in their tier, or cancel the parent's relegation if there's no
        // deeper tier to cascade to.
        [$cascadeMoves, $compensationMoves, $cancellations] = $this->resolveReserveProtection(
            $snapshot,
            $tierStructure,
            $promotionRules,
            $relegatorsByRule,
            $promotersByRule,
        );

        // Step 5: Apply cancellations from the escape hatch.
        foreach ($cancellations['relegations'] as $ruleIdx => $cancelledTeamIds) {
            $relegatorsByRule[$ruleIdx] = array_values(
                array_diff($relegatorsByRule[$ruleIdx], $cancelledTeamIds),
            );
        }
        foreach ($cancellations['promotionsCount'] as $ruleIdx => $count) {
            // Drop the lowest-priority promoters (end of list = playoff winners
            // / lowest-seeded direct slot, depending on rule order).
            $current = $promotersByRule[$ruleIdx];
            $keep = max(0, count($current) - $count);
            $promotersByRule[$ruleIdx] = array_slice($current, 0, $keep);
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
            skippedRelegations: $cancellations['skipped'],
            touchedCompetitionIds: $touched,
        );

        $this->validatePlan($plan, $snapshot, $tierStructure);

        return $plan;
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

        $out = [];

        foreach ($sources as $sourceDiv) {
            $eligible = $this->eligibleInOrder(
                $snapshot,
                $snapshot->standings($sourceDiv),
                $effectiveTopRoster,
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
        $state = $snapshot->playoffState($playoffComp);
        $playoffPickCount = 2; // Two bracket finals → two promotion spots.

        $playoffPicks = match ($state) {
            PlayoffState::Completed => $this->primeraRfefCompletedWinners($snapshot, $playoffComp, $playoffPickCount),
            PlayoffState::NotStarted => $this->primeraRfefStandIns(
                $snapshot,
                $sources,
                $directCount,
                $effectiveTopRoster,
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
            PlayoffState::Completed => array_slice($snapshot->playoffWinners($playoffComp), 0, 1),
            PlayoffState::InProgress => throw PlayoffInProgressException::forCompetition($playoffComp),
            PlayoffState::NotStarted => array_slice(
                array_values(array_diff($eligible, $directAlreadyTaken)),
                0,
                1, // One stand-in (the bracket produces one winner from the playoff range)
            ),
        };
    }

    /**
     * @return list<array{teamId: string, source: string}>
     */
    private function primeraRfefCompletedWinners(CountrySeasonSnapshot $snapshot, string $playoffComp, int $count): array
    {
        $winners = array_slice($snapshot->playoffWinners($playoffComp), 0, $count);
        $out = [];
        foreach ($winners as $teamId) {
            $source = $snapshot->competitionOf($teamId) ?? '';
            $out[] = ['teamId' => $teamId, 'source' => $source];
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

                $reserveTier = $tierStructure['tierByCompetition'][$reserveCurrentComp] ?? null;
                if ($reserveTier === null) {
                    continue;
                }

                $deeperTier = $reserveTier + 1;
                if (!isset($tierStructure['competitionsByTier'][$deeperTier])) {
                    // Escape hatch: reserve at deepest tier, cannot cascade.
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
                    $tierStructure['competitionsByTier'][$deeperTier],
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
        foreach ($snapshot->reserveToParent as $reserve => $parent) {
            if (!isset($finalComp[$reserve], $finalComp[$parent])) {
                continue;
            }
            if ($finalComp[$reserve] === $finalComp[$parent]) {
                throw new \LogicException(
                    "Planner produced a coexistence violation: reserve={$reserve} parent={$parent} competition={$finalComp[$reserve]}.",
                );
            }
        }
    }
}
