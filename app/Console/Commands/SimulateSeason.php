<?php

namespace App\Console\Commands;

use App\Models\Game;
use App\Models\GameMatch;
use App\Modules\Match\Services\FastModeService;
use App\Modules\Match\Services\MatchdayAdvanceCoordinator;
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

        // Run as fast mode so the orchestrator finalizes the user's match
        // inline. Without fastForward the HTTP flow takes over: the user's
        // match is left as pending_finalization_match_id waiting for a
        // FinalizeMatch handoff that never comes in console context.
        $fastModeWasEntered = $game->isFastMode();
        if (! $fastModeWasEntered) {
            app(FastModeService::class)->enter($game);
        }

        $advances = 0;

        try {
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

                // Advance synchronously. The HTTP AdvanceMatchday action dispatches
                // to the queue so the UI can show a loading screen; console commands
                // need inline completion, so runSync it.
                if (! app(MatchdayAdvanceCoordinator::class)->runSync($game->id, fastForward: true)) {
                    $this->warn('Could not claim advancing flag — another process may be running.');
                    break;
                }
                $advances++;

                // The orchestrator queues a ProcessCareerActions job on the
                // gameplay queue at the end of advance(); its handler clears
                // career_actions_processing_at. The next claim() refuses to
                // run while that flag is set, so we have to wait for it. Drain
                // anything queue:work can grab itself (so we don't depend on
                // Horizon being up), then wait briefly in case Horizon already
                // picked it up and is still running it.
                $this->drainCareerActions($game->id);

                // Force PHP to collect circular references (Eloquent models
                // create model<->relation cycles that only gc_collect_cycles
                // can reclaim).
                gc_collect_cycles();

                $game->refresh();
                $lastPlayed = $this->lastPlayedMatchday($game);
                $mem = round(memory_get_usage() / 1024 / 1024, 1);
                $this->line("  Batch #{$advances} — matchday {$lastPlayed} — {$game->current_date->toDateString()} — {$mem}MB");
            }
        } finally {
            if (! $fastModeWasEntered) {
                app(FastModeService::class)->exit($game->refresh());
            }
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

    private function drainCareerActions(string $gameId): void
    {
        $this->callSilently('queue:work', [
            '--queue' => 'gameplay',
            '--stop-when-empty' => true,
            '--tries' => 1,
        ]);

        // If Horizon grabbed the job before queue:work above, the flag may
        // still be set while Horizon finishes. Poll for up to ~30s.
        $deadline = microtime(true) + 30;
        while (microtime(true) < $deadline) {
            $stillProcessing = (bool) Game::where('id', $gameId)
                ->whereNotNull('career_actions_processing_at')
                ->exists();

            if (! $stillProcessing) {
                return;
            }

            usleep(100_000); // 100ms
        }
    }
}
