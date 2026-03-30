<?php

namespace App\Console\Commands;

use App\Modules\Season\Services\GameDeletionService;
use App\Models\Game;
use Illuminate\Console\Command;

class CleanupGames extends Command
{
    protected $signature = 'app:cleanup-games
                            {--dry-run : Preview what would be deleted without actually deleting}
                            {--days=2 : Number of days of inactivity after which a game is considered stale}
                            {--all : Include all inactive games, not just unstarted ones}';

    protected $description = 'Delete stale games based on inactivity. By default only unstarted games (setup not completed); use --all to include any inactive game.';

    public function handle(GameDeletionService $service): int
    {
        $days = (int) $this->option('days');
        $dryRun = $this->option('dry-run');
        $all = $this->option('all');

        $query = Game::where('updated_at', '<', now()->subDays($days));

        if (! $all) {
            $query->whereNull('setup_completed_at');
        }

        $staleGames = $query->get();

        if ($staleGames->isEmpty()) {
            $this->info('No stale games found.');

            return Command::SUCCESS;
        }

        $this->info(($dryRun ? '[DRY RUN] ' : '')."Found {$staleGames->count()} stale game(s).");

        foreach ($staleGames as $game) {
            $this->line("  - {$game->id} ({$game->team?->name ?? 'unknown'})");
            if (! $dryRun) {
                $service->delete($game);
            }
        }

        return Command::SUCCESS;
    }
}
