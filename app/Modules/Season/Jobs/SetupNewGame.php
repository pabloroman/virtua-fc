<?php

namespace App\Modules\Season\Jobs;

use App\Modules\Competition\Services\CountryConfig;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Season\Services\SeasonSetupPipeline;
use App\Modules\Season\Processors\LeagueFixtureProcessor;
use App\Modules\Season\Processors\StandingsResetProcessor;
use App\Models\ClubProfile;
use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\CompetitionTeam;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\TeamReputation;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SetupNewGame implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function uniqueId(): string
    {
        return $this->gameId;
    }

    private Carbon $currentDate;

    public function __construct(
        public string $gameId,
        public string $teamId,
        public string $competitionId,
        public string $season,
        public string $gameMode,
    ) {
        $this->onQueue('setup');
    }

    public function handle(
        SeasonSetupPipeline $setupPipeline,
        LeagueFixtureProcessor $fixtureProcessor,
        StandingsResetProcessor $standingsProcessor,
    ): void {
        // Idempotency: skip if already set up
        $game = Game::find($this->gameId);
        if (!$game || $game->isSetupComplete()) {
            return;
        }

        $this->currentDate = $game->current_date ?? Carbon::parse("{$this->season}-08-15");

        // Step 1: Copy competition team rosters into per-game table
        $this->copyCompetitionTeamsToGame();

        // Step 1b: Initialize per-game reputation records for all teams
        $this->initializeTeamReputations();

        // Step 2: Initialize game players from templates (required)
        $this->initializeGamePlayersFromTemplates();

        // Step 3: Run shared setup processors
        if ($this->gameMode === Game::MODE_CAREER) {
            // Career mode: run all 4 shared processors (fixtures, standings, budget, cups/Swiss)
            $allTeams = $this->loadTeamLookup();
            $swissPotData = $this->buildSwissPotData($allTeams);

            $data = new SeasonTransitionData(
                oldSeason: '0',
                newSeason: $this->season,
                competitionId: $this->competitionId,
                isInitialSeason: true,
                metadata: $swissPotData ? [SeasonTransitionData::META_SWISS_POT_DATA => $swissPotData] : [],
            );

            $setupPipeline->run($game->refresh(), $data);
        } else {
            // Non-career mode: only fixtures + standings (no budget/cups)
            $data = new SeasonTransitionData(
                oldSeason: '0',
                newSeason: $this->season,
                competitionId: $this->competitionId,
                isInitialSeason: true,
            );

            $fixtureProcessor->process($game, $data);
            $standingsProcessor->process($game, $data);
        }

        // Mark setup as complete
        Game::where('id', $this->gameId)->update([
            'setup_completed_at' => now(),
            'season_transition_step' => null,
            'season_transition_data' => null,
        ]);

        // Record activation event
        app(\App\Modules\Season\Services\ActivationTracker::class)
            ->record($game->user_id, \App\Models\ActivationEvent::EVENT_SETUP_COMPLETED, $this->gameId, $this->gameMode);

        // Notify the user that the summer transfer window is open
        if ($this->gameMode === Game::MODE_CAREER) {
            app(NotificationService::class)->notifyTransferWindowOpen($game->refresh(), 'summer');
        }
    }

    private function copyCompetitionTeamsToGame(): void
    {
        // Idempotency: skip if already done
        if (CompetitionEntry::where('game_id', $this->gameId)->exists()) {
            return;
        }

        $rows = CompetitionTeam::where('season', $this->season)
            ->whereNotIn('team_id', function ($query) {
                $query->select('id')->from('teams')->where('type', 'national');
            })
            ->get()
            ->map(fn ($ct) => [
                'game_id' => $this->gameId,
                'competition_id' => $ct->competition_id,
                'team_id' => $ct->team_id,
                'entry_round' => $ct->entry_round ?? 1,
            ])
            ->unique(fn ($row) => $row['competition_id'] . '|' . $row['team_id'])
            ->values()
            ->toArray();

        DB::transaction(function () use ($rows) {
            foreach (array_chunk($rows, 100) as $chunk) {
                CompetitionEntry::insert($chunk);
            }
        });
    }

    /**
     * Initialize per-game reputation records for all teams with competition entries.
     * Copies the static ClubProfile reputation as the starting point.
     * Applies a division bonus for lower-tier teams in top-division leagues.
     */
    private function initializeTeamReputations(): void
    {
        // Idempotency: skip if already done
        if (TeamReputation::where('game_id', $this->gameId)->exists()) {
            return;
        }

        $game = Game::find($this->gameId);
        $countryCode = $game->country ?? 'ES';

        // Load competition entries with their competition tier
        $entries = CompetitionEntry::where('game_id', $this->gameId)
            ->whereHas('competition', fn ($q) => $q->where('country', $countryCode))
            ->get();

        $teamIds = $entries->pluck('team_id')->unique();

        $clubProfiles = ClubProfile::whereIn('team_id', $teamIds)
            ->pluck('reputation_level', 'team_id');

        // Build a map of team_id => lowest competition tier (1 = top division)
        $competitionTiers = Competition::whereIn('id', $entries->pluck('competition_id')->unique())
            ->pluck('tier', 'id');

        $teamCompetitionTier = [];
        foreach ($entries as $entry) {
            $tier = $competitionTiers[$entry->competition_id] ?? 99;
            if (!isset($teamCompetitionTier[$entry->team_id]) || $tier < $teamCompetitionTier[$entry->team_id]) {
                $teamCompetitionTier[$entry->team_id] = $tier;
            }
        }

        $divisionBonus = (int) config('reputation.division_bonus', 25);

        $rows = [];
        foreach ($teamIds as $teamId) {
            $level = $clubProfiles[$teamId] ?? ClubProfile::REPUTATION_LOCAL;
            $points = TeamReputation::pointsForTier($level);

            // Apply division bonus for Modest/Local teams in tier 1
            $competitionTier = $teamCompetitionTier[$teamId] ?? 99;
            if ($competitionTier === 1 && in_array($level, [ClubProfile::REPUTATION_MODEST, ClubProfile::REPUTATION_LOCAL])) {
                $points += $divisionBonus;
            }

            $rows[] = [
                'id' => Str::uuid()->toString(),
                'game_id' => $this->gameId,
                'team_id' => $teamId,
                'reputation_level' => $level,
                'base_reputation_level' => $level,
                'reputation_points' => $points,
            ];
        }

        DB::transaction(function () use ($rows) {
            foreach (array_chunk($rows, 100) as $chunk) {
                TeamReputation::insert($chunk);
            }
        });
    }

    private function loadTeamLookup(): Collection
    {
        return DB::table('teams')
            ->select('id', 'transfermarkt_id')
            ->whereNotNull('transfermarkt_id')
            ->get()
            ->keyBy('transfermarkt_id');
    }

    /**
     * Build Swiss pot data from JSON for all Swiss competitions (initial season only).
     *
     * @return array<string, array<array{id: string, pot: int, country: string}>>
     */
    private function buildSwissPotData(Collection $allTeams): array
    {
        $countryConfig = app(CountryConfig::class);
        $game = Game::find($this->gameId);
        $countryCode = $game->country ?? 'ES';

        $swissIds = $countryConfig->swissFormatCompetitionIds($countryCode);
        $potData = [];

        foreach ($swissIds as $competitionId) {
            $teamsFilePath = base_path("data/{$this->season}/{$competitionId}/teams.json");
            if (!file_exists($teamsFilePath)) {
                continue;
            }

            $teamsData = json_decode(file_get_contents($teamsFilePath), true);
            $clubs = $teamsData['clubs'] ?? [];

            $drawTeams = [];
            foreach ($clubs as $club) {
                $transfermarktId = $club['id'] ?? null;
                if (!$transfermarktId) {
                    continue;
                }

                $team = $allTeams->get($transfermarktId);
                if (!$team) {
                    continue;
                }

                $drawTeams[] = [
                    'id' => $team->id,
                    'pot' => $club['pot'] ?? 4,
                    'country' => $club['country'] ?? 'XX',
                ];
            }

            if (!empty($drawTeams)) {
                $potData[$competitionId] = $drawTeams;
            }
        }

        return $potData;
    }

    /**
     * Initialize game players from pre-computed templates.
     * Templates must exist — fails if they don't.
     */
    private function initializeGamePlayersFromTemplates(): void
    {
        // Idempotency: skip if players already exist
        if (GamePlayer::where('game_id', $this->gameId)->exists()) {
            return;
        }

        $hasTemplates = DB::table('game_player_templates')
            ->where('season', $this->season)
            ->exists();

        if (!$hasTemplates) {
            throw new \RuntimeException(
                "No game_player_templates found for season {$this->season}. "
                . 'Run php artisan app:refresh-player-templates first.'
            );
        }

        $gameId = $this->gameId;

        DB::table('game_player_templates')
            ->where('season', $this->season)
            ->whereNotIn('team_id', function ($query) {
                $query->select('id')->from('teams')->where('type', 'national');
            })
            ->orderBy('player_id')
            ->chunk(200, function ($templates) use ($gameId) {
                $rows = [];

                foreach ($templates as $t) {
                    $rows[] = [
                        'id' => Str::uuid()->toString(),
                        'game_id' => $gameId,
                        'player_id' => $t->player_id,
                        'team_id' => $t->team_id,
                        'number' => $t->number,
                        'position' => $t->position,
                        'secondary_positions' => $t->secondary_positions,
                        'market_value' => $t->market_value,
                        'market_value_cents' => $t->market_value_cents,
                        'contract_until' => $t->contract_until,
                        'annual_wage' => $t->annual_wage,
                        'fitness' => $t->fitness,
                        'morale' => $t->morale,
                        'durability' => $t->durability,
                        'game_technical_ability' => $t->game_technical_ability,
                        'game_physical_ability' => $t->game_physical_ability,
                        'potential' => $t->potential,
                        'potential_low' => $t->potential_low,
                        'potential_high' => $t->potential_high,
                        'tier' => $t->tier,
                        'season_appearances' => 0,
                    ];
                }

                if (!empty($rows)) {
                    GamePlayer::insertOrIgnore($rows);
                }
            });
    }
}
