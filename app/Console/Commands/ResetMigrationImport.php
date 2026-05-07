<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Modules\Migration\MigrationStatus;
use App\Modules\Migration\Services\MigrationProgress;
use App\Modules\Migration\TableManifest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Operator escape hatch for the beta→prod import side. Wipes every imported
 * tenant row tied to a user and resets their migration_status to `pending` so
 * they can re-run the import from scratch.
 *
 *   php artisan migration:reset-import 42
 *   php artisan migration:reset-import pablo@example.com --force
 *
 * This is the "burn it all down" cleanup. The per-game retry path inside
 * UserImporter::wipeGame() already handles partial failures during a single
 * job run; this command exists for cases where the user's data needs a clean
 * slate before the next attempt (e.g. after a duplicate-key collision left
 * inconsistent state across multiple games).
 */
class ResetMigrationImport extends Command
{
    protected $signature = 'migration:reset-import
        {user : User id (control-plane integer id) or email}
        {--force : Skip the confirmation prompt}';

    protected $description = 'Wipe imported tenant data for a user and reset their migration_status to pending.';

    public function handle(): int
    {
        $user = $this->resolveUser((string) $this->argument('user'));
        if ($user === null) {
            return self::FAILURE;
        }

        $gameIds = DB::table('games')->where('user_id', $user->id)->pluck('id')->all();
        $this->info(sprintf(
            'User: id=%d email=%s migration_status=%s games=%d',
            $user->id,
            $user->email,
            $user->migration_status->value,
            count($gameIds),
        ));

        if (! $this->option('force') && ! $this->confirm("Wipe all {$this->wipeTargetSummary($gameIds)} and reset migration_status to pending?")) {
            $this->line('Aborted.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($gameIds) {
            $tables = array_reverse(TableManifest::TENANT_TABLES_IN_INSERT_ORDER);
            foreach ($tables as $table) {
                $column = $table === 'games' ? 'id' : 'game_id';
                $deleted = DB::table($table)->whereIn($column, $gameIds)->delete();
                if ($deleted > 0) {
                    $this->line(sprintf('  %-32s %d row(s) deleted', $table, $deleted));
                }
            }
        });

        DB::connection('pgsql_control')
            ->table('users')
            ->where('id', $user->id)
            ->update(['migration_status' => MigrationStatus::PENDING->value, 'migration_completed_at' => null]);

        MigrationProgress::clear($user->id);

        $this->info('Done. User can now re-run the import from /migration/import.');

        return self::SUCCESS;
    }

    private function resolveUser(string $value): ?User
    {
        $user = ctype_digit($value)
            ? User::find((int) $value)
            : User::where('email', $value)->first();

        if ($user === null) {
            $this->error("User '{$value}' not found.");

            return null;
        }

        return $user;
    }

    /** @param list<string> $gameIds */
    private function wipeTargetSummary(array $gameIds): string
    {
        return count($gameIds) === 1
            ? '1 game and its tenant data'
            : count($gameIds).' games and their tenant data';
    }
}
