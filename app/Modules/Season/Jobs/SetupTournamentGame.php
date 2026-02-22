<?php

namespace App\Modules\Season\Jobs;

use App\Modules\Squad\Services\InjuryService;
use App\Modules\Squad\Services\PlayerDevelopmentService;
use App\Models\CompetitionEntry;
use App\Models\CompetitionTeam;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\GameStanding;
use App\Models\Player;
use App\Models\Team;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class SetupTournamentGame implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    private const COMPETITION_ID = 'WC2026';

    public function __construct(
        public string $gameId,
        public string $teamId,
    ) {}

    public function handle(
        PlayerDevelopmentService $developmentService,
    ): void {
        $game = Game::find($this->gameId);
        if (!$game || $game->isSetupComplete()) {
            return;
        }

        // Load groups.json for fixture pairings and dates
        $groupsPath = base_path('data/2025/WC/groups.json');
        $groupsData = json_decode(file_get_contents($groupsPath), true);

        // Build team key -> Team UUID map from seeded competition_teams
        $teamKeyMap = CompetitionTeam::where('competition_id', self::COMPETITION_ID)
            ->where('season', '2025')
            ->join('teams', 'competition_teams.team_id', '=', 'teams.id')
            ->pluck('teams.id', 'teams.transfermarkt_id')
            ->toArray();

        // Step 1: Create competition entries for all WC teams
        $this->createCompetitionEntries();

        // Step 2: Create fixtures from groups.json
        $this->createFixtures($groupsData, $teamKeyMap);

        // Step 3: Create standings from DB group assignments
        $this->createGroupStandings();

        // Step 4: Create game players for all teams
        $this->createGamePlayers($developmentService);

        // Mark setup as complete
        Game::where('id', $this->gameId)->update(['setup_completed_at' => now()]);
    }

    private function createCompetitionEntries(): void
    {
        if (CompetitionEntry::where('game_id', $this->gameId)->exists()) {
            return;
        }

        $competitionTeams = CompetitionTeam::where('competition_id', self::COMPETITION_ID)
            ->where('season', '2025')
            ->get();

        $rows = $competitionTeams->map(fn ($ct) => [
            'game_id' => $this->gameId,
            'competition_id' => self::COMPETITION_ID,
            'team_id' => $ct->team_id,
            'entry_round' => 1,
        ])->toArray();

        foreach (array_chunk($rows, 100) as $chunk) {
            CompetitionEntry::insert($chunk);
        }
    }

    private function createFixtures(array $groupsData, array $teamKeyMap): void
    {
        if (GameMatch::where('game_id', $this->gameId)->exists()) {
            return;
        }

        $matchRows = [];

        foreach ($groupsData as $groupLabel => $groupInfo) {
            foreach ($groupInfo['matches'] as $match) {
                $homeTeamId = $teamKeyMap[$match['home']] ?? null;
                $awayTeamId = $teamKeyMap[$match['away']] ?? null;

                if (!$homeTeamId || !$awayTeamId) {
                    continue;
                }

                $matchRows[] = [
                    'id' => Str::uuid()->toString(),
                    'game_id' => $this->gameId,
                    'competition_id' => self::COMPETITION_ID,
                    'round_number' => $match['round'],
                    'round_name' => __('game.group_stage') . ' - ' . __('game.matchday') . ' ' . $match['round'],
                    'home_team_id' => $homeTeamId,
                    'away_team_id' => $awayTeamId,
                    'scheduled_date' => $match['date'],
                    'played' => false,
                ];
            }
        }

        foreach (array_chunk($matchRows, 100) as $chunk) {
            GameMatch::insert($chunk);
        }
    }

    private function createGroupStandings(): void
    {
        if (GameStanding::where('game_id', $this->gameId)->exists()) {
            return;
        }

        // Read group assignments from competition_teams (set by SeedWorldCupData)
        $groupedTeams = CompetitionTeam::where('competition_id', self::COMPETITION_ID)
            ->where('season', '2025')
            ->whereNotNull('group_label')
            ->orderBy('group_label')
            ->get()
            ->groupBy('group_label');

        $rows = [];
        foreach ($groupedTeams as $groupLabel => $teams) {
            $position = 1;
            foreach ($teams as $ct) {
                $rows[] = [
                    'game_id' => $this->gameId,
                    'competition_id' => self::COMPETITION_ID,
                    'group_label' => $groupLabel,
                    'team_id' => $ct->team_id,
                    'position' => $position,
                    'prev_position' => null,
                    'played' => 0,
                    'won' => 0,
                    'drawn' => 0,
                    'lost' => 0,
                    'goals_for' => 0,
                    'goals_against' => 0,
                    'points' => 0,
                ];
                $position++;
            }
        }

        foreach (array_chunk($rows, 100) as $chunk) {
            GameStanding::insert($chunk);
        }
    }

    private function createGamePlayers(PlayerDevelopmentService $developmentService): void
    {
        if (GamePlayer::where('game_id', $this->gameId)->exists()) {
            return;
        }

        // Load all team files from data directory
        $basePath = base_path('data/2025/WC/teams');
        $allPlayers = Player::all()->keyBy('transfermarkt_id');
        $teamsByKey = Team::where('type', 'national')
            ->whereNotNull('transfermarkt_id')
            ->get()
            ->keyBy('transfermarkt_id');

        $playerRows = [];

        foreach (glob("{$basePath}/*.json") as $filePath) {
            $data = json_decode(file_get_contents($filePath), true);
            if (!$data) {
                continue;
            }

            $teamKey = pathinfo($filePath, PATHINFO_FILENAME);
            $team = $teamsByKey->get($teamKey);
            if (!$team) {
                continue;
            }

            // Skip user's team — their players are created during squad selection onboarding
            if ($team->id === $this->teamId) {
                continue;
            }

            foreach ($data['players'] ?? [] as $playerData) {
                $transfermarktId = $playerData['id'] ?? null;
                if (!$transfermarktId) {
                    continue;
                }

                $player = $allPlayers->get($transfermarktId);
                if (!$player) {
                    continue;
                }

                $currentAbility = (int) round(
                    ($player->technical_ability + $player->physical_ability) / 2
                );
                $potentialData = $developmentService->generatePotential(
                    $player->age,
                    $currentAbility
                );

                $playerRows[] = [
                    'id' => Str::uuid()->toString(),
                    'game_id' => $this->gameId,
                    'player_id' => $player->id,
                    'team_id' => $team->id,
                    'number' => null,
                    'position' => $playerData['position'] ?? 'Central Midfield',
                    'market_value' => null,
                    'market_value_cents' => 0,
                    'contract_until' => null,
                    'annual_wage' => 0,
                    'fitness' => rand(90, 100),
                    'morale' => rand(70, 85),
                    'durability' => InjuryService::generateDurability(),
                    'game_technical_ability' => $player->technical_ability,
                    'game_physical_ability' => $player->physical_ability,
                    'potential' => $potentialData['potential'],
                    'potential_low' => $potentialData['low'],
                    'potential_high' => $potentialData['high'],
                    'season_appearances' => 0,
                ];
            }
        }

        foreach (array_chunk($playerRows, 100) as $chunk) {
            GamePlayer::insert($chunk);
        }
    }
}
