<?php

namespace App\Console\Commands;

use App\Http\Actions\AdvanceMatchday;
use App\Models\Game;
use App\Models\GameMatch;
use Illuminate\Console\Command;

class SimulateSeason extends Command
{
    protected $signature = 'game:simulate {gameId} {matchday}';

    protected $description = 'Simulate all matches up to (and including) the given matchday';

    public function handle(): int
    {
        $game = Game::find($this->argument('gameId'));

        if (!$game) {
            $this->error('Game not found.');
            return 1;
        }

        $targetMatchday = (int) $this->argument('matchday');

        if ($game->current_matchday >= $targetMatchday) {
            $this->info("Already at matchday {$game->current_matchday}.");
            return 0;
        }

        $this->info("Simulating from matchday {$game->current_matchday} to {$targetMatchday}...");

        $advanceAction = app(AdvanceMatchday::class);
        $advances = 0;

        while ($advances < 500) {
            $game->refresh();

            if ($game->current_matchday >= $targetMatchday) {
                break;
            }

            $hasMatches = GameMatch::where('game_id', $game->id)
                ->where('played', false)
                ->exists();

            if (!$hasMatches) {
                $this->warn('No more matches to play. Season complete.');
                break;
            }

            $advanceAction($game->id);
            $advances++;

            $game->refresh();
            $this->line("  Batch #{$advances} — matchday {$game->current_matchday}");
        }

        $this->info("Done. Played {$advances} batches. Current matchday: {$game->current_matchday}");

        return 0;
    }
}
