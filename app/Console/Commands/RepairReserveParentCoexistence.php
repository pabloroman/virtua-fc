<?php

namespace App\Console\Commands;

use App\Models\Game;
use App\Modules\Competition\Promotions\RepairOutcome;
use App\Modules\Competition\Promotions\ReserveParentCoexistenceRepairer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Operator entry point for repairing reserve/parent league assignments on a
 * game stuck mid season-transition because
 * {@see \App\Modules\Competition\Promotions\CountryPromotionRelegationPlanner::validatePlan}
 * detected an INVERTED or COEXISTENCE violation.
 *
 * The detection/swap/apply logic lives in
 * {@see ReserveParentCoexistenceRepairer} (also used for the in-band self-heal
 * inside PromotionRelegationProcessor). This command is a thin CLI wrapper:
 * it enforces the operator-facing guards (game transitioning, checkpointed at
 * the step right before the planner runs), prints the planned swaps, and only
 * writes when --apply is passed.
 *
 * Defaults to dry-run. Safe to leave in the tree — the guards guarantee it only
 * acts on a game checkpointed at the pre-PromotionRelegation step, and the
 * repairer only emits swaps for pairs that would currently trip validatePlan.
 *
 * After applying, run app:diagnose-stuck-game to confirm flags are gone, then
 * app:resume-season-transition to re-run the closing pipeline (the planner
 * builds a fresh snapshot, replans, and validatePlan passes).
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
    private const EXPECTED_STEP = 23;

    public function __construct(private readonly ReserveParentCoexistenceRepairer $repairer)
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

        $result = $this->repairer->plan($game);

        if ($result->outcome === RepairOutcome::Unsafe) {
            $this->error($result->reason ?? 'Cannot repair automatically — manual investigation required.');
            return self::FAILURE;
        }
        if ($result->outcome === RepairOutcome::NothingToFix) {
            $this->info('No reserve/parent inversions or coexistences detected. Nothing to repair.');
            return self::SUCCESS;
        }

        $this->info('--- Detected issues ---');
        foreach ($result->issues as $i) {
            $this->line("  {$i['kind']}: reserve {$i['reserve']['name']} ({$i['reserve']['league']}) <- parent {$i['parent']['name']} ({$i['parent']['league']})");
        }
        $this->line('');

        $this->info('--- Planned swaps ---');
        foreach ($result->mutations as $m) {
            $swap = $m['swap'];
            $this->line("  {$swap['reason']}");
            $this->line("    competition_entries: {$swap['teamA_name']} {$swap['leagueA']} -> {$swap['leagueB']}");
            $this->line("    competition_entries: {$swap['teamB_name']} {$swap['leagueB']} -> {$swap['leagueA']}");
            $this->line("    {$swap['leagueA']} ({$m['slotA']['kind']}): {$swap['teamA_name']} -> {$swap['teamB_name']}");
            $this->line("    {$swap['leagueB']} ({$m['slotB']['kind']}): {$swap['teamB_name']} -> {$swap['teamA_name']}");
        }
        $this->line('');

        if (!$apply) {
            $this->info('Dry run — no changes written. Pass --apply to execute.');
            return self::SUCCESS;
        }

        try {
            DB::transaction(fn () => $this->repairer->apply($game, $result));
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
