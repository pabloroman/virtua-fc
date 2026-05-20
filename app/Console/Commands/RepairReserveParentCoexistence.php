<?php

namespace App\Console\Commands;

use App\Models\CompetitionEntry;
use App\Models\Game;
use App\Models\GameStanding;
use App\Models\SimulatedSeason;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-off repair for game 101770e2-bc57-497b-a0ee-2621b7a0f2fa, stuck mid
 * season-transition with two structural problems carried over from prior
 * seasons (surfaced by app:diagnose-stuck-game):
 *
 *   COEXISTENCE: CA Osasuna (parent) and CA Osasuna Promesas (reserve) both
 *                in ESP3A. CountryPromotionRelegationPlanner::validatePlan
 *                seeds finalComp from snapshot standings before applying any
 *                plan moves, so the planner throws on the inherited
 *                coexistence even when it would otherwise emit no relevant
 *                moves.
 *
 *   INVERTED:    RC Celta Fortuna (reserve) in ESP2 with parent RC Celta in
 *                ESP3B. Would trip the planner the moment either team got
 *                touched.
 *
 * Repair: two 1:1 league swaps in a single transaction.
 *
 *   Celta swap   — Fortuna (ESP2) <-> Celta (ESP3B)
 *   Osasuna swap — Osasuna (ESP3A) <-> bottom-of-ESP2 team (ESP2)
 *
 * ESP2 is non-played for this game, so its ordering lives in
 * simulated_seasons.results (JSON array). ESP3A/ESP3B are played; their
 * ordering lives in game_standings. The command touches both tables and
 * preserves table order — each swap keeps the existing index/position and
 * just exchanges team_ids.
 *
 * After the swap:
 *   - Osasuna in ESP2, Promesas in ESP3A         (no coexistence)
 *   - Celta in ESP2, Fortuna in ESP3B            (proper tier order)
 *
 * Operator then runs app:diagnose-stuck-game to confirm the flags are gone
 * and app:resume-season-transition to re-run the closing pipeline from
 * step 23 (PromotionRelegationProcessor builds a fresh snapshot, replans,
 * and validatePlan passes).
 *
 * Deliberately specific to this game's known state — hardcoded UUIDs and
 * preconditions, not a general team-swap tool. Safe to delete from the tree
 * once the production run succeeds.
 */
class RepairReserveParentCoexistence extends Command
{
    protected $signature = 'app:repair-reserve-parent-coexistence
        {gameId : Game UUID. Must be the known stuck game.}
        {--dry-run : Print planned mutations without writing.}';

    protected $description = 'One-off repair for the known reserve/parent coexistence + inversion in game 101770e2.';

    private const EXPECTED_GAME_ID = '101770e2-bc57-497b-a0ee-2621b7a0f2fa';
    private const EXPECTED_COUNTRY = 'ES';
    private const EXPECTED_STEP = 22;
    private const EXPECTED_SEASON = '2045';

    private const OSASUNA_PARENT_ID = '956bf812-5dbe-48f8-a812-82d00400b8dc';
    private const OSASUNA_RESERVE_ID = '8a519d48-3c8b-4121-b6ae-49cc89ff1fcf';
    private const CELTA_PARENT_ID = 'd21458e1-6063-4022-af08-f98aa675ffbf';
    private const CELTA_RESERVE_ID = '853060a1-9253-4662-82b5-b413d3045c81';
    private const ESP2_BOTTOM_ID = 'c18f6e69-8926-4074-955e-efc2b9564a2c';

