<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class FetchTeamCrests extends Command
{
    protected $signature = 'app:fetch-team-crests
                            {--force : Re-download existing crests}';

    protected $description = 'Download team crests locally from Transfermarkt CDN';

    public function handle(): int
    {
        $crestsDir = public_path('crests');

        if (!is_dir($crestsDir)) {
            mkdir($crestsDir, 0755, true);
        }

        $transfermarktIds = DB::table('teams')
            ->whereNotNull('transfermarkt_id')
            ->distinct()
            ->pluck('transfermarkt_id');

        if ($transfermarktIds->isEmpty()) {
            $this->warn('No teams with transfermarkt_id found. Run app:seed-reference-data first.');

            return self::FAILURE;
        }

        $this->info("Downloading crests for {$transfermarktIds->count()} teams...");

        $bar = $this->output->createProgressBar($transfermarktIds->count());
        $bar->start();

        $downloaded = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($transfermarktIds as $id) {
            $filePath = "{$crestsDir}/{$id}.png";

            if (file_exists($filePath) && !$this->option('force')) {
                $skipped++;
                $bar->advance();

                continue;
            }

            $url = "https://tmssl.akamaized.net/images/wappen/big/{$id}.png";

            try {
                $response = Http::timeout(10)->get($url);

                if ($response->successful()) {
                    file_put_contents($filePath, $response->body());
                    $downloaded++;
                } else {
                    $failed++;
                    $this->newLine();
                    $this->warn("  HTTP {$response->status()} for ID {$id}");
                }
            } catch (\Exception $e) {
                $failed++;
                $this->newLine();
                $this->warn("  Failed ID {$id}: {$e->getMessage()}");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info('Done!');
        $this->line("  Downloaded: {$downloaded}");
        $this->line("  Skipped:    {$skipped}");
        $this->line("  Failed:     {$failed}");

        return self::SUCCESS;
    }
}
