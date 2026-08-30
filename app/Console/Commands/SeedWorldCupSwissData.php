<?php

namespace App\Console\Commands;

use App\Models\Team;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Command\Command as CommandAlias;

/**
 * Seeds the "World Cup — Swiss Format" competition (WCSWISS): a Champions-
 * League-style Swiss league phase (48 teams, 4 pots of 12, top 24 advance)
 * contested by the same national teams seeded for the World Cup.
 *
 * This command is intentionally thin — it reuses the national teams and player
 * templates already created by app:seed-world-cup-data. It only inserts the
 * WCSWISS competition row and its competition_teams membership, mapping the
 * FIFA codes in data/2025/WCSWISS/teams.json to the existing national Team rows.
 *
 * Run order: app:seed-reference-data → app:seed-world-cup-data → this command.
 */
class SeedWorldCupSwissData extends Command
{
    protected $signature = 'app:seed-world-cup-swiss-data
                            {--fresh : Clear existing WCSWISS competition data before seeding}';

    protected $description = 'Seed the Swiss-format World Cup competition (WCSWISS) over the existing national teams';

    private const COMPETITION_ID = 'WCSWISS';
    private const SEASON = '2025';

    public function handle(): int
    {
        if ($this->option('fresh')) {
            $this->clearExistingData();
        }

        $teamsData = $this->loadTeamsJson();
        if ($teamsData === null) {
            return CommandAlias::FAILURE;
        }

        $fifaToUuid = Team::worldCupEligible()->pluck('id', 'fifa_code')->toArray();

        $missing = collect($teamsData)
            ->pluck('id')
            ->reject(fn ($fifaCode) => isset($fifaToUuid[$fifaCode]));

        if ($missing->isNotEmpty()) {
            $this->error('Missing national teams for FIFA codes: ' . $missing->implode(', '));
            $this->line('Run app:seed-world-cup-data first to seed the national teams.');
            return CommandAlias::FAILURE;
        }

        $this->seedCompetition();
        $this->seedCompetitionTeams($teamsData, $fifaToUuid);

        $this->info('Seeded WCSWISS with ' . count($teamsData) . ' national teams.');

        return CommandAlias::SUCCESS;
    }

    private function clearExistingData(): void
    {
        $this->info('Clearing existing WCSWISS data...');

        $matchIds = DB::table('game_matches')->where('competition_id', self::COMPETITION_ID)->pluck('id');
        if ($matchIds->isNotEmpty()) {
            DB::table('match_events')->whereIn('game_match_id', $matchIds)->delete();
        }

        DB::table('game_matches')->where('competition_id', self::COMPETITION_ID)->delete();
        DB::table('cup_ties')->where('competition_id', self::COMPETITION_ID)->delete();
        DB::table('game_standings')->where('competition_id', self::COMPETITION_ID)->delete();
        DB::table('competition_entries')->where('competition_id', self::COMPETITION_ID)->delete();
        DB::table('competition_teams')->where('competition_id', self::COMPETITION_ID)->delete();
        DB::table('games')->where('competition_id', self::COMPETITION_ID)->delete();
        DB::table('competitions')->where('id', self::COMPETITION_ID)->delete();

        $this->info('Cleared.');
    }

    /**
     * @return array<int, array{id: string, pot: int, country: string}>|null
     */
    private function loadTeamsJson(): ?array
    {
        $path = base_path('data/' . self::SEASON . '/' . self::COMPETITION_ID . '/teams.json');

        if (!file_exists($path)) {
            $this->error("Teams file not found: {$path}");
            return null;
        }

        $data = json_decode(file_get_contents($path), true);
        $clubs = $data['clubs'] ?? null;

        if (!is_array($clubs) || $clubs === []) {
            $this->error('teams.json has no clubs array.');
            return null;
        }

        return $clubs;
    }

    private function seedCompetition(): void
    {
        DB::table('competitions')->updateOrInsert(
            ['id' => self::COMPETITION_ID],
            [
                'name' => 'game.wcswiss_name',
                'country' => 'XX',
                'tier' => 0,
                'type' => 'league',
                'role' => 'league',
                'scope' => 'continental',
                'handler_type' => 'swiss_format',
                'season' => self::SEASON,
            ]
        );

        $this->info('Competition: World Cup — Swiss Format');
    }

    /**
     * @param  array<int, array{id: string, pot: int, country: string}>  $teamsData
     * @param  array<string, string>  $fifaToUuid
     */
    private function seedCompetitionTeams(array $teamsData, array $fifaToUuid): void
    {
        $rows = [];
        foreach ($teamsData as $entry) {
            $rows[] = [
                'competition_id' => self::COMPETITION_ID,
                'team_id' => $fifaToUuid[$entry['id']],
                'season' => self::SEASON,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('competition_teams')->insertOrIgnore($chunk);
        }
    }
}
