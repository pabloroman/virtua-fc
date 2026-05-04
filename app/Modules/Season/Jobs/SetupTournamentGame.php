<?php

namespace App\Modules\Season\Jobs;

use App\Modules\Notification\Services\NotificationService;
use App\Models\CompetitionEntry;
use App\Models\CompetitionTeam;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\GameStanding;
use App\Models\Team;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SetupTournamentGame implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    private const COMPETITION_ID = 'WC2026';

    public function __construct(
        public string $gameId,
        public string $teamId,
    ) {
        $this->onQueue('setup');
    }

    public function handle(
        NotificationService $notificationService,
    ): void {
        $game = Game::find($this->gameId);
        if (!$game || $game->isSetupComplete()) {
            return;
        }

        // Load groups.json for fixture data and group assignments (cached for 1 hour)
        $groupsData = Cache::remember('wc2026_groups', 3600, function () {
            $groupsPath = base_path('data/2025/WC2026/groups.json');
            return json_decode(file_get_contents($groupsPath), true);
        });

        // Build FIFA code → Team UUID map from the database (cached for 1 hour)
        $nationalTeams = Cache::remember('wc2026_national_teams', 3600, function () {
            return Team::worldCupEligible()->get(['id', 'fifa_code']);
        });
        $teamKeyMap = $nationalTeams->pluck('id', 'fifa_code')->toArray();

        DB::transaction(function () use ($game, $groupsData, $teamKeyMap, $nationalTeams, $notificationService) {
            // Step 1: Create competition entries for all WC teams
            $this->createCompetitionEntries();

            // Step 2: Create fixtures from groups.json
            $this->createFixtures($groupsData, $teamKeyMap);

            // Step 3: Create standings with group labels
            $this->createGroupStandings($groupsData, $teamKeyMap);

            // Step 4: Create game players from pre-computed templates
            $this->createGamePlayersFromTemplates();

            // Send welcome notification
            $teamName = $nationalTeams->firstWhere('id', $this->teamId)?->getRawOriginal('name') ?? '';
            $notificationService->notifyTournamentWelcome($game, self::COMPETITION_ID, $teamName);

            // Mark setup as complete
            Game::where('id', $this->gameId)->update(['setup_completed_at' => now()]);

            // Record activation event
            app(\App\Modules\Season\Services\ActivationTracker::class)
                ->record($game->user_id, \App\Models\ActivationEvent::EVENT_SETUP_COMPLETED, $this->gameId, \App\Models\Game::MODE_TOURNAMENT);
        });
    }

    private function createCompetitionEntries(): void
    {
        if (CompetitionEntry::where('game_id', $this->gameId)->exists()) {
            return;
        }

        $teamIds = CompetitionTeam::where('competition_id', self::COMPETITION_ID)
            ->where('season', '2025')
            ->pluck('team_id');

        $rows = $teamIds->map(fn ($teamId) => [
            'game_id' => $this->gameId,
            'competition_id' => self::COMPETITION_ID,
            'team_id' => $teamId,
            'entry_round' => 1,
        ])->toArray();

        foreach (array_chunk($rows, 500) as $chunk) {
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

        foreach (array_chunk($matchRows, 500) as $chunk) {
            GameMatch::insert($chunk);
        }
    }

    private function createGroupStandings(array $groupsData, array $teamKeyMap): void
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

        foreach (array_chunk($rows, 500) as $chunk) {
            GameStanding::insert($chunk);
        }
    }

    private const TEMPLATE_INSERT_CHUNK_SIZE = 500;

    /**
     * Copy pre-computed templates into game_players (mirrors SetupNewGame pattern).
     *
     * Cursor-streams templates from the control plane and bulk-inserts
     * game_players + game_player_match_state on the tenant plane in chunks.
     * Replaces the cross-plane INSERT…SELECT that was the last hot-path
     * PLANES-SEAM. Tournament mode loads only national-team rosters, so the
     * volume is bounded (~30 teams × ~25 players ≈ 750 rows).
     */
    private function createGamePlayersFromTemplates(): void
    {
        if (GamePlayer::where('game_id', $this->gameId)->exists()) {
            return;
        }

        $eligibleNationalTeamIds = Team::where('type', 'national')
            ->whereNotNull('fifa_code')
            ->pluck('id')
            ->all();

        if ($eligibleNationalTeamIds === []) {
            return;
        }

        $templateQuery = DB::connection('pgsql_control')
            ->table('game_player_templates')
            ->where('season', '2025')
            ->whereIn('team_id', $eligibleNationalTeamIds)
            ->where('team_id', '<>', $this->teamId);

        $playerBuffer = [];
        $matchStateBuffer = [];
        $seenPlayerIds = [];

        foreach ($templateQuery->cursor() as $template) {
            // Mirrors the ON CONFLICT (game_id, player_id) DO NOTHING the raw
            // SQL used to enforce. Same player_id can appear under multiple
            // (season, team) templates; first wins.
            if (isset($seenPlayerIds[$template->player_id])) {
                continue;
            }
            $seenPlayerIds[$template->player_id] = true;

            $gamePlayerId = (string) Str::uuid();

            $playerBuffer[] = [
                'id' => $gamePlayerId,
                'game_id' => $this->gameId,
                'player_id' => $template->player_id,
                'transfermarkt_id' => $template->transfermarkt_id,
                'name' => $template->name,
                'date_of_birth' => $template->date_of_birth,
                'nationality' => $template->nationality,
                'height' => $template->height,
                'foot' => $template->foot,
                'team_id' => $template->team_id,
                'is_reserve_squad' => false,
                'number' => null,
                'position' => $template->position,
                'market_value' => $template->market_value,
                'market_value_cents' => $template->market_value_cents,
                'contract_until' => $template->contract_until,
                'annual_wage' => $template->annual_wage,
                'durability' => $template->durability,
                'overall_score' => $template->overall_score,
                'potential' => $template->potential,
                'potential_low' => $template->potential_low,
                'potential_high' => $template->potential_high,
                'tier' => $template->tier,
            ];

            $matchStateBuffer[] = [
                'game_player_id' => $gamePlayerId,
                'game_id' => $this->gameId,
                'fitness' => $template->fitness,
                'morale' => $template->morale,
            ];

            if (count($playerBuffer) >= self::TEMPLATE_INSERT_CHUNK_SIZE) {
                $this->flushTemplateChunks($playerBuffer, $matchStateBuffer);
                $playerBuffer = [];
                $matchStateBuffer = [];
            }
        }

        if (!empty($playerBuffer)) {
            $this->flushTemplateChunks($playerBuffer, $matchStateBuffer);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $players
     * @param  list<array<string, mixed>>  $matchStates
     */
    private function flushTemplateChunks(array $players, array $matchStates): void
    {
        DB::transaction(function () use ($players, $matchStates) {
            DB::table('game_players')->insert($players);
            DB::table('game_player_match_state')->insert($matchStates);
        });
    }
}
