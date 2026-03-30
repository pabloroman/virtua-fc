<?php

namespace App\Console\Commands;

use App\Http\Actions\AdvanceMatchday;
use App\Models\Game;
use App\Models\GameMatch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SimulateSeason extends Command
{
    protected $signature = 'game:simulate {gameId} {matchday}';

    protected $description = 'Simulate all matches up to (and including) the given matchday';

    public function handle(): int
    {
        $game = Game::find($this->argument('gameId'));

        if (! $game) {
            $this->error('Game not found.');

            return 1;
        }

        $targetMatchday = (int) $this->argument('matchday');

        $lastPlayed = $this->lastPlayedMatchday($game);

        if ($lastPlayed >= $targetMatchday) {
            $this->info("Already at matchday {$lastPlayed}.");

            return 0;
        }

        $this->info("Simulating from matchday {$lastPlayed} to {$targetMatchday}...");

        // Disable query logging — it accumulates every SQL string in memory
        // across hundreds of iterations.
        DB::disableQueryLog();
        DB::flushQueryLog();

        $advances = 0;

        while ($advances < 500) {
            $game->refresh();

            if ($this->lastPlayedMatchday($game) >= $targetMatchday) {
                break;
            }

            $hasMatches = GameMatch::where('game_id', $game->id)
                ->where('played', false)
                ->exists();

            if (! $hasMatches) {
                $this->warn('No more matches to play. Season complete.');
                break;
            }

            // Resolve a fresh action instance each iteration to avoid
            // stale references in long-running service singletons.
            $advanceAction = app(AdvanceMatchday::class);
            $advanceAction($game->id);
            $advances++;

            // Force PHP to collect circular references (Eloquent models
            // create model<->relation cycles that only gc_collect_cycles
            // can reclaim).
            gc_collect_cycles();

            $game->refresh();
            $lastPlayed = $this->lastPlayedMatchday($game);
            $mem = round(memory_get_usage() / 1024 / 1024, 1);
            $this->line("  Batch #{$advances} — matchday {$lastPlayed} — {$game->current_date->toDateString()} — {$mem}MB");
        }

        $peak = round(memory_get_peak_usage() / 1024 / 1024, 1);
        $lastPlayed = $this->lastPlayedMatchday($game);
        $this->info("Done. Played {$advances} batches. Current matchday: {$lastPlayed}. Peak memory: {$peak}MB");

        return 0;
    }

    private function lastPlayedMatchday(Game $game): int
    {
        return (int) GameMatch::where('game_id', $game->id)
            ->where('played', true)
            ->whereNull('cup_tie_id')
            ->max('round_number');
    }
}
