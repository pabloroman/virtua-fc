<?php

namespace App\Console\Commands;

use App\Models\Game;
use App\Modules\Player\Jobs\BackfillGamePlayerBiography;
use Illuminate\Console\Command;

class BackfillPlayerBiography extends Command
{
    protected $signature = 'app:backfill-player-biography
                            {--limit= : Max games to dispatch this run (default: all remaining)}
                            {--queue=cleanup : Queue to dispatch jobs onto}';

    protected $description = 'Dispatch one BackfillGamePlayerBiography job per game with NULL game_players biography rows.';

    public function handle(): int
    {
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $queue = $this->option('queue');

        $query = Game::query()
            ->select('id')
            ->whereExists(fn ($q) => $q
                ->selectRaw(1)
                ->from('game_players')
                ->whereColumn('game_players.game_id', 'games.id')
                ->whereNull('name'))
            ->orderBy('id');

        if ($limit !== null) {
            $query->limit($limit);
        }

        $count = 0;
        $query->lazyById()->each(function (Game $game) use ($queue, &$count) {
            BackfillGamePlayerBiography::dispatch($game->id)->onQueue($queue);
            $count++;
        });

        $this->info("Dispatched {$count} backfill jobs onto queue '{$queue}'.");

        return self::SUCCESS;
    }
}
