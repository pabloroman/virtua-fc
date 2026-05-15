<?php

namespace App\Console\Commands;

use App\Modules\Manager\Services\OrphanManagerStatsMerger;
use Illuminate\Console\Command;

/**
 * One-off ops command for the beta→prod migration tail: merges career stats
 * from the OLD server into NEW orphan `manager_stats` rows (`game_id IS NULL`).
 *
 * Each orphan represents a career the user deleted on NEW; the matching OLD
 * row is the same user's still-active run at the same team. Stats are summed
 * because the two are non-overlapping runs — see OrphanManagerStatsMerger.
 *
 * Producing the input file (run on OLD):
 *
 *   psql -At -c "SELECT json_agg(t) FROM (
 *     SELECT user_id, team_id, matches_played, matches_won, matches_drawn,
 *            matches_lost, win_percentage, current_unbeaten_streak,
 *            longest_unbeaten_streak, seasons_completed
 *     FROM manager_stats WHERE game_id IS NOT NULL
 *   ) t" > /tmp/old-manager-stats.json
 *
 * Then on NEW:
 *
 *   php artisan app:merge-orphan-manager-stats --from=/tmp/old-manager-stats.json --dry-run
 *   php artisan app:merge-orphan-manager-stats --from=/tmp/old-manager-stats.json
 */
class MergeOrphanManagerStats extends Command
{
    protected $signature = 'app:merge-orphan-manager-stats
        {--from= : Path to JSON file with OLD-server manager_stats rows (array of objects)}
        {--dry-run : Report what would change without persisting}';

    protected $description = 'Merge OLD-server manager_stats into NEW orphan rows (game_id IS NULL)';

    public function handle(OrphanManagerStatsMerger $merger): int
    {
        $path = $this->option('from');
        if (! is_string($path) || $path === '') {
            $this->error('Pass --from=<path-to-old.json>.');

            return self::FAILURE;
        }

        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $rows = json_decode((string) file_get_contents($path), true);
        if (! is_array($rows)) {
            $this->error('Could not parse JSON or top-level value is not an array.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $summary = $merger->merge($rows, $dryRun);

        $this->newLine();
        $this->info(sprintf(
            '%s %d orphan row(s).',
            $dryRun ? 'Would merge' : 'Merged',
            count($summary['merged']),
        ));

        foreach ($summary['merged'] as $entry) {
            $this->line(sprintf(
                '  user=%d team=%s  matches:%d→%d  seasons:%d→%d  longest_streak:%d→%d',
                $entry['user_id'],
                $entry['team_id'],
                $entry['before']['matches_played'],
                $entry['after']['matches_played'],
                $entry['before']['seasons_completed'],
                $entry['after']['seasons_completed'],
                $entry['before']['longest_unbeaten_streak'],
                $entry['after']['longest_unbeaten_streak'],
            ));
        }

        $this->reportSkipped(
            'orphan(s) had no OLD-server match',
            $summary['no_old_match'],
            fn (array $e) => "  user={$e['user_id']} team={$e['team_id']}",
        );

        $this->reportSkipped(
            'orphan(s) matched multiple OLD rows (skipped — resolve manually)',
            $summary['ambiguous_old'],
            fn (array $e) => "  user={$e['user_id']} team={$e['team_id']}  old_rows={$e['count']}",
        );

        $this->reportSkipped(
            '(user, team) pair(s) had multiple NEW orphans (skipped — resolve manually)',
            $summary['ambiguous_orphan'],
            fn (array $e) => "  user={$e['user_id']} team={$e['team_id']}  orphans={$e['count']}",
        );

        return self::SUCCESS;
    }

    /**
     * @param list<array<string,mixed>> $entries
     */
    private function reportSkipped(string $label, array $entries, callable $formatter): void
    {
        if (empty($entries)) {
            return;
        }

        $this->newLine();
        $this->warn(sprintf('%d %s:', count($entries), $label));
        foreach ($entries as $entry) {
            $this->line($formatter($entry));
        }
    }
}
