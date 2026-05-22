<?php

namespace App\Console\Commands;

use App\Models\CompetitionEntry;
use App\Models\Game;
use App\Models\GameStanding;
use App\Models\SimulatedSeason;
use App\Models\Team;
use App\Modules\Competition\Services\CountryConfig;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Repair reserve/parent league assignments for a game stuck mid season-transition
 * because CountryPromotionRelegationPlanner::validatePlan detected one of:
 *
 *   INVERTED:    reserve in a higher tier than its parent (e.g. Promesas ESP1,
 *                Osasuna ESP2). validatePlan seeds finalComp from snapshot
 *                standings before applying any plan moves, so the inversion
 *                gets carried into finalComp; once the planner emits any
 *                relegation that lands the reserve in the parent's tier, the
 *                pair collides.
 *
 *   COEXISTENCE: reserve and parent already share a competition in the
 *                snapshot. validatePlan throws immediately, even when no
 *                relevant moves were emitted.
 *
 * For each detected issue, plans a 1:1 league swap:
 *
 *   - INVERTED:    swap reserve <-> parent. Reserve drops to parent's tier,
 *                  parent rises to reserve's tier — restores correct hierarchy
 *                  with no displacement of unrelated teams.
 *   - COEXISTENCE: swap parent with the bottom team of the next-higher tier.
 *                  Parent moves up; the displaced team drops into the shared
 *                  tier. Requires a single competition at the next-higher tier
 *                  (aborts when that tier has siblings — manual choice needed).
 *
 * For each swap, mutates whichever store backs each affected league:
 *   - played league:     game_standings row (team_id swap, position preserved)
 *   - non-played league: simulated_seasons.results array slot (team_id swap)
 *
 * Plus competition_entries rows in both directions.
 *
 * Defaults to dry-run; pass --apply to write. Safe to leave in the tree —
 * preconditions guarantee it only acts on a game checkpointed at the
 * pre-PromotionRelegation step, and the issue detector only emits swaps for
 * pairs that would currently trip validatePlan.
 *
 * After applying, run app:diagnose-stuck-game to confirm flags are gone,
 * then app:resume-season-transition to re-run the closing pipeline from
 * step 23 (PromotionRelegationProcessor builds a fresh snapshot, replans,
 * and validatePlan passes).
 */
class RepairReserveParentCoexistence extends Command
{
    protected $signature = 'app:repair-reserve-parent-coexistence
        {game : Game UUID stuck mid season-transition.}
        {--apply : Actually write changes (default is dry-run).}';

    protected $description = 'Repair reserve/parent inversion or coexistence for a stuck game (dry-run by default).';

    /**
     * The season_transition_step at which PromotionRelegationProcessor is
     * the next processor to run. The planner explodes during that processor,
     * which leaves the game checkpointed at the prior step.
     */
    private const EXPECTED_STEP = 22;

    public function __construct(private readonly CountryConfig $countryConfig)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $gameId = (string) $this->argument('game');
        $apply = (bool) $this->option('apply');

        $game = Game::find($gameId);
        if (!$game) {
            $this->error("Game {$gameId} not found.");
            return self::FAILURE;
        }
        if (!$game->isTransitioningSeason()) {
            $this->error("Game {$gameId} is not in a transitioning state (season_transitioning_at is null). Aborting.");
            return self::FAILURE;
        }
        if ((int) $game->season_transition_step !== self::EXPECTED_STEP) {
            $this->error(sprintf(
                "Game %s season_transition_step is %s, expected %d. Aborting — the planner runs at the next step.",
                $gameId,
                $game->season_transition_step ?? 'NULL',
                self::EXPECTED_STEP,
            ));
            return self::FAILURE;
        }

