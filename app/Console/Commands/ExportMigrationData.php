<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Modules\Migration\Services\UserExporter;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * CLI shortcut around UserExporter for debugging the migration flow without
 * touching HTTP, middleware, or the queue.
 *
 *   # Manifest only (control plane + game ids):
 *   php artisan migration:export-user 42
 *
 *   # Manifest + per-game payloads, summarised:
 *   php artisan migration:export-user 42 --all
 *
 *   # Full JSON dump to disk (one file for manifest + one per game):
 *   php artisan migration:export-user 42 --all --out=/tmp/export-42
 *
 *   # Single game:
 *   php artisan migration:export-user 42 --game=01HX...uuid
 */
class ExportMigrationData extends Command
{
    protected $signature = 'migration:export-user
        {user : User id (control-plane integer id) or email}
        {--game= : Export a single game id instead of the manifest}
        {--all : After the manifest, export every game it lists}
        {--out= : Directory to write JSON files to (otherwise summary on stdout)}';

    protected $description = 'Run UserExporter against a user for debugging the migration flow.';

    public function handle(UserExporter $exporter): int
    {
        $userId = $this->resolveUserId((string) $this->argument('user'));
        if ($userId === null) {
            return self::FAILURE;
        }

        $outDir = $this->option('out') !== null ? rtrim((string) $this->option('out'), '/') : null;
        if ($outDir !== null && ! is_dir($outDir) && ! mkdir($outDir, 0o755, true) && ! is_dir($outDir)) {
            $this->error("Could not create output directory: {$outDir}");

            return self::FAILURE;
        }

        $singleGame = $this->option('game');
        if ($singleGame !== null) {
            return $this->dumpGame($exporter, $userId, (string) $singleGame, $outDir);
        }

        $manifest = $exporter->exportManifest($userId);
        $this->reportManifest($manifest);
        $this->writeJson($outDir, 'manifest.json', $manifest);

        if ($this->option('all')) {
            foreach ($manifest['game_ids'] as $gameId) {
                $this->dumpGame($exporter, $userId, (string) $gameId, $outDir);
            }
        }

        return self::SUCCESS;
    }

    private function dumpGame(UserExporter $exporter, int $userId, string $gameId, ?string $outDir): int
    {
        if (! Str::isUuid($gameId)) {
            $this->error("Not a UUID: {$gameId}");

            return self::FAILURE;
        }

        $payload = $exporter->exportGame($userId, $gameId);
        $this->reportGame($payload);
        $this->writeJson($outDir, "game-{$gameId}.json", $payload);

        return self::SUCCESS;
    }

    private function resolveUserId(string $value): ?int
    {
        if (ctype_digit($value)) {
            $user = User::find((int) $value);
        } else {
            $user = User::where('email', $value)->first();
        }

        if ($user === null) {
            $this->error("User '{$value}' not found.");

            return null;
        }

        $this->info("User: id={$user->id} email={$user->email} migration_status={$user->migration_status->value}");

        return $user->id;
    }

    /** @param array<string, mixed> $manifest */
    private function reportManifest(array $manifest): void
    {
        $this->line('');
        $this->line('=== Manifest ===');
        $this->line("format_version: {$manifest['format_version']}");
        $this->line('exported_at:    '.($manifest['exported_at'] ?? 'n/a'));
        $this->line('user_id:        '.$manifest['user_id']);
        $this->line('game_ids:       '.count($manifest['game_ids']).' game(s)');
        foreach ($manifest['game_ids'] as $id) {
            $this->line("  - {$id}");
        }
        $this->line('control_plane:');
        foreach ($manifest['control_plane'] as $table => $rows) {
            $this->line(sprintf('  %-20s %d row(s)', $table, count($rows)));
        }
    }

    /** @param array<string, mixed> $payload */
    private function reportGame(array $payload): void
    {
        $this->line('');
        $this->line("=== Game {$payload['game_id']} ===");
        $totalRows = 0;
        $emptyTables = 0;
        foreach ($payload['tables'] as $table => $rows) {
            $count = count($rows);
            $totalRows += $count;
            if ($count === 0) {
                $emptyTables++;
            }
            $this->line(sprintf('  %-32s %d row(s)', $table, $count));
        }
        $this->line(sprintf(
            '  -> %d total rows across %d tables (%d empty)',
            $totalRows,
            count($payload['tables']),
            $emptyTables,
        ));
        if ($totalRows === 0) {
            $this->warn('  No rows exported. If the game exists on this database, this is a bug.');
        }
    }

    /** @param array<string, mixed> $payload */
    private function writeJson(?string $outDir, string $filename, array $payload): void
    {
        if ($outDir === null) {
            return;
        }

        $path = $outDir.'/'.$filename;
        file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->line("  wrote {$path} (".number_format(filesize($path)).' bytes)');
    }
}
