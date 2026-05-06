<?php

namespace App\Modules\Migration\Services;

use App\Modules\Migration\TableManifest;
use Illuminate\Support\Facades\DB;

/**
 * Inverse of UserExporter. Takes the JSON envelope produced on the export
 * side and inserts every row into this deployment's database.
 *
 * Per-game work runs inside a transaction on the tenant connection so a
 * partial failure on one game doesn't leave dangling orphans. Control-plane
 * writes use a separate transaction on `pgsql_control` for the same reason.
 *
 * Progress is reported via MigrationProgress so the import page can drive a
 * progress bar without the job knowing anything about HTTP.
 */
class UserImporter
{
    public function __construct(
        private readonly MigrationProgress $progress,
    ) {
    }

    public function import(int $userId, array $envelope): void
    {
        $this->progress::set($userId, 0, 'starting');

        $this->importControlPlane($userId, $envelope['control_plane'] ?? []);

        $games = $envelope['games'] ?? [];
        $total = count($games);

        if ($total === 0) {
            $this->progress::set($userId, 100, 'finalizing');

            return;
        }

        foreach ($games as $i => $game) {
            $current = $i + 1;
            // Reserve 10–95% for game imports; 0–10% was control plane,
            // 95–100% is the finalising step the caller writes on success.
            $percent = 10 + (int) round((($current - 1) / $total) * 85);
            $this->progress::set($userId, $percent, 'games', [
                'current' => $current,
                'total' => $total,
            ]);

            $this->importGame($game);
        }

        $this->progress::set($userId, 95, 'finalizing');
    }

    private function importControlPlane(int $userId, array $controlPlane): void
    {
        DB::connection('pgsql_control')->transaction(function () use ($userId, $controlPlane) {
            $this->progress::set($userId, 2, 'user');
            foreach (TableManifest::CONTROL_PLANE_TABLES as $table => $meta) {
                $rows = $controlPlane[$table] ?? [];
                if (empty($rows)) {
                    continue;
                }

                $this->upsertOnControl($table, $meta['key'], $rows);
            }
            $this->progress::set($userId, 10, 'stats');
        });
    }

    /** @param array{game_id: string, tables: array<string, list<array>>} $game */
    private function importGame(array $game): void
    {
        DB::transaction(function () use ($game) {
            foreach (TableManifest::TENANT_TABLES_IN_INSERT_ORDER as $table) {
                $rows = $game['tables'][$table] ?? [];
                if (empty($rows)) {
                    continue;
                }

                // Insert in chunks to keep statement size sane on tables with
                // thousands of rows (game_player_match_state, match_events).
                foreach (array_chunk($rows, 500) as $chunk) {
                    DB::table($table)->insert($chunk);
                }
            }
        });
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
