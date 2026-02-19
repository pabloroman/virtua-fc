<?php

namespace App\Console\Commands;

use App\Modules\Season\Services\GameDeletionService;
use App\Models\Game;
use Illuminate\Console\Command;

class CleanupUnplayedGames extends Command
{
    protected $signature = 'app:cleanup-unplayed-games
                            {--dry-run : Preview what would be deleted without actually deleting}
                            {--days=2 : Number of days after which an unplayed game is considered stale}';

    protected $description = 'Delete games that were never played (matchday 0) and are older than the specified threshold';

    public function handle(GameDeletionService $service): int
    {
        $days = (int) $this->option('days');
        $dryRun = $this->option('dry-run');

        $staleGames = Game::where('current_matchday', 0)
            ->where('created_at', '<', now()->subDays($days))
            ->get();

        if ($staleGames->isEmpty()) {
            $this->info('No stale games found.');

            return Command::SUCCESS;
        }

        $this->info(($dryRun ? '[DRY RUN] ' : '')."Found {$staleGames->count()} stale game(s).");

        foreach ($staleGames as $game) {
            $this->line("  - Game {$game->id} (user: {$game->user_id}, team: {$game->team_id}, created: {$game->created_at})");

            if (! $dryRun) {
                $service->delete($game);
            }
        }

        $this->info(($dryRun ? '[DRY RUN] Would delete' : 'Deleted')." {$staleGames->count()} stale game(s).");

        return Command::SUCCESS;
    }
}
