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

    /**
     * Copy pre-computed templates into game_players (mirrors SetupNewGame pattern).
     */
    private function createGamePlayersFromTemplates(): void
    {
        if (GamePlayer::where('game_id', $this->gameId)->exists()) {
            return;
        }

        // PLANES-SEAM: cross-plane INSERT-SELECT and JOIN. game_players is
        // tenant; game_player_templates is control. Works today because both
        // planes share one physical Postgres. This is a hot setup path; the
        // same OOM/timeout caveats documented on SetupNewGame apply, so the
        // seam is left in place and must be re-split before the planes are
        // physically separated. See CLAUDE.md → "Control plane / tenant
        // plane".
        //
        // Tournament mode (WC2026) only loads teams the user actually faces,
        // so every player here is "active" — they all need a match-state
        // satellite row from the start. Two single INSERT...SELECT statements
        // run entirely in Postgres; the match_state insert joins on player_id
        // *and* team_id so that, if the same player_id appears in multiple
        // templates for season 2025 (e.g., league + national), we copy
        // fitness/morale from the template that matches the inserted
        // game_player's team.
        //
        // Eligible national-team ids are resolved on the control plane up
        // front so the raw INSERT below at least avoids an `IN (SELECT id
        // FROM teams WHERE type = 'national' AND fifa_code IS NOT NULL)`
        // cross-plane subquery on top of the JOIN.
        $eligibleNationalTeamIds = Team::where('type', 'national')
            ->whereNotNull('fifa_code')
            ->pluck('id')
            ->all();

        if ($eligibleNationalTeamIds === []) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($eligibleNationalTeamIds), '?'));

        DB::insert(<<<SQL
            INSERT INTO game_players (
                id, game_id, player_id, team_id, number, position,
                market_value, market_value_cents, contract_until, annual_wage, durability,
                overall_score,
                potential, potential_low, potential_high, tier
            )
            SELECT
                gen_random_uuid(), ?, t.player_id, t.team_id, NULL, t.position,
                t.market_value, t.market_value_cents, t.contract_until, t.annual_wage, t.durability,
                t.overall_score,
                t.potential, t.potential_low, t.potential_high, t.tier
            FROM game_player_templates t
            WHERE t.season = '2025'
              AND t.team_id IN ($placeholders)
              AND t.team_id <> ?
            ON CONFLICT (game_id, player_id) DO NOTHING
        SQL, [$this->gameId, ...$eligibleNationalTeamIds, $this->teamId]);

        DB::insert(<<<'SQL'
            INSERT INTO game_player_match_state (game_player_id, game_id, fitness, morale)
            SELECT gp.id, gp.game_id, t.fitness, t.morale
            FROM game_players gp
            JOIN game_player_templates t
              ON t.player_id = gp.player_id
             AND t.team_id = gp.team_id
             AND t.season = '2025'
            WHERE gp.game_id = ?
            ON CONFLICT (game_player_id) DO NOTHING
        SQL, [$this->gameId]);
    }
}
