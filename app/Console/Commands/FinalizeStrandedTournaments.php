<?php

namespace App\Console\Commands;

use App\Events\TournamentEnded;
use App\Models\Game;
use App\Models\TournamentSummary;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FinalizeStrandedTournaments extends Command
{
    protected $signature = 'app:finalize-stranded-tournaments {--dry-run} {--limit=}';

    protected $description = 'Finalize tournament-mode games that were left in limbo (e.g. legacy fast-mode runs): dispatch TournamentEnded so snapshot/soft-delete/activation listeners run.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limit = $this->option('limit') !== null ? max(1, (int) $this->option('limit')) : null;

        $query = Game::query()
            ->where('game_mode', Game::MODE_TOURNAMENT)
            ->whereNull('deleting_at')
            ->whereDoesntHave('matches', fn ($q) => $q->where('played', false))
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('tournament_summaries')
                    ->whereColumn('tournament_summaries.original_game_id', 'games.id');
            });

        if ($limit !== null) {
            $query->limit($limit);
        }

        $candidates = $query->pluck('id');
        $total = $candidates->count();

        $this->info("Found {$total} stranded tournament game(s).");

        if ($dryRun || $total === 0) {
            if ($dryRun) {
                $this->warn('Dry run — no dispatch.');
            }

            return self::SUCCESS;
        }

        $finalized = 0;
        $skipped = 0;

        foreach ($candidates as $gameId) {
            $dispatched = DB::transaction(function () use ($gameId) {
                $game = Game::where('id', $gameId)->lockForUpdate()->first();

                if (! $game) {
                    return false;
                }

                if ($game->deleting_at !== null) {
                    return false;
                }

                if ($game->matches()->where('played', false)->exists()) {
                    return false;
                }

                if (TournamentSummary::where('original_game_id', $game->id)->exists()) {
                    return false;
                }

                TournamentEnded::dispatch($game);

                return true;
            });

            if ($dispatched) {
                $finalized++;
            } else {
                $skipped++;
            }
        }

        $this->info("Finalized: {$finalized}. Skipped (raced/stale): {$skipped}.");

        return self::SUCCESS;
    }
}
