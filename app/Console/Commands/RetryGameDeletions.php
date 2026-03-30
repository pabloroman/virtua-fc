<?php

namespace App\Console\Commands;

use App\Jobs\DeleteGameJob;
use App\Models\Game;
use Illuminate\Console\Command;

class RetryGameDeletions extends Command
{
    protected $signature = 'app:retry-game-deletions';

    protected $description = 'Requeue delete jobs for games stuck in deleting state';

    public function handle(): int
    {
        $games = Game::whereNotNull('deleting_at')->get();

        if ($games->isEmpty()) {
            $this->info('No stuck games found.');

            return Command::SUCCESS;
        }

        $this->info("Found {$games->count()} game(s) stuck in deleting state.");

        foreach ($games as $game) {
            DeleteGameJob::dispatch($game->id);
            $this->line("  - Queued deletion for game {$game->id} (stuck since {$game->deleting_at})");
        }

        $this->info("Dispatched {$games->count()} delete job(s).");

        return Command::SUCCESS;
    }
}
