<?php

namespace App\Modules\Competition\Promotions;

use App\Models\CompetitionEntry;
use App\Models\Game;
use App\Models\GameStanding;
use App\Models\SimulatedSeason;
use App\Models\Team;
use App\Modules\Competition\Services\CountryConfig;

/**
 * Detects and repairs reserve/parent league corruption for a game stuck (or
 * about to get stuck) mid season-transition because
 * {@see CountryPromotionRelegationPlanner::validatePlan} would throw a
 * {@see \App\Modules\Competition\Exceptions\ReserveParentCoexistenceException}.
 *
 * Two corruption shapes are handled:
 *
 *   INVERTED:    reserve sits in a higher tier than its parent (e.g. Promesas
 *                ESP1, Osasuna ESP2). validatePlan seeds finalComp from
 *                snapshot standings before applying any moves, so the inversion
 *                is carried in; once any relegation lands the reserve in the
 *                parent's tier, the pair collides.
 *   COEXISTENCE: reserve and parent already share a competition in the
 *                snapshot. validatePlan throws immediately.
 *
 * For each issue a 1:1 league swap is planned:
 *   - INVERTED:    swap reserve <-> parent (reserve drops to parent's tier,
 *                  parent rises to reserve's tier — restores hierarchy with no
 *                  displacement of unrelated teams).
 *   - COEXISTENCE: swap parent with the bottom team of the next-higher tier.
 *                  Parent moves up; the displaced team drops into the shared
 *                  tier. Requires a single competition at the next-higher tier
 *                  (bails when that tier has siblings — a manual choice).
 *
 * Each swap mutates whichever store backs the affected league:
 *   - played league:     game_standings row (team_id swap, position preserved)
 *   - non-played league: simulated_seasons.results array slot (team_id swap)
 * plus the two competition_entries rows.
 *
 * The repairer is deliberately conservative: anything it cannot resolve
 * deterministically (multi-league membership, sibling next-tier, unlocatable
 * slot) returns an Unsafe result rather than guessing, so callers escalate to
 * manual repair. It is guard-free about transition state (the
 * RepairReserveParentCoexistence command owns those CLI checks) and never opens
 * its own transaction (callers supply one — the season pipeline's per-processor
 * transaction in-band, or the command's DB::transaction on --apply).
 */
class ReserveParentCoexistenceRepairer
{
    public function __construct(private readonly CountryConfig $countryConfig) {}

    /**
     * Plan + apply in one call (used for the in-band self-heal). Returns the
     * plan result unchanged when there is nothing to fix or the situation is
     * unsafe to repair automatically.
     */
    public function repair(Game $game): ReserveRepairResult
    {
        $plan = $this->plan($game);

        if ($plan->outcome !== RepairOutcome::Repaired) {
            return $plan;
        }

        return $this->apply($game, $plan);
    }

