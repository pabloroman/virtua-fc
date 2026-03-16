<?php

namespace App\Console\Commands;

use App\Models\SeasonArchive;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MigrateArchivesToStorage extends Command
{
    protected $signature = 'app:migrate-archives-to-storage';

    protected $description = 'Migrate existing season archives from database columns to object storage';

    public function handle(): int
    {
        $query = SeasonArchive::whereNull('storage_path');
        $total = $query->count();

        if ($total === 0) {
            $this->info('No archives to migrate — all records already use object storage.');

            return self::SUCCESS;
        }

        $this->info("Migrating {$total} archive(s) to object storage...");

        $migrated = 0;
        $failed = 0;

        $query->chunk(50, function ($archives) use (&$migrated, &$failed) {
            foreach ($archives as $archive) {
                try {
                    $this->migrateArchive($archive);
                    $migrated++;
                } catch (\Throwable $e) {
                    $failed++;
                    $this->error("Failed to migrate archive {$archive->id} (game={$archive->game_id}, season={$archive->season}): {$e->getMessage()}");
                }
            }
        });

        $this->info("Done. Migrated: {$migrated}, Failed: {$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function migrateArchive(SeasonArchive $archive): void
    {
        // Build archive data from legacy DB columns
        $archiveData = [
            'final_standings' => json_decode($archive->getAttributes()['final_standings'] ?? '[]', true) ?? [],
            'player_season_stats' => json_decode($archive->getAttributes()['player_season_stats'] ?? '[]', true) ?? [],
            'season_awards' => json_decode($archive->getAttributes()['season_awards'] ?? '[]', true) ?? [],
            'match_results' => json_decode($archive->getAttributes()['match_results'] ?? '[]', true) ?? [],
            'transfer_activity' => json_decode($archive->getAttributes()['transfer_activity'] ?? '[]', true) ?? [],
            'match_events' => $this->decompressLegacyEvents($archive->getAttributes()['match_events_archive'] ?? null),
        ];

        $path = "{$archive->game_id}/{$archive->season}.json.gz";
        $compressed = gzcompress(json_encode($archiveData), 9);

        Storage::disk('season-archives')->put($path, $compressed);

        // Update the record: set storage_path and clear blob columns
        $archive->update([
            'storage_path' => $path,
            'final_standings' => null,
            'player_season_stats' => null,
            'season_awards' => null,
            'match_results' => null,
            'transfer_activity' => null,
            'match_events_archive' => null,
        ]);

        $this->line("  Migrated: game={$archive->game_id}, season={$archive->season}");
    }

    private function decompressLegacyEvents(?string $blob): array
    {
        if (empty($blob)) {
            return [];
        }

        $decoded = @base64_decode($blob, true);
        if ($decoded === false) {
            return [];
        }

        $decompressed = @gzuncompress($decoded);
        if ($decompressed === false) {
            return [];
        }

        return json_decode($decompressed, true) ?? [];
    }
}
