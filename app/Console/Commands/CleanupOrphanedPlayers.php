<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupOrphanedPlayers extends Command
{
    protected $signature = 'app:cleanup-orphaned-players
                            {--dry-run : Count orphaned players without deleting}
                            {--chunk=1000 : Number of records to delete per batch}';

    protected $description = 'Delete orphaned generated players that have no game_player references.';

    public function handle(): int
    {
        $chunkSize = (int) $this->option('chunk');
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $count = $this->orphanedQuery()->count();
            $this->info("[DRY RUN] Found {$count} orphaned generated player(s).");

            return Command::SUCCESS;
        }

        $totalDeleted = 0;

        do {
            $ids = $this->orphanedQuery()
                ->limit($chunkSize)
                ->pluck('id')
                ->all();

            if (empty($ids)) {
                break;
            }

            $deleted = DB::table('players')->whereIn('id', $ids)->delete();
            $totalDeleted += $deleted;

            $this->line("  Deleted {$deleted} orphaned player(s) ({$totalDeleted} total).");
        } while (count($ids) === $chunkSize);

        $this->info("Deleted {$totalDeleted} orphaned generated player(s).");

        return Command::SUCCESS;
    }

    private function orphanedQuery()
    {
        return DB::table('players')
            ->where('transfermarkt_id', 'like', 'gen-%')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('game_players')
                    ->whereColumn('game_players.player_id', 'players.id');
            });
    }
}
