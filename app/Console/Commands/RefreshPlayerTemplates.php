<?php

namespace App\Console\Commands;

use App\Modules\Competition\Services\CountryConfig;
use App\Modules\Season\Services\GamePlayerTemplateService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RefreshPlayerTemplates extends Command
{
    protected $signature = 'app:refresh-player-templates
                            {--country= : Refresh only a specific country (e.g., ES)}
                            {--season=2025 : Season to refresh}';

    protected $description = 'Regenerate game player templates from JSON data (safe for production)';

    public function handle(GamePlayerTemplateService $templateService, CountryConfig $countryConfig): int
    {
        $season = $this->option('season');
        $countryFilter = $this->option('country');

        $countryCodes = $countryConfig->playableCountryCodes();

        if ($countryFilter) {
            $countryFilter = strtoupper($countryFilter);
            if (!in_array($countryFilter, $countryCodes)) {
                $this->error("Country '{$countryFilter}' is not a playable country.");
                return self::FAILURE;
            }
            $countryCodes = [$countryFilter];
        }

        $this->info("Refreshing player templates for season {$season}...");

        DB::transaction(function () use ($templateService, $season, $countryCodes, $countryFilter) {
            if ($countryFilter) {
                $templateService->clearTemplatesForCountry($season, $countryFilter);
            } else {
                $templateService->clearTemplates($season);
            }

            $totalCount = 0;

            foreach ($countryCodes as $countryCode) {
                $count = $templateService->generateTemplates($season, $countryCode);
                $this->line("  {$countryCode}: {$count} player templates");
                $totalCount += $count;
            }

            $this->newLine();
            $this->info("Total templates: {$totalCount}");
        });

        return self::SUCCESS;
    }
}
