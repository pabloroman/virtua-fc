<?php

namespace App\Modules\Migration\Services;

use App\Modules\Migration\TableManifest;
use Illuminate\Support\Facades\DB;

/**
 * Inverse of UserExporter. Takes the JSON envelopes produced on the export
 * side and inserts every row into this deployment's database.
 *
 * Per-game work runs inside a transaction on the tenant connection so a
 * partial failure on one game doesn't leave dangling orphans. Control-plane
 * writes use a separate transaction on `pgsql_control` for the same reason.
 *
 * Retries are idempotent: every per-game import wipes existing rows for that
 * game id (in reverse manifest order so FKs are satisfied) before inserting.
 * This means a job that crashed half-way through can be re-run from scratch
 * without hitting duplicate-key errors on the games already imported.
 *
 * Progress is reported by the caller (MigrationImportJob) via
 * MigrationProgress.
 */
class UserImporter
{
    /**
     * Per-table row counts written by the most recent importGame() call.
     * Exposed for diagnostic logging from the job — when this is all zeros
     * after a non-empty payload, we know the importer is silently dropping
     * data.
     *
     * @var array<string, array{wiped: int, inserted: int}>
     */
    private array $lastInsertCounts = [];

    /** @return array<string, array{wiped: int, inserted: int}> */
    public function lastInsertCounts(): array
    {
        return $this->lastInsertCounts;
    }

    public function importControlPlane(int $userId, array $controlPlane): void
    {
        DB::connection('pgsql_control')->transaction(function () use ($controlPlane) {
            foreach (TableManifest::CONTROL_PLANE_TABLES as $table => $meta) {
                $rows = $controlPlane[$table] ?? [];
                if (empty($rows)) {
                    continue;
                }

                $this->upsertOnControl($table, $meta['row_key'], $rows);
            }
        });
    }

    /** @param array{game_id: string, tables: array<string, list<array>>} $game */
    public function importGame(array $game): void
    {
        $gameId = $game['game_id'];
        $this->lastInsertCounts = [];

        DB::transaction(function () use ($game, $gameId) {
            $wipeCounts = $this->wipeGame($gameId);

            foreach (TableManifest::TENANT_TABLES_IN_INSERT_ORDER as $table) {
                $rows = $game['tables'][$table] ?? [];
                $inserted = 0;
                if (! empty($rows)) {
                    // Insert in chunks to keep statement size sane on tables
                    // with thousands of rows (game_player_match_state,
                    // match_events).
                    foreach (array_chunk($rows, 500) as $chunk) {
                        DB::table($table)->insert($chunk);
                        $inserted += count($chunk);
                    }
                }
                if ($inserted > 0 || ($wipeCounts[$table] ?? 0) > 0) {
                    $this->lastInsertCounts[$table] = [
                        'wiped' => $wipeCounts[$table] ?? 0,
                        'inserted' => $inserted,
                    ];
                }
            }
        });
    }

    /**
     * Delete every row tied to this game id, in reverse manifest order.
     *
     * We don't lean on FK ON DELETE CASCADE because not every per-game table
     * was created with a cascading FK (e.g. `competition_entries.game_id`
     * uses the default RESTRICT). Reverse-order deletes work for every table
     * regardless of FK action and don't depend on schema details staying put.
     */
    /**
     * @return array<string, int> rows deleted per table
     */
    private function wipeGame(string $gameId): array
    {
        $deleted = [];
        $tables = array_reverse(TableManifest::TENANT_TABLES_IN_INSERT_ORDER);
        foreach ($tables as $table) {
            $column = $table === 'games' ? 'id' : 'game_id';
            $deleted[$table] = DB::table($table)->where($column, $gameId)->delete();
        }

        return $deleted;
    }

    private function upsertOnControl(string $table, string $keyColumn, array $rows): void
    {
        foreach ($rows as $row) {
            $key = $row[$keyColumn] ?? null;
            if ($key === null) {
                throw new \RuntimeException("Row in control-plane table {$table} is missing key column {$keyColumn}.");
            }

            // Local state for migration columns is authoritative — StartImport
            // has just flipped them to in_progress, and MigrationImportJob will
            // mark them completed at the end. Importing the export-side values
            // (still `pending` on beta until the seal call) would briefly bounce
            // the status back to pending and flicker the import page.
            if ($table === 'users') {
                unset($row['migration_status'], $row['migration_completed_at']);
            }

            $existing = DB::connection('pgsql_control')
                ->table($table)
                ->where($keyColumn, $key)
                ->exists();

            if ($existing) {
                // Don't overwrite an existing user row blindly — the import
                // side may already have a partial record (e.g. created during
                // the handoff step before the import job ran). Update the
                // attributes the export side ships and leave the rest alone.
                DB::connection('pgsql_control')
                    ->table($table)
                    ->where($keyColumn, $key)
                    ->update($row);
            } else {
                DB::connection('pgsql_control')->table($table)->insert($row);
            }
        }
    }
}
