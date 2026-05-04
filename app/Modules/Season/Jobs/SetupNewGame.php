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
use App\Models\Team;
use App\Models\TeamReputation;
use App\Modules\Stadium\Services\FanLoyaltyService;
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

    /**
     * Number of prep steps that run before the shared setup pipeline.
     * Used as the pipeline step offset and by GameSetupStatus for progress totals.
     */
    public const PREP_STEPS = 3;

    /**
     * Steps run by non-career mode after the prep phase (fixtures + standings).
     * Career mode delegates to SeasonSetupPipeline instead.
     */
    public const NON_CAREER_PIPELINE_STEPS = 2;

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

        // Reset progress counter on (re-)entry so the polling UI starts at 0.
        // Stale values can be left behind by a crashed prior run; the per-step
        // idempotency checks below mean we always re-walk the prep phase.
        $this->markStep(-1);

        // Step 0: Copy competition team rosters into per-game table
        $this->copyCompetitionTeamsToGame();
        $this->markStep(0);

        // Step 1: Initialize per-game reputation records for all teams
        $this->initializeTeamReputations();
        $this->markStep(1);

        // Step 2: Initialize game players from templates (required)
        $this->initializeGamePlayersFromTemplates();
        $this->markStep(2);

        // Step 3+: Run shared setup processors
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

            // Pipeline writes season_transition_step using stepOffset + processor index,
            // so its checkpoints continue the count after the prep phase.
            $setupPipeline->run($game->refresh(), $data, stepOffset: self::PREP_STEPS);
        } else {
            // Non-career mode: only fixtures + standings (no budget/cups)
            $data = new SeasonTransitionData(
                oldSeason: '0',
                newSeason: $this->season,
                competitionId: $this->competitionId,
                isInitialSeason: true,
            );

            $fixtureProcessor->process($game, $data);
            $this->markStep(self::PREP_STEPS);
            $standingsProcessor->process($game, $data);
            $this->markStep(self::PREP_STEPS + 1);
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

    /**
     * Persist the current setup step so GameSetupStatus can report progress.
     * Pass -1 to reset the counter on (re-)entry.
     */
    private function markStep(int $step): void
    {
        Game::where('id', $this->gameId)->update([
            'season_transition_step' => $step < 0 ? null : $step,
        ]);
    }

    private function copyCompetitionTeamsToGame(): void
    {
        // Idempotency: skip if already done
        if (CompetitionEntry::where('game_id', $this->gameId)->exists()) {
            return;
        }

        // Reserve teams (e.g. Real Madrid Castilla, Barcelona Atlètic) never
        // play in domestic cups. Drop them from those competitions at the
        // point where per-game entries are first written so no downstream
        // draw/fixture step can resurface them.
        $domesticCupIds = [];
        foreach (app(CountryConfig::class)->allCountryCodes() as $countryCode) {
            foreach (app(CountryConfig::class)->domesticCupIds($countryCode) as $cupId) {
                $domesticCupIds[] = $cupId;
            }
        }

        $query = CompetitionTeam::where('season', $this->season)
            ->whereNotIn('team_id', function ($query) {
                $query->select('id')->from('teams')->where('type', 'national');
            });

        if (!empty($domesticCupIds)) {
            $query->where(function ($q) use ($domesticCupIds) {
                $q->whereNotIn('competition_id', $domesticCupIds)
                    ->orWhereNotIn('team_id', function ($inner) {
                        $inner->select('id')->from('teams')->whereNotNull('parent_team_id');
                    });
            });
        }

        $rows = $query
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

        // Resolve in-country competition ids on the control plane first, then
        // filter game-side entries by competition_id. Avoids a cross-plane
        // whereHas('competition', …) subquery.
        $countryCompetitionIds = Competition::where('country', $countryCode)->pluck('id');

        $entries = CompetitionEntry::where('game_id', $this->gameId)
            ->whereIn('competition_id', $countryCompetitionIds)
            ->get();

        $teamIds = $entries->pluck('team_id')->unique();

        $clubProfileRows = ClubProfile::whereIn('team_id', $teamIds)
            ->get(['team_id', 'reputation_level', 'fan_loyalty'])
            ->keyBy('team_id');

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
        $fanLoyaltyService = app(FanLoyaltyService::class);

        $rows = [];
        foreach ($teamIds as $teamId) {
            $profile = $clubProfileRows[$teamId] ?? null;
            $level = $profile->reputation_level ?? ClubProfile::REPUTATION_LOCAL;
            $curatedLoyalty = $profile?->fan_loyalty;
            $points = TeamReputation::pointsForTier($level);

            // Apply division bonus for Modest/Local teams in tier 1
            $competitionTier = $teamCompetitionTier[$teamId] ?? 99;
            if ($competitionTier === 1 && in_array($level, [ClubProfile::REPUTATION_MODEST, ClubProfile::REPUTATION_LOCAL])) {
                $points += $divisionBonus;
            }

            // base_loyalty captures cultural identity (never moves);
            // loyalty_points starts equal and drifts from that anchor.
            $seededLoyalty = $fanLoyaltyService->seedInitialValue(
                $curatedLoyalty !== null ? (int) $curatedLoyalty : null,
            );

            $rows[] = [
                'id' => Str::uuid()->toString(),
                'game_id' => $this->gameId,
                'team_id' => $teamId,
                'reputation_level' => $level,
                'base_reputation_level' => $level,
                'reputation_points' => $points,
                'base_loyalty' => $seededLoyalty,
                'loyalty_points' => $seededLoyalty,
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
        return Team::whereNotNull('transfermarkt_id')
            ->get(['id', 'transfermarkt_id'])
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

        // Bulk-insert directly from templates with a single round trip per table.
        // The match_state insert joins the just-inserted game_players back to
        // templates by player_id to copy fitness/morale. Every game_player gets
        // a satellite row (Pool players carry template defaults they never read
        // in practice, but the invariant "every game_player has a matchState
        // row" lets simulation code assume presence without a lazy-ensure
        // fallback at matchday time).
        //
        // National team ids are resolved on the control plane up front so the
        // raw INSERT below stays single-plane (tenant) — replaces an inline
        // `NOT IN (SELECT id FROM teams WHERE type = 'national')` that would
        // cross the plane boundary post-split.
        $nationalTeamIds = Team::where('type', 'national')->pluck('id')->all();
        $excludeNationalClause = '';
        $bindings = [$this->gameId, $this->season];
        if ($nationalTeamIds !== []) {
            $placeholders = implode(',', array_fill(0, count($nationalTeamIds), '?'));
            $excludeNationalClause = "AND t.team_id NOT IN ($placeholders)";
            array_push($bindings, ...$nationalTeamIds);
        }

        DB::insert(<<<SQL
            INSERT INTO game_players (
                id, game_id, player_id, team_id, number, position, secondary_positions,
                market_value, market_value_cents, contract_until, annual_wage, durability,
                overall_score,
                potential, potential_low, potential_high, tier
            )
            SELECT
                gen_random_uuid(), ?, t.player_id, t.team_id, t.number, t.position, t.secondary_positions,
                t.market_value, t.market_value_cents, t.contract_until, t.annual_wage, t.durability,
                t.overall_score,
                t.potential, t.potential_low, t.potential_high, t.tier
            FROM game_player_templates t
            WHERE t.season = ?
              {$excludeNationalClause}
            ON CONFLICT (game_id, player_id) DO NOTHING
        SQL, $bindings);

        // Join on team_id too: the same player_id can appear in templates
        // for multiple teams in the same season (e.g. league + national),
        // and we want fitness/morale from the template that matches the
        // game_player's actual team.
        DB::insert(<<<'SQL'
            INSERT INTO game_player_match_state (game_player_id, game_id, fitness, morale)
            SELECT gp.id, gp.game_id, t.fitness, t.morale
            FROM game_players gp
            JOIN game_player_templates t
              ON t.player_id = gp.player_id
             AND t.team_id = gp.team_id
             AND t.season = ?
            WHERE gp.game_id = ?
            ON CONFLICT (game_player_id) DO NOTHING
        SQL, [$this->season, $this->gameId]);
    }
}