    /**
     * Detect issues and resolve the swaps + slots needed to fix them, without
     * writing anything. Returns NothingToFix, Unsafe (with a reason), or
     * Repaired (carrying the resolved mutations ready for apply()).
     */
    public function plan(Game $game): ReserveRepairResult
    {
        $gameId = (string) $game->id;
        $season = (string) $game->season;

        // Build tier maps from country config. Only league tier competitions
        // count; cups, promotion playoffs, and continentals are excluded.
        [$tierToComps, $compToTier] = $this->tierMaps((string) $game->country);
        if (empty($compToTier)) {
            return ReserveRepairResult::unsafe("Country {$game->country} has no playable tiers configured.");
        }
        $leagueIds = array_keys($compToTier);

        // Detect issues.
        $issues = [];
        $reserves = Team::where('country', $game->country)
            ->whereNotNull('parent_team_id')
            ->get(['id', 'name', 'parent_team_id']);

        foreach ($reserves as $reserve) {
            $rLeagues = CompetitionEntry::where('game_id', $gameId)
                ->where('team_id', $reserve->id)
                ->whereIn('competition_id', $leagueIds)
                ->pluck('competition_id')->all();
            $pLeagues = CompetitionEntry::where('game_id', $gameId)
                ->where('team_id', $reserve->parent_team_id)
                ->whereIn('competition_id', $leagueIds)
                ->pluck('competition_id')->all();

            // Multi-league membership for a single team is a separate corruption.
            // Bail rather than guess which league to swap.
            if (count($rLeagues) > 1 || count($pLeagues) > 1) {
                return ReserveRepairResult::unsafe(sprintf(
                    'Reserve %s or parent (%s) has multiple league entries — refusing to swap. Investigate competition_entries first.',
                    $reserve->name,
                    $reserve->parent_team_id,
                ));
            }

            $rLeague = $rLeagues[0] ?? null;
            $pLeague = $pLeagues[0] ?? null;
            if ($rLeague === null || $pLeague === null) {
                continue;
            }

            $rTier = $compToTier[$rLeague];
            $pTier = $compToTier[$pLeague];
            $parentName = Team::where('id', $reserve->parent_team_id)->value('name');

            if ($rLeague === $pLeague) {
                $issues[] = [
                    'kind' => 'COEXISTENCE',
                    'reserve' => ['id' => $reserve->id, 'name' => $reserve->name, 'league' => $rLeague, 'tier' => $rTier],
                    'parent'  => ['id' => (string) $reserve->parent_team_id, 'name' => $parentName, 'league' => $pLeague, 'tier' => $pTier],
                ];
            } elseif ($rTier < $pTier) {
                // Lower tier *number* = higher division. Reserve above parent is inverted.
                $issues[] = [
                    'kind' => 'INVERTED',
                    'reserve' => ['id' => $reserve->id, 'name' => $reserve->name, 'league' => $rLeague, 'tier' => $rTier],
                    'parent'  => ['id' => (string) $reserve->parent_team_id, 'name' => $parentName, 'league' => $pLeague, 'tier' => $pTier],
                ];
            }
        }

        if (empty($issues)) {
            return ReserveRepairResult::nothingToFix();
        }

        // Plan a swap for each issue.
        $swaps = [];
        foreach ($issues as $issue) {
            if ($issue['kind'] === 'INVERTED') {
                $swaps[] = [
                    'reason'     => "INVERTED: {$issue['reserve']['name']} ({$issue['reserve']['league']}) <-> {$issue['parent']['name']} ({$issue['parent']['league']})",
                    'teamA'      => $issue['reserve']['id'],
                    'teamA_name' => $issue['reserve']['name'],
                    'leagueA'    => $issue['reserve']['league'],
                    'teamB'      => $issue['parent']['id'],
                    'teamB_name' => $issue['parent']['name'],
                    'leagueB'    => $issue['parent']['league'],
                ];
                continue;
            }

            // COEXISTENCE: swap parent with the bottom team of the next-higher tier.
            $parentTier = $issue['parent']['tier'];
            $targetTier = $parentTier - 1;
            $targetComps = $tierToComps[$targetTier] ?? [];
            if (empty($targetComps)) {
                return ReserveRepairResult::unsafe(
                    "Cannot resolve COEXISTENCE for {$issue['parent']['name']} in tier {$parentTier}: no higher tier exists.",
                    $issues,
                );
            }
            if (count($targetComps) > 1) {
                return ReserveRepairResult::unsafe(
                    "Cannot resolve COEXISTENCE for {$issue['parent']['name']}: tier {$targetTier} has siblings (" . implode(',', $targetComps) . "). Manual swap target required.",
                    $issues,
                );
            }
            $targetComp = $targetComps[0];
            $bottomTeamId = $this->bottomTeamOf($gameId, $season, $targetComp);
            if ($bottomTeamId === null) {
                return ReserveRepairResult::unsafe(
                    "Cannot resolve COEXISTENCE for {$issue['parent']['name']}: no bottom team found in {$targetComp}.",
                    $issues,
                );
            }
            $bottomName = Team::where('id', $bottomTeamId)->value('name');
            $swaps[] = [
                'reason'     => "COEXISTENCE: {$issue['parent']['name']} ({$issue['parent']['league']}) <-> {$bottomName} ({$targetComp}) [parent moves up]",
                'teamA'      => $issue['parent']['id'],
                'teamA_name' => $issue['parent']['name'],
                'leagueA'    => $issue['parent']['league'],
                'teamB'      => $bottomTeamId,
                'teamB_name' => $bottomName,
                'leagueB'    => $targetComp,
            ];
        }

        // Resolve slots for every swap so we fail before any writes if a slot
        // is unlocatable.
        $mutations = [];
        foreach ($swaps as $swap) {
            $slotA = $this->locateSlot($gameId, $season, $swap['leagueA'], $swap['teamA']);
            $slotB = $this->locateSlot($gameId, $season, $swap['leagueB'], $swap['teamB']);
            if ($slotA === null) {
                return ReserveRepairResult::unsafe(
                    "Cannot locate slot for {$swap['teamA_name']} in {$swap['leagueA']} (no game_standings or simulated_seasons entry).",
                    $issues,
                );
            }
            if ($slotB === null) {
                return ReserveRepairResult::unsafe(
                    "Cannot locate slot for {$swap['teamB_name']} in {$swap['leagueB']} (no game_standings or simulated_seasons entry).",
                    $issues,
                );
            }
            $mutations[] = ['swap' => $swap, 'slotA' => $slotA, 'slotB' => $slotB];
        }

        return ReserveRepairResult::repaired($issues, $mutations);
    }

