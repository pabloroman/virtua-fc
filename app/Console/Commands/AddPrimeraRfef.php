<?php

namespace App\Console\Commands;

use Database\Seeders\ClubProfilesSeeder;
use Symfony\Component\Console\Command\Command as CommandAlias;

/**
 * One-off command to add Primera RFEF (ESP3A, ESP3B, ESP3PO) to an existing
 * database without rebuilding reference data.
 *
 * Safe to run against live databases with in-progress careers — existing
 * competitions, teams, players, and games are left untouched. The new tier 3
 * only appears in games created after this command runs; retroactively
 * injecting it into in-progress careers is out of scope.
 *
 * Extends SeedReferenceData to reuse its idempotent seeding primitives
 * (seedCompetition, seedPromotionPlayoff, linkReserveTeams) without
 * duplicating the team/player/competition_teams wiring.
 */
class AddPrimeraRfef extends SeedReferenceData
{
    protected $signature = 'app:add-primera-rfef
                            {--season=2025 : Reference season to seed against}';

    protected $description =
        'Add Primera RFEF (ESP3A, ESP3B, ESP3PO) to an existing database ' .
        'without rebuilding reference data. In-progress careers are unaffected.';

    public function handle(): int
    {
        $season = $this->option('season');

        $this->info('Adding Primera RFEF to existing database...');
        $this->newLine();

        try {
            $this->seedCompetitionsByCode('ES', ['ESP3A', 'ESP3B', 'ESP3PO']);
        } catch (\Throwable $e) {
            $this->error("FAILED seeding Primera RFEF: {$e->getMessage()}");
            $this->error("  at {$e->getFile()}:{$e->getLine()}");
            $this->newLine();
            $this->error($e->getTraceAsString());
            return CommandAlias::FAILURE;
        }

        // ClubProfilesSeeder uses firstOrCreate/updateOrCreate per club, so
        // running the full seeder only writes rows for newly introduced ESP3
        // clubs. Existing clubs are touched without semantic change.
        try {
            $this->info('Seeding club profiles for new teams...');
            $profilesSeeder = new ClubProfilesSeeder();
            $profilesSeeder->setCommand($this);
            $profilesSeeder->run();
        } catch (\Throwable $e) {
            $this->error("FAILED seeding club profiles: {$e->getMessage()}");
            $this->error("  at {$e->getFile()}:{$e->getLine()}");
            $this->newLine();
            $this->error($e->getTraceAsString());
            return CommandAlias::FAILURE;
        }

        // Refresh player templates for ES — picks up the new ESP3A/ESP3B
        // rosters without rebuilding the foreign transfer pool.
        try {
            $this->call('app:refresh-player-templates', [
                '--season'  => $season,
                '--country' => 'ES',
            ]);
        } catch (\Throwable $e) {
            $this->error("FAILED refreshing player templates: {$e->getMessage()}");
            $this->error("  at {$e->getFile()}:{$e->getLine()}");
            $this->newLine();
            $this->error($e->getTraceAsString());
            return CommandAlias::FAILURE;
        }

        $this->newLine();
        $this->info('Primera RFEF added successfully.');
        $this->line('New careers started from now on will include tier 3.');
        $this->warn('In-progress careers are unchanged — tier 3 only appears in games created after this command.');

        return CommandAlias::SUCCESS;
    }
}
