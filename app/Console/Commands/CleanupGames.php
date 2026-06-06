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
                            {--all : Include all inactive games, not just unstarted ones}
                            {--stuck : Target stuck games (pending finalization or needing setup in the base season)}';

    protected $description = 'Delete stale games based on inactivity. By default only unstarted games (setup not completed); use --all to include any inactive game, or --stuck to target stuck games.';

    public function handle(GameDeletionService $service): int
    {
        $days = (int) $this->option('days');
        $dryRun = $this->option('dry-run');
        $all = $this->option('all');
        $stuck = $this->option('stuck');

        if ($stuck) {
            $staleGames = Game::where('updated_at', '<', now()->subDays($days))
                ->where(function ($q) {
                    $q->whereNotNull('pending_finalization_match_id')
                        ->orWhere(function ($q2) {
                            $q2->where('season', config('season.current'))
                                ->where('needs_new_season_setup', true);
                        });
                })->with('team')->get();
        } else {
            $query = Game::where('updated_at', '<', now()->subDays($days));

            if (! $all) {
                $query->whereNull('setup_completed_at');
            }

            $staleGames = $query->with('team')->get();
        }

        if ($staleGames->isEmpty()) {
            $this->info('No stale games found.');

            return Command::SUCCESS;
        }

        $this->info(($dryRun ? '[DRY RUN] ' : '')."Found {$staleGames->count()} stale game(s).");

        foreach ($staleGames as $game) {
            $teamName = $game->team?->name ?? 'unknown';
            $this->line("  - {$game->id} ({$teamName})");
            if (! $dryRun) {
                $service->delete($game);
            }
        }

        return Command::SUCCESS;
    }
}
