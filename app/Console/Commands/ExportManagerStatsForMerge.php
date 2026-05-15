<?php

namespace App\Console\Commands;

use App\Models\ManagerStats;
use Illuminate\Console\Command;

/**
 * Companion to `app:merge-orphan-manager-stats`. Run this on the OLD beta
 * server to produce the JSON file the merge command consumes on NEW.
 *
 *   php artisan app:export-manager-stats-for-merge --out=/tmp/old-manager-stats.json
 *
 * Omitting --out prints to stdout, so this also works:
 *
 *   php artisan app:export-manager-stats-for-merge > /tmp/old-manager-stats.json
 *
 * Only rows with a non-null game_id are exported — orphan rows on OLD have
 * nothing to merge into and would just add noise.
 */
class ExportManagerStatsForMerge extends Command
{
    protected $signature = 'app:export-manager-stats-for-merge
        {--out= : Write JSON to this path instead of stdout}';

    protected $description = 'Export manager_stats rows as JSON (companion to app:merge-orphan-manager-stats)';

    public function handle(): int
    {
        $rows = ManagerStats::query()
            ->whereNotNull('game_id')
            ->get([
                'user_id',
                'team_id',
                'matches_played',
                'matches_won',
                'matches_drawn',
                'matches_lost',
                'win_percentage',
                'current_unbeaten_streak',
                'longest_unbeaten_streak',
                'seasons_completed',
            ])
            ->map(fn (ManagerStats $row) => [
                'user_id' => (int) $row->user_id,
                'team_id' => (string) $row->team_id,
                'matches_played' => (int) $row->matches_played,
                'matches_won' => (int) $row->matches_won,
                'matches_drawn' => (int) $row->matches_drawn,
                'matches_lost' => (int) $row->matches_lost,
                'win_percentage' => (float) $row->win_percentage,
                'current_unbeaten_streak' => (int) $row->current_unbeaten_streak,
                'longest_unbeaten_streak' => (int) $row->longest_unbeaten_streak,
                'seasons_completed' => (int) $row->seasons_completed,
            ])
            ->values()
            ->all();

        $json = json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $out = $this->option('out');
        if (is_string($out) && $out !== '') {
            file_put_contents($out, $json);
            $this->info(sprintf('Wrote %d row(s) to %s', count($rows), $out));

            return self::SUCCESS;
        }

        $this->line($json);

        return self::SUCCESS;
    }
}
