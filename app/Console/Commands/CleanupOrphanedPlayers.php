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
            $count = 0;
            foreach ($this->iterateGeneratedPlayerIdChunks($chunkSize) as $chunk) {
                $count += count($this->orphanIdsFromChunk($chunk));
            }
            $this->info("[DRY RUN] Found {$count} orphaned generated player(s).");

            return Command::SUCCESS;
        }

        $totalDeleted = 0;

        foreach ($this->iterateGeneratedPlayerIdChunks($chunkSize) as $chunk) {
            $orphanIds = $this->orphanIdsFromChunk($chunk);
            if ($orphanIds === []) {
                continue;
            }

            $deleted = DB::connection('pgsql_control')
                ->table('players')
                ->whereIn('id', $orphanIds)
                ->delete();

            $totalDeleted += $deleted;
            $this->line("  Deleted {$deleted} orphaned player(s) ({$totalDeleted} total).");
        }

        $this->info("Deleted {$totalDeleted} orphaned generated player(s).");

        return Command::SUCCESS;
    }

    /**
     * Stream every chunk of `gen-%` player ids from the control plane.
     *
     * @return \Generator<int, list<string>>
     */
    private function iterateGeneratedPlayerIdChunks(int $chunkSize): \Generator
    {
        $cursor = null;

        while (true) {
            $query = DB::connection('pgsql_control')
                ->table('players')
                ->where('transfermarkt_id', 'like', 'gen-%')
                ->orderBy('id')
                ->limit($chunkSize);

            if ($cursor !== null) {
                $query->where('id', '>', $cursor);
            }

            $ids = $query->pluck('id')->all();
            if ($ids === []) {
                return;
            }

            yield $ids;

            if (count($ids) < $chunkSize) {
                return;
            }
            $cursor = end($ids);
        }
    }

    /**
     * Of a candidate batch (control), return the ones with no game_players
     * reference (tenant). Splitting the previous correlated whereNotExists
     * keeps each query single-plane.
     *
     * @param  list<string>  $candidateIds
     * @return list<string>
     */
    private function orphanIdsFromChunk(array $candidateIds): array
    {
        if ($candidateIds === []) {
            return [];
        }

        $stillReferenced = DB::table('game_players')
            ->whereIn('player_id', $candidateIds)
            ->distinct()
            ->pluck('player_id')
            ->all();

        return array_values(array_diff($candidateIds, $stillReferenced));
    }
}
