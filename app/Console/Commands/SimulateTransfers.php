<?php

namespace App\Console\Commands;

use App\Models\Game;
use App\Models\GameNotification;
use App\Models\GameTransfer;
use App\Modules\Transfer\Services\AITransferMarketService;
use App\Support\Money;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SimulateTransfers extends Command
{
    protected $signature = 'transfers:simulate
                            {--game= : Game ID to use (defaults to most recent)}
                            {--window=summer : Transfer window (summer or winter)}
                            {--runs=5 : Number of simulations to run}';

    protected $description = 'Run AI transfer simulations for debugging and realism verification';

    public function handle(AITransferMarketService $service): int
    {
        ini_set('memory_limit', '512M');

        $gameId = $this->option('game');
        $window = $this->option('window');
        $runs = (int) $this->option('runs');

        $game = $gameId
            ? Game::with('team')->find($gameId)
            : Game::with('team')->latest('created_at')->first();

        if (! $game) {
            $this->error('No game found. Create one with php artisan app:create-test-game');
            return 1;
        }

        $this->info("Game: {$game->team->name} | Season {$game->season} | Window: {$window}");
        $this->info("Running {$runs} simulation(s)...");
        $this->newLine();

        $pdo = DB::connection()->getPdo();

        // Wrap everything in a single transaction — nothing persists
        $pdo->beginTransaction();

        try {
            for ($run = 1; $run <= $runs; $run++) {
                $this->info("━━━ Run {$run}/{$runs} ━━━");

                try {
                    $pdo->exec('SAVEPOINT run_snapshot');

                    // Delete any existing notification so processWindowClose doesn't skip
                    GameNotification::where('game_id', $game->id)
                        ->where('type', GameNotification::TYPE_AI_TRANSFER_ACTIVITY)
                        ->delete();

                    // Run the transfer engine
                    $service->processWindowClose($game, $window);

                    // Read transfers from the ledger
                    $transferRecords = GameTransfer::with(['gamePlayer.player', 'fromTeam', 'toTeam'])
                        ->where('game_id', $game->id)
                        ->where('season', $game->season)
                        ->where('window', $window)
                        ->get();

                    $this->printResults($transferRecords);
                } catch (\Throwable $e) {
                    $this->error("  Error: {$e->getMessage()}");
                    $this->error("  at {$e->getFile()}:{$e->getLine()}");
                }

                // Rollback to savepoint so next run starts fresh
                $pdo->exec('ROLLBACK TO SAVEPOINT run_snapshot');

                $this->newLine();
            }
        } finally {
            $pdo->rollBack();
        }

        $this->info('Done. All runs were rolled back — no data was modified.');
        return 0;
    }

    private function printResults($transferRecords): void
    {
        $freeAgents = $transferRecords->where('type', GameTransfer::TYPE_FREE_AGENT);
        $paidTransfers = $transferRecords->where('type', GameTransfer::TYPE_TRANSFER);

        // Split paid transfers into domestic (both teams have from_team) vs foreign-like
        $domestic = $paidTransfers->filter(fn ($t) => $t->from_team_id && $t->to_team_id);
        $foreign = collect(); // All paid transfers are technically domestic or foreign — we just display them all

        $this->info("  Transfers: {$domestic->count()} transfers | Free agents: {$freeAgents->count()}");
        $this->newLine();

        if ($domestic->isNotEmpty()) {
            $this->comment("  Transfers:");
            foreach ($domestic as $t) {
                $pos = str_pad($t->gamePlayer->position ?? '?', 20);
                $playerName = $t->gamePlayer->name ?? 'Unknown';
                $fromName = $t->fromTeam->name ?? '?';
                $toName = $t->toTeam->name ?? '?';
                $fee = Money::format($t->transfer_fee);
                $this->line("    {$pos} {$playerName}  {$fromName} → {$toName}  {$fee}");
            }
            $this->newLine();
        }

        if ($freeAgents->isNotEmpty()) {
            $this->comment("  Free agent signings:");
            foreach ($freeAgents as $t) {
                $pos = str_pad($t->gamePlayer->position ?? '?', 20);
                $playerName = $t->gamePlayer->name ?? 'Unknown';
                $toName = $t->toTeam->name ?? '?';
                $this->line("    {$pos} {$playerName} → {$toName}");
            }
            $this->newLine();
        }
    }
}