        // Build tier maps from country config. Only league tier competitions
        // count; cups, promotion playoffs, and continentals are excluded.
        $tierToComps = [];
        $compToTier = [];
        foreach (array_keys($this->countryConfig->tiers($game->country)) as $tier) {
            $ids = $this->countryConfig->tierCompetitionIds($game->country, (int) $tier);
            $tierToComps[(int) $tier] = $ids;
            foreach ($ids as $compId) {
                $compToTier[$compId] = (int) $tier;
            }
        }
        if (empty($compToTier)) {
            $this->error("Country {$game->country} has no playable tiers configured. Aborting.");
            return self::FAILURE;
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
                $this->error(sprintf(
                    "Reserve %s or parent (%s) has multiple league entries — refusing to swap. Investigate competition_entries first.",
                    $reserve->name,
                    $reserve->parent_team_id,
                ));
                return self::FAILURE;
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
            $this->info('No reserve/parent inversions or coexistences detected. Nothing to repair.');
            return self::SUCCESS;
        }

        $this->info('--- Detected issues ---');
        foreach ($issues as $i) {
            $this->line("  {$i['kind']}: reserve {$i['reserve']['name']} ({$i['reserve']['league']}) <- parent {$i['parent']['name']} ({$i['parent']['league']})");
        }
        $this->line('');

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
                $this->error("Cannot resolve COEXISTENCE for {$issue['parent']['name']} in tier {$parentTier}: no higher tier exists. Aborting.");
                return self::FAILURE;
            }
            if (count($targetComps) > 1) {
                $this->error("Cannot resolve COEXISTENCE for {$issue['parent']['name']}: tier {$targetTier} has siblings (" . implode(',', $targetComps) . "). Manual swap target required. Aborting.");
                return self::FAILURE;
            }
            $targetComp = $targetComps[0];
            $bottomTeamId = $this->bottomTeamOf($gameId, (string) $game->season, $targetComp);
            if ($bottomTeamId === null) {
                $this->error("Cannot resolve COEXISTENCE for {$issue['parent']['name']}: no bottom team found in {$targetComp}. Aborting.");
                return self::FAILURE;
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

        // Resolve slots for every swap so we can print the full mutation list
        // up front and fail before any writes if a slot is unlocatable.
        $mutations = [];
        $this->info('--- Planned swaps ---');
        foreach ($swaps as $swap) {
            $this->line("  {$swap['reason']}");
            $slotA = $this->locateSlot($gameId, (string) $game->season, $swap['leagueA'], $swap['teamA']);
            $slotB = $this->locateSlot($gameId, (string) $game->season, $swap['leagueB'], $swap['teamB']);
            if ($slotA === null) {
                $this->error("    Cannot locate slot for {$swap['teamA_name']} in {$swap['leagueA']} (no game_standings or simulated_seasons entry). Aborting.");
                return self::FAILURE;
            }
            if ($slotB === null) {
                $this->error("    Cannot locate slot for {$swap['teamB_name']} in {$swap['leagueB']} (no game_standings or simulated_seasons entry). Aborting.");
                return self::FAILURE;
            }
            $this->line("    competition_entries: {$swap['teamA_name']} {$swap['leagueA']} -> {$swap['leagueB']}");
            $this->line("    competition_entries: {$swap['teamB_name']} {$swap['leagueB']} -> {$swap['leagueA']}");
            $this->line("    {$swap['leagueA']} ({$slotA['kind']}): {$swap['teamA_name']} -> {$swap['teamB_name']}");
            $this->line("    {$swap['leagueB']} ({$slotB['kind']}): {$swap['teamB_name']} -> {$swap['teamA_name']}");
            $mutations[] = ['swap' => $swap, 'slotA' => $slotA, 'slotB' => $slotB];
        }
        $this->line('');

        if (!$apply) {
            $this->info('Dry run — no changes written. Pass --apply to execute.');
            return self::SUCCESS;
        }

        try {
            DB::transaction(function () use ($gameId, $mutations) {
                foreach ($mutations as $m) {
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
            });
        } catch (\Throwable $e) {
            $this->error("Repair failed: {$e->getMessage()}");
            return self::FAILURE;
        }

        $this->info('Repair applied. Next steps:');
        $this->line("  php artisan app:diagnose-stuck-game {$gameId}");
        $this->line("  php artisan app:resume-season-transition {$gameId}");

        return self::SUCCESS;
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
