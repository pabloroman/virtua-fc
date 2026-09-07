<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Download player photos into the assets disk, keyed by Sofascore id.
 *
 * GamePlayer::getImageUrlAttribute resolves a photo to players/{sofascore_id}.webp,
 * so that is what this writes. Sofascore's image CDN serves a mix of webp, png and
 * jpeg; everything is saved as .webp regardless because the extension is cosmetic —
 * browsers render by sniffing content, and the game only ever asks for .webp.
 *
 * Sibling of app:fetch-team-crests, which does the same job for club crests.
 *
 * A 404 is an ordinary outcome, not a failure: plenty of lower-division and youth
 * players have a Sofascore entry but no photo. Those keep the default avatar.
 *
 * NOTE: unlike the *search* API (which 403s anything that isn't a sofascore.com
 * tab, hence scripts/sofascore-id-finder/ being a console script), the image CDN
 * answers ordinary server-side requests, so this needs no browser.
 */
class FetchPlayerPhotos extends Command
{
    protected $signature = 'app:fetch-player-photos
                            {--season= : Season whose players to fetch (defaults to config season.current)}
                            {--force : Re-download photos already on disk}';

    protected $description = 'Download player photos from Sofascore into the assets disk';

    /** Requests in flight at once. */
    private const CONCURRENCY = 10;

    public function handle(): int
    {
        $season = (string) ($this->option('season') ?: config('season.current'));
        $dir = public_path('players');

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $ids = DB::table('game_player_templates')
            ->where('season', $season)
            ->whereNotNull('sofascore_id')
            ->distinct()
            ->pluck('sofascore_id');

        if ($ids->isEmpty()) {
            $this->warn("No {$season} players have a Sofascore id. Run app:build-sofascore-id-map first.");

            return self::FAILURE;
        }

        $pending = $this->option('force')
            ? $ids->all()
            : $ids->reject(fn ($id) => is_file("{$dir}/{$id}.webp"))->values()->all();

        $skipped = $ids->count() - count($pending);

        if ($pending === []) {
            $this->info("All {$ids->count()} photo(s) already on disk.");

            return self::SUCCESS;
        }

        $this->info('Fetching '.count($pending).' photo(s) for season '.$season.'...');

        $bar = $this->output->createProgressBar(count($pending));
        $bar->start();

        $saved = 0;
        $noPhoto = 0;
        $failed = [];

        foreach (array_chunk($pending, self::CONCURRENCY) as $chunk) {
            $responses = Http::pool(fn (Pool $pool) => array_map(
                fn ($id) => $pool->as((string) $id)
                    ->timeout(30)
                    // throw: false — a 404 is an expected outcome here, and the
                    // default would surface it as a RequestException instead of a
                    // response we can classify.
                    ->retry(2, 500, throw: false)
                    ->get("https://img.sofascore.com/api/v1/player/{$id}/image"),
                $chunk,
            ));

            foreach ($chunk as $id) {
                $response = $responses[(string) $id] ?? null;
                $bar->advance();

                if ($response instanceof \Throwable) {
                    $failed[$id] = $response->getMessage();

                    continue;
                }
                if ($response === null) {
                    $failed[$id] = 'no response';

                    continue;
                }
                if ($response->status() === 404) {
                    $noPhoto++;

                    continue;
                }
                if (! $response->successful() || $response->body() === '') {
                    $failed[$id] = 'HTTP '.$response->status();

                    continue;
                }

                file_put_contents("{$dir}/{$id}.webp", $response->body());
                $saved++;
            }
        }

        $bar->finish();
        $this->newLine(2);

        $this->info('Done.');
        $this->line("  Saved:            {$saved}");
        $this->line("  No photo (404):   {$noPhoto}");
        $this->line("  Already on disk:  {$skipped}");
        $this->line('  Failed:           '.count($failed));

        foreach (array_slice($failed, 0, 10, true) as $id => $reason) {
            $this->warn("    {$id}: {$reason}");
        }

        return self::SUCCESS;
    }
}
