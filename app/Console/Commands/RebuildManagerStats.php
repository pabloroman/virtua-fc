<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Modules\Manager\Services\ManagerStatsRebuilder;
use App\Modules\Migration\MigrationStatus;
use Illuminate\Console\Command;
use Throwable;

/**
 * Recompute `manager_stats` from the locally-available match history.
 *
 * Operational backfill for users whose career achievements were partially
 * wiped by the beta→prod migration upserting on `user_id` instead of `id`
 * (see TableManifest). The rebuilder is idempotent, so this is safe to run
 * for any user — even ones whose stats are already correct.
 *
 *   php artisan app:rebuild-manager-stats --user-id=42
 *   php artisan app:rebuild-manager-stats --migrated
 *   php artisan app:rebuild-manager-stats --all
 */
class RebuildManagerStats extends Command
{
    protected $signature = 'app:rebuild-manager-stats
        {--user-id= : Only rebuild for the given user id}
        {--migrated : Rebuild for every user with migration_status=completed}
        {--all : Rebuild for every user with at least one career game}';

    protected $description = 'Rebuild manager_stats from match history (backfill for migrated users)';

    public function handle(ManagerStatsRebuilder $rebuilder): int
    {
        $users = $this->resolveUsers();
        if ($users === null) {
            return self::FAILURE;
        }

        if ($users->isEmpty()) {
            $this->warn('No users matched the selector — nothing to rebuild.');
            return self::SUCCESS;
        }

        $this->info("Rebuilding manager_stats for {$users->count()} user(s).");

        $totalGames = 0;
        $totalSkipped = 0;
        $totalFailed = 0;

        $bar = $this->output->createProgressBar($users->count());
        $bar->start();

        foreach ($users as $user) {
            try {
                $summary = $rebuilder->rebuildForUser($user);
                $totalGames += $summary['games'];
                $totalSkipped += $summary['skipped'];
            } catch (Throwable $e) {
                $totalFailed++;
                $this->newLine();
                $this->error("User {$user->id}: {$e->getMessage()}");
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Rebuilt {$totalGames} career game(s) across {$users->count()} user(s).");
        if ($totalSkipped > 0) {
            $this->line("Skipped {$totalSkipped} non-career game(s) (tournament mode).");
        }
        if ($totalFailed > 0) {
            $this->error("{$totalFailed} user(s) failed — see errors above.");
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @return \Illuminate\Support\Collection<int, User>|null
     */
    private function resolveUsers(): ?\Illuminate\Support\Collection
    {
        $userId = $this->option('user-id');
        $migrated = (bool) $this->option('migrated');
        $all = (bool) $this->option('all');

        $selectors = (int) ($userId !== null) + (int) $migrated + (int) $all;
        if ($selectors !== 1) {
            $this->error('Pass exactly one of --user-id=<id>, --migrated, or --all.');
            return null;
        }

        if ($userId !== null) {
            $user = User::find((int) $userId);
            if (! $user) {
                $this->error("User {$userId} not found.");
                return null;
            }
            return collect([$user]);
        }

        $query = User::query();
        if ($migrated) {
            $query->where('migration_status', MigrationStatus::COMPLETED->value);
        }

        return $query->get();
    }
}