    /**
     * Apply the swaps resolved by plan(). Does NOT open its own transaction —
     * the caller wraps it (season pipeline transaction in-band, or the
     * command's DB::transaction on --apply). Throws on an integrity mismatch
     * (an "expected exactly one row" guard) so the caller's transaction rolls
     * back cleanly.
     */
    public function apply(Game $game, ReserveRepairResult $plan): ReserveRepairResult
    {
        $gameId = (string) $game->id;

        foreach ($plan->mutations as $m) {
            $swap = $m['swap'];

            $a = CompetitionEntry::where('game_id', $gameId)
                ->where('team_id', $swap['teamA'])
                ->where('competition_id', $swap['leagueA'])
                ->update(['competition_id' => $swap['leagueB']]);
            if ($a !== 1) {
                throw new \RuntimeException("Expected 1 competition_entries update for {$swap['teamA_name']} {$swap['leagueA']}->{$swap['leagueB']}, got {$a}.");
            }
            $b = CompetitionEntry::where('game_id', $gameId)
                ->where('team_id', $swap['teamB'])
                ->where('competition_id', $swap['leagueB'])
                ->update(['competition_id' => $swap['leagueA']]);
            if ($b !== 1) {
                throw new \RuntimeException("Expected 1 competition_entries update for {$swap['teamB_name']} {$swap['leagueB']}->{$swap['leagueA']}, got {$b}.");
            }

            // Slot A (was teamA) becomes teamB; slot B (was teamB) becomes teamA.
            $this->applySlotSwap($m['slotA'], $swap['teamB']);
            $this->applySlotSwap($m['slotB'], $swap['teamA']);
        }

        return $plan;
    }

    /**
     * Build [tierToComps, compToTier] from country config. Only league tiers
     * are included; cups, promotion playoffs, and continentals are excluded.
     *
     * @return array{0: array<int, list<string>>, 1: array<string, int>}
     */
    private function tierMaps(string $country): array
    {
        $tierToComps = [];
        $compToTier = [];
        foreach (array_keys($this->countryConfig->tiers($country)) as $tier) {
            $ids = $this->countryConfig->tierCompetitionIds($country, (int) $tier);
            $tierToComps[(int) $tier] = $ids;
            foreach ($ids as $compId) {
                $compToTier[$compId] = (int) $tier;
            }
        }

        return [$tierToComps, $compToTier];
    }

    /**
     * Locate a team's slot inside a league for the given game/season. Returns
     * a descriptor for a game_standings row (played league) or a
     * simulated_seasons.results index (non-played league), or null if the
     * team isn't present in either store.
     *
     * @return array{kind: string, standing?: GameStanding, sim?: SimulatedSeason, index?: int}|null
     */
    private function locateSlot(string $gameId, string $season, string $competitionId, string $teamId): ?array
    {
        $standing = GameStanding::where('game_id', $gameId)
            ->where('competition_id', $competitionId)
            ->where('team_id', $teamId)
            ->first();
        if ($standing) {
            return ['kind' => 'standing', 'standing' => $standing];
        }

        $sim = SimulatedSeason::where('game_id', $gameId)
            ->where('season', $season)
            ->where('competition_id', $competitionId)
            ->first();
        if ($sim && is_array($sim->results)) {
            $idx = array_search($teamId, $sim->results, true);
            if ($idx !== false) {
                return ['kind' => 'sim', 'sim' => $sim, 'index' => $idx];
            }
        }

        return null;
    }

    /**
     * @param  array{kind: string, standing?: GameStanding, sim?: SimulatedSeason, index?: int}  $slot
     */
    private function applySlotSwap(array $slot, string $newTeamId): void
    {
        if ($slot['kind'] === 'standing') {
            $standing = $slot['standing'];
            $standing->team_id = $newTeamId;
            $standing->save();
            return;
        }
        if ($slot['kind'] === 'sim') {
            $sim = $slot['sim'];
            $results = $sim->results;
            $results[$slot['index']] = $newTeamId;
            $sim->results = $results;
            $sim->save();
            return;
        }
        throw new \RuntimeException("Unknown slot kind: {$slot['kind']}.");
    }

    /**
     * Bottom team of a league: last position by game_standings if played,
     * otherwise last element of simulated_seasons.results.
     */
    private function bottomTeamOf(string $gameId, string $season, string $competitionId): ?string
    {
        $standing = GameStanding::where('game_id', $gameId)
            ->where('competition_id', $competitionId)
            ->orderByDesc('position')
            ->first();
        if ($standing) {
            return (string) $standing->team_id;
        }

        $sim = SimulatedSeason::where('game_id', $gameId)
            ->where('season', $season)
            ->where('competition_id', $competitionId)
            ->first();
        if ($sim && is_array($sim->results) && !empty($sim->results)) {
            return (string) $sim->results[array_key_last($sim->results)];
        }

        return null;
    }
}
