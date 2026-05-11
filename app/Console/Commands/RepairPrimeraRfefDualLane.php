<?php

namespace App\Console\Commands;

use App\Models\CompetitionEntry;
use App\Models\Competition;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GameStanding;
use App\Models\SeasonArchive;
use App\Models\SimulatedSeason;
use App\Modules\Competition\Promotions\PrimeraRFEFPromotionRule;
use App\Modules\Finance\Services\SeasonSimulationService;
use App\Modules\Season\Jobs\ProcessSeasonTransition;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Recovery command for games stuck on the dual-truth duplicate-promotion bug
 * in PrimeraRFEFPromotionRule (introduced by #993, "Display standings and
 * results for non-user leagues on demand"). PromotionRelegationProcessor's
 * validatePlan now refuses to swap when the same team appears as both a direct
 * promotion and an ESP3PO bracket winner, leaving the closing pipeline stuck
 * at priority 85.
 *
 * Per-game cleanup:
 *   1. Drop ESP3PO completely (cup_ties, game_matches, game_standings,
 *      competition_entries). The bracket was drawn against an ordering that
 *      can no longer be trusted.
 *   2. Drop the synthetic ESP3A/ESP3B GameStanding and GameMatch rows that
 *      FinalizeOtherLeaguesProcessor wrote. The user's primary group's real
 *      data is also dropped if it still exists; we'll rebuild it from
 *      SeasonArchive in the next step.
 *   3. Drop the ESP3A/ESP3B SimulatedSeason rows so we can recreate them
 *      from authoritative data.
 *   4. Rebuild ESP3A/ESP3B SimulatedSeason rows:
 *      - From season_archives.final_standings if the group was archived
 *        (preserves real finishing order, including the user's earned
 *        promotion).
 *      - Falls back to SeasonSimulationService::simulateLeague otherwise.
 *   5. Verify PrimeraRFEFPromotionRule::getPromotedTeams returns 4 distinct
 *      teams. Abort if not.
 *   6. Re-dispatch ProcessSeasonTransition. The checkpoint at
 *      season_transition_step skips back through completed processors and
 *      lands on PromotionRelegationProcessor, which now succeeds.
 *
 * Safe to re-run: every step is idempotent.
 */
class RepairPrimeraRfefDualLane extends Command
{
    protected $signature = 'app:repair-primera-rfef-dual-lane {gameId} {--dry-run} {--no-redispatch}';

    protected $description = 'Repair a season transition stuck on the ESP3->ESP2 duplicate-promotion bug.';

    private const GROUP_IDS = ['ESP3A', 'ESP3B'];
    private const PLAYOFF_ID = 'ESP3PO';

    public function handle(SeasonSimulationService $simulationService): int
    {
        $gameId = (string) $this->argument('gameId');
        $game = Game::find($gameId);

        if (!$game) {
            $this->error("Game {$gameId} not found.");
            return self::FAILURE;
        }

        if (!$game->season_transition_data) {
            $this->warn('Game has no season_transition_data — it is not in a stuck transition state. Continue anyway?');
            if (!$this->confirm('Proceed?', false)) {
                return self::SUCCESS;
            }
        }

        $this->printPreflight($game);

        if ($this->option('dry-run')) {
            $this->info('Dry run — no changes applied.');
            return self::SUCCESS;
        }

        if (!$this->confirm('Apply repair and re-dispatch?', true)) {
            $this->info('Aborted.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($game, $simulationService) {
            $this->clearPlayoffState($game->id);
            $this->clearGroupState($game->id);
            $this->rebuildSimulatedSeasons($game, $simulationService);
        });

        $verification = $this->verify($game);
        if ($verification !== self::SUCCESS) {
            return $verification;
        }

        if ($this->option('no-redispatch')) {
            $this->info('Repair applied. Re-dispatch skipped (--no-redispatch).');
            return self::SUCCESS;
        }

        ProcessSeasonTransition::dispatch($game->id);
        $this->info('Repair applied and ProcessSeasonTransition re-dispatched.');
        return self::SUCCESS;
    }

    private function printPreflight(Game $game): void
    {
        $this->info("Game: {$game->id}");
        $this->info("User: {$game->user_id}");
        $this->info("Season: {$game->season}");
        $this->info("Primary competition: {$game->competition_id}");
        $this->info("Stuck at step: " . ($game->season_transition_step ?? 'n/a'));

        $rows = [];
        foreach (self::GROUP_IDS as $cid) {
            $rows[] = [
                $cid,
                GameStanding::where('game_id', $game->id)->where('competition_id', $cid)->count(),
                GameMatch::where('game_id', $game->id)->where('competition_id', $cid)->count(),
                SimulatedSeason::where('game_id', $game->id)->where('season', $game->season)
                    ->where('competition_id', $cid)->count(),
                $this->archivedTopForCompetition($game, $cid),
            ];
        }
        $rows[] = [
            self::PLAYOFF_ID,
            GameStanding::where('game_id', $game->id)->where('competition_id', self::PLAYOFF_ID)->count(),
            GameMatch::where('game_id', $game->id)->where('competition_id', self::PLAYOFF_ID)->count(),
            '-',
            '-',
        ];

        $this->table(
            ['Competition', 'Standings rows', 'Match rows', 'SimulatedSeason rows', 'Archived top team'],
            $rows,
        );
    }

    private function archivedTopForCompetition(Game $game, string $competitionId): string
    {
        $archive = SeasonArchive::where('game_id', $game->id)
            ->where('season', $game->season)
            ->first();

        if (!$archive) {
            return 'no archive';
        }

        $rows = collect($archive->final_standings ?? [])
            ->where('competition_id', $competitionId)
            ->sortBy('position')
            ->values();

        if ($rows->isEmpty()) {
            return 'not archived';
        }

        $top = $rows->first();
        return ($top['team_name'] ?? $top['team_id'] ?? 'unknown') . " ({$rows->count()} rows)";
    }

    private function clearPlayoffState(string $gameId): void
    {
        CupTie::where('game_id', $gameId)->where('competition_id', self::PLAYOFF_ID)->delete();
        GameMatch::where('game_id', $gameId)->where('competition_id', self::PLAYOFF_ID)->delete();
        GameStanding::where('game_id', $gameId)->where('competition_id', self::PLAYOFF_ID)->delete();
        CompetitionEntry::where('game_id', $gameId)->where('competition_id', self::PLAYOFF_ID)->delete();
    }

    private function clearGroupState(string $gameId): void
    {
        foreach (self::GROUP_IDS as $cid) {
            GameStanding::where('game_id', $gameId)->where('competition_id', $cid)->delete();
            GameMatch::where('game_id', $gameId)->where('competition_id', $cid)->delete();
        }
    }

    private function rebuildSimulatedSeasons(Game $game, SeasonSimulationService $simulationService): void
    {
        $archive = SeasonArchive::where('game_id', $game->id)
            ->where('season', $game->season)
            ->first();

        $archivedByComp = collect($archive?->final_standings ?? [])
            ->groupBy('competition_id')
            ->map(fn ($rows) => collect($rows)->sortBy('position')->pluck('team_id')->values()->toArray());

        foreach (self::GROUP_IDS as $cid) {
            SimulatedSeason::where('game_id', $game->id)
                ->where('season', $game->season)
                ->where('competition_id', $cid)
                ->delete();

            $archivedOrder = $archivedByComp[$cid] ?? null;
            if (is_array($archivedOrder) && count($archivedOrder) >= 20) {
                SimulatedSeason::create([
                    'game_id' => $game->id,
                    'season' => $game->season,
                    'competition_id' => $cid,
                    'results' => $archivedOrder,
                ]);
                $this->info("[{$cid}] SimulatedSeason rebuilt from SeasonArchive (top: {$archivedOrder[0]}).");
                continue;
            }

            $competition = Competition::find($cid);
            if (!$competition) {
                throw new \RuntimeException("Competition {$cid} not found — cannot fabricate SimulatedSeason.");
            }

            $row = $simulationService->simulateLeague($game, $competition);
            $top = $row->results[0] ?? '(none)';
            $this->warn("[{$cid}] No usable archive; SimulatedSeason fabricated via SeasonSimulationService (top: {$top}).");
        }
    }

    private function verify(Game $game): int
    {
        $rule = app(PrimeraRFEFPromotionRule::class);

        $promoted = $rule->getPromotedTeams($game);
        $relegated = $rule->getRelegatedTeams($game);

        $ids = array_column($promoted, 'teamId');
        $distinct = array_unique($ids);

        $this->newLine();
        $this->info('Verification:');
        $this->info('  Promoted: ' . count($promoted) . ' (distinct: ' . count($distinct) . ')');
        $this->info('  Relegated: ' . count($relegated));
        $this->info('  User team in promoted list: ' . (in_array($game->team_id, $ids, true) ? 'YES' : 'no'));

        $this->table(
            ['Origin', 'Position', 'Team'],
            array_map(fn ($p) => [$p['origin'] ?? '-', (string) $p['position'], $p['teamName'] ?? $p['teamId']], $promoted),
        );

        if (count($promoted) !== 4 || count($distinct) !== 4) {
            $this->error('Verification failed: expected 4 distinct promoted teams.');
            return self::FAILURE;
        }

        if (count($relegated) !== 4) {
            $this->error('Verification failed: expected 4 relegated teams from ESP2.');
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