    public function handle(): int
    {
        $gameId = (string) $this->argument('gameId');
        $dryRun = (bool) $this->option('dry-run');

        if ($gameId !== self::EXPECTED_GAME_ID) {
            $this->error("This command only repairs game " . self::EXPECTED_GAME_ID . ". Got: {$gameId}.");
            return self::FAILURE;
        }

        $game = Game::find($gameId);
        if (!$game) {
            $this->error("Game {$gameId} not found.");
            return self::FAILURE;
        }

        // Precondition checks. Bail loudly rather than half-repair if the
        // game's state has drifted from what we observed during diagnosis.
        if ($game->country !== self::EXPECTED_COUNTRY) {
            $this->error("Game {$gameId} country is {$game->country}, expected " . self::EXPECTED_COUNTRY . ".");
            return self::FAILURE;
        }
        if ((string) $game->season !== self::EXPECTED_SEASON) {
            $this->error("Game {$gameId} season is {$game->season}, expected " . self::EXPECTED_SEASON . ".");
            return self::FAILURE;
        }
        if ((int) $game->season_transition_step !== self::EXPECTED_STEP) {
            $this->error("Game {$gameId} season_transition_step is {$game->season_transition_step}, expected " . self::EXPECTED_STEP . ".");
            return self::FAILURE;
        }

        // Confirm each team currently sits in its expected league. Cup
        // entries (ESPCUP) are ignored — we only check league placements.
        $leagueIds = ['ESP1', 'ESP2', 'ESP3A', 'ESP3B'];

        $currentLeague = function (string $teamId) use ($gameId, $leagueIds): ?string {
            return CompetitionEntry::where('game_id', $gameId)
                ->where('team_id', $teamId)
                ->whereIn('competition_id', $leagueIds)
                ->value('competition_id');
        };

        $expectations = [
            self::OSASUNA_PARENT_ID  => ['name' => 'CA Osasuna',         'league' => 'ESP3A'],
            self::OSASUNA_RESERVE_ID => ['name' => 'CA Osasuna Promesas','league' => 'ESP3A'],
            self::CELTA_PARENT_ID    => ['name' => 'RC Celta',           'league' => 'ESP3B'],
            self::CELTA_RESERVE_ID   => ['name' => 'RC Celta Fortuna',   'league' => 'ESP2'],
            self::ESP2_BOTTOM_ID     => ['name' => 'ESP2 bottom team',   'league' => 'ESP2'],
        ];

        foreach ($expectations as $teamId => $exp) {
            $actual = $currentLeague($teamId);
            if ($actual !== $exp['league']) {
                $this->error(sprintf(
                    "Precondition failed: %s (%s) is in %s, expected %s.",
                    $exp['name'], $teamId, $actual ?? 'NONE', $exp['league'],
                ));
                return self::FAILURE;
            }
        }

        // ESP2 simulated_seasons row must exist (non-played league for this game).
        $esp2Sim = SimulatedSeason::where('game_id', $gameId)
            ->where('season', self::EXPECTED_SEASON)
            ->where('competition_id', 'ESP2')
            ->first();
        if (!$esp2Sim) {
            $this->error("Precondition failed: no simulated_seasons row for ESP2 season " . self::EXPECTED_SEASON . ".");
            return self::FAILURE;
        }
        $results = $esp2Sim->results;
        if (!is_array($results) || count($results) !== 22) {
            $this->error("Precondition failed: ESP2 simulated_seasons.results is not a 22-element array (got " . (is_array($results) ? count($results) : gettype($results)) . ").");
            return self::FAILURE;
        }
        $fortunaIdx = array_search(self::CELTA_RESERVE_ID, $results, true);
        $bottomIdx = array_search(self::ESP2_BOTTOM_ID, $results, true);
        if ($fortunaIdx === false || $bottomIdx === false) {
            $this->error("Precondition failed: Fortuna or ESP2 bottom team not found in simulated_seasons.results.");
            return self::FAILURE;
        }
        if ($bottomIdx !== count($results) - 1) {
            $this->warn("Note: ESP2 bottom team is at index {$bottomIdx}, not " . (count($results) - 1) . " — proceeding anyway (swap is still safe).");
        }

        // GameStanding rows for the played-league reassignments.
        $celtaStanding = GameStanding::where('game_id', $gameId)
            ->where('competition_id', 'ESP3B')
            ->where('team_id', self::CELTA_PARENT_ID)
            ->first();
        $osasunaStanding = GameStanding::where('game_id', $gameId)
            ->where('competition_id', 'ESP3A')
            ->where('team_id', self::OSASUNA_PARENT_ID)
            ->first();
        if (!$celtaStanding) {
            $this->error("Precondition failed: no game_standings row for RC Celta in ESP3B.");
            return self::FAILURE;
        }
        if (!$osasunaStanding) {
            $this->error("Precondition failed: no game_standings row for CA Osasuna in ESP3A.");
            return self::FAILURE;
        }

        // Print plan.
        $this->info('--- Planned mutations ---');
        $this->line("Celta swap (fixes INVERTED):");
        $this->line("  competition_entries: Fortuna (" . self::CELTA_RESERVE_ID . ") ESP2 -> ESP3B");
        $this->line("  competition_entries: Celta   (" . self::CELTA_PARENT_ID  . ") ESP3B -> ESP2");
        $this->line("  game_standings:      ESP3B row pos {$celtaStanding->position} team_id Celta -> Fortuna");
        $this->line("  simulated_seasons:   ESP2 results[{$fortunaIdx}] Fortuna -> Celta");
        $this->line('');
        $this->line("Osasuna swap (fixes COEXISTENCE):");
        $this->line("  competition_entries: Osasuna (" . self::OSASUNA_PARENT_ID . ") ESP3A -> ESP2");
        $this->line("  competition_entries: bottom  (" . self::ESP2_BOTTOM_ID     . ") ESP2 -> ESP3A");
        $this->line("  game_standings:      ESP3A row pos {$osasunaStanding->position} team_id Osasuna -> bottom");
        $this->line("  simulated_seasons:   ESP2 results[{$bottomIdx}] bottom -> Osasuna");
        $this->line('');

        if ($dryRun) {
            $this->info('Dry run — no changes written.');
            return self::SUCCESS;
        }

        try {
            DB::transaction(function () use ($gameId, $esp2Sim, $celtaStanding, $osasunaStanding, $fortunaIdx, $bottomIdx) {
                // --- Celta swap ---
                CompetitionEntry::where('game_id', $gameId)
                    ->where('team_id', self::CELTA_RESERVE_ID)
                    ->where('competition_id', 'ESP2')
                    ->update(['competition_id' => 'ESP3B']);
                CompetitionEntry::where('game_id', $gameId)
                    ->where('team_id', self::CELTA_PARENT_ID)
                    ->where('competition_id', 'ESP3B')
                    ->update(['competition_id' => 'ESP2']);

                $celtaStanding->team_id = self::CELTA_RESERVE_ID;
                $celtaStanding->save();

                $results = $esp2Sim->results;
                $results[$fortunaIdx] = self::CELTA_PARENT_ID;

                // --- Osasuna swap ---
                CompetitionEntry::where('game_id', $gameId)
                    ->where('team_id', self::OSASUNA_PARENT_ID)
                    ->where('competition_id', 'ESP3A')
                    ->update(['competition_id' => 'ESP2']);
                CompetitionEntry::where('game_id', $gameId)
                    ->where('team_id', self::ESP2_BOTTOM_ID)
                    ->where('competition_id', 'ESP2')
                    ->update(['competition_id' => 'ESP3A']);

                $osasunaStanding->team_id = self::ESP2_BOTTOM_ID;
                $osasunaStanding->save();

                // Find the bottom team's current index in the (already
                // mutated) results array — array_search rather than the
                // pre-computed index, in case the Celta swap shifted things
                // (it doesn't, but the cost is negligible and the safety
                // margin is real).
                $bottomIdxNow = array_search(self::ESP2_BOTTOM_ID, $results, true);
                if ($bottomIdxNow === false) {
                    throw new \RuntimeException('ESP2 bottom team disappeared from results mid-transaction.');
                }
                $results[$bottomIdxNow] = self::OSASUNA_PARENT_ID;

                $esp2Sim->results = $results;
                $esp2Sim->save();
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
}
