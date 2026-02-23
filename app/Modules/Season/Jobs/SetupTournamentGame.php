<?php

namespace App\Modules\Season\Jobs;

use App\Modules\Notification\Services\NotificationService;
use App\Modules\Squad\Services\InjuryService;
use App\Modules\Squad\Services\PlayerDevelopmentService;
use App\Modules\Competition\Services\StandingsCalculator;
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
        StandingsCalculator $standingsCalculator,
        PlayerDevelopmentService $developmentService,
        NotificationService $notificationService,
    ): void {
        $game = Game::find($this->gameId);
        if (!$game || $game->isSetupComplete()) {
            return;
        }

        // Load groups.json for fixture data and group assignments
        $groupsPath = base_path('data/2025/WC2026/groups.json');
        $groupsData = json_decode(file_get_contents($groupsPath), true);

        // Build FIFA code → Team UUID map from team_mapping.json
        $mappingPath = base_path('data/2025/WC2026/team_mapping.json');
        $mapping = json_decode(file_get_contents($mappingPath), true);
        $teamKeyMap = collect($mapping)->mapWithKeys(
            fn ($data, $fifaCode) => [$fifaCode => $data['uuid']]
        )->toArray();

        // Step 1: Create competition entries for all WC teams
        $this->createCompetitionEntries();

        // Step 2: Create fixtures from groups.json
        $this->createFixtures($groupsData, $teamKeyMap);

        // Step 3: Create standings with group labels
        $this->createGroupStandings($groupsData, $teamKeyMap, $standingsCalculator);

        // Step 4: Create game players for teams with JSON rosters
        $this->createGamePlayers($mapping, $developmentService);

        // Send welcome notification
        $teamName = Team::find($this->teamId)?->name ?? '';
        $notificationService->notifyTournamentWelcome($game, self::COMPETITION_ID, $teamName);

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

    private function createGroupStandings(array $groupsData, array $teamKeyMap, StandingsCalculator $standingsCalculator): void
    {
        if (GameStanding::where('game_id', $this->gameId)->exists()) {
            return;
        }

        $rows = [];
        foreach ($groupsData as $groupLabel => $groupInfo) {
            $position = 1;
            foreach ($groupInfo['teams'] as $teamKey) {
                $teamId = $teamKeyMap[$teamKey] ?? null;
                if (!$teamId) {
                    continue;
                }

                $rows[] = [
                    'game_id' => $this->gameId,
                    'competition_id' => self::COMPETITION_ID,
                    'group_label' => $groupLabel,
                    'team_id' => $teamId,
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

    /**
     * Create game players only for teams that have JSON roster files.
     */
    private function createGamePlayers(array $teamMapping, PlayerDevelopmentService $developmentService): void
    {
        if (GamePlayer::where('game_id', $this->gameId)->exists()) {
            return;
        }

        $basePath = base_path('data/2025/WC2026/teams');
        $allPlayers = Player::all()->keyBy('transfermarkt_id');
        $playerRows = [];

        // Only process teams that have a transfermarkt_id (i.e., have JSON roster files)
        $teamsWithRosters = collect($teamMapping)->filter(
            fn ($data) => $data['transfermarkt_id'] !== null
        );

        foreach ($teamsWithRosters as $fifaCode => $teamData) {
            $filePath = "{$basePath}/{$teamData['transfermarkt_id']}.json";
            if (!file_exists($filePath)) {
                continue;
            }

            // Skip user's team — their players are created during squad selection onboarding
            if ($teamData['uuid'] === $this->teamId) {
                continue;
            }

            $data = json_decode(file_get_contents($filePath), true);
            if (!$data) {
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
                    'team_id' => $teamData['uuid'],
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
