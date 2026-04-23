<?php

namespace App\Console\Commands;

use App\Models\Competition;
use App\Models\SeasonArchive;
use Illuminate\Console\Command;

/**
 * Writes `competition_id` onto every row in `season_archives.final_standings`
 * so the league tier of each archived season can be read without scanning
 * match_results. Idempotent: rows already carrying the field are skipped
 * at the database level.
 *
 * Memory strategy: archives can be large (player_season_stats + match_results
 * are the heavy blobs). We only select the three JSON fields we need, and
 * we walk the table with small chunkById() pages so memory stays bounded
 * regardless of table size.
 */
class BackfillSeasonArchiveCompetitionIds extends Command
{
    protected $signature = 'app:backfill-season-archive-competition-ids
                            {--chunk=50 : Rows loaded into memory per page}
                            {--dry-run : Report what would change without writing}';

    protected $description = 'Backfill competition_id onto season_archives.final_standings rows.';

    public function handle(): int
    {
        $chunkSize = max(1, (int) $this->option('chunk'));
        $dryRun = (bool) $this->option('dry-run');

        // League competitions form a small reference set (a dozen or so rows).
        // Load them once and key by id so each archive resolution is O(1).
        $leagueCompetitionIds = Competition::where('role', Competition::ROLE_LEAGUE)
            ->pluck('id')
            ->flip();

        // Skip archives whose first standings row already carries competition_id.
        // Filtering in SQL avoids decoding JSON blobs for already-backfilled rows
        // on re-runs, which matters on a large table.
        $baseQuery = SeasonArchive::query()
            ->whereRaw("(final_standings->0->>'competition_id') IS NULL");

        $total = (clone $baseQuery)->count();

        if ($total === 0) {
            $this->info('No archives require backfill.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Backfilling %d season archives (chunk=%d%s)…',
            $total,
            $chunkSize,
            $dryRun ? ', dry-run' : '',
        ));

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $updated = 0;
        $unresolved = 0;
        $empty = 0;

        // select() trims out the heavy JSON columns we don't need (player_season_stats,
        // season_awards, transfer_activity, transition_log), keeping per-page memory low.
        $baseQuery
            ->select(['id', 'final_standings', 'match_results'])
            ->chunkById($chunkSize, function ($chunk) use (
                &$updated, &$unresolved, &$empty, $leagueCompetitionIds, $dryRun, $bar
            ) {
                foreach ($chunk as $archive) {
                    $standings = $archive->final_standings ?? [];

                    if (empty($standings)) {
                        $empty++;
                        $bar->advance();
                        continue;
                    }

                    $leagueId = $this->resolveLeagueId($archive, $leagueCompetitionIds);

                    if (!$leagueId) {
                        $unresolved++;
                        $bar->advance();
                        continue;
                    }

                    foreach ($standings as &$row) {
                        $row['competition_id'] = $leagueId;
                    }
                    unset($row);

                    if (!$dryRun) {
                        SeasonArchive::where('id', $archive->id)
                            ->update(['final_standings' => $standings]);
                    }

                    $updated++;
                    $bar->advance();
                }

                // JSON decoding leaves large transient arrays behind; nudge the
                // collector between pages so long runs don't fragment memory.
                unset($chunk);
                gc_collect_cycles();
            });

        $bar->finish();
        $this->newLine(2);

        $this->info(sprintf(
            'Done. Updated: %d, Unresolved: %d, Empty standings: %d%s',
            $updated,
            $unresolved,
            $empty,
            $dryRun ? ' (dry-run — no rows written)' : '',
        ));

        if ($unresolved > 0) {
            $this->warn('Unresolved archives had no league competition_id in match_results; they may be from pre-league-handler games or lacking match data.');
        }

        return self::SUCCESS;
    }

    /**
     * Find the league competition_id for an archive by scanning its
     * match_results for the first entry whose competition_id belongs to
     * a league. Uses a flipped id map for O(1) membership checks.
     *
     * @param  \Illuminate\Support\Collection<string, int>  $leagueCompetitionIds
     */
    private function resolveLeagueId(SeasonArchive $archive, $leagueCompetitionIds): ?string
    {
        foreach ($archive->match_results ?? [] as $match) {
            $cid = $match['competition_id'] ?? null;
            if ($cid !== null && $leagueCompetitionIds->has($cid)) {
                return $cid;
            }
        }

        return null;
    }
}
