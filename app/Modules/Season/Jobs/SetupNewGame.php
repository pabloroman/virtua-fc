<?php

namespace App\Modules\Season\Jobs;

use App\Events\SeasonStarted;
use App\Modules\Competition\Services\CountryConfig;
use App\Modules\Lineup\Enums\Formation;
use App\Modules\Lineup\Services\FormationBiasResolver;
use App\Modules\Lineup\Services\FormationRecommender;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Season\Services\SeasonSetupPipeline;
use App\Modules\Finance\Services\SquadWageBudgetService;
use App\Modules\Season\Services\TeamReputationSeeder;
use App\Modules\Season\Processors\LeagueFixtureProcessor;
use App\Modules\Season\Processors\StandingsResetProcessor;
use App\Models\CompetitionEntry;
use App\Models\CompetitionTeam;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\GameTactics;
use App\Models\Loan;
use App\Models\Team;
use App\Models\TeamReputation;
use App\Models\UserSquadCareerRecord;
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
    public const PREP_STEPS = 4;

    /**
     * Steps run by non-career mode after the prep phase (fixtures + standings).
     * Career mode delegates to SeasonSetupPipeline instead.
     */
    public const NON_CAREER_PIPELINE_STEPS = 2;

    public function uniqueId(): string
    {
        return $this->gameId;
    }

    public function tags(): array
    {
        return ['game:' . $this->gameId];
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
        FormationRecommender $formationRecommender,
        FormationBiasResolver $formationBiasResolver,
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
        $this->initializeUserSquadCareerRecords($game);
        $this->initializeLoansFromTemplates($game);
        $this->markStep(2);

        // Step 3: Pick a default formation that fits the user's squad. Runs
        // after templates are materialised so the recommender can score every
        // shape against real players. Without this, every game would start on
        // the hardcoded 4-3-3 from GameCreationService and squads built around
        // 4-4-2 / 4-2-3-1 would land their first /lineup view with several
        // players in fallback slots.
        $this->setUserTeamDefaultFormation($formationRecommender, $formationBiasResolver);
        $this->markStep(3);

        // Step 4+: Run shared setup processors
        if (in_array($this->gameMode, [Game::MODE_CAREER, Game::MODE_CAREER_PRO], true)) {
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

            // Assign every club's squad wage bill from its own revenue (wage =
            // weight × clubWageLevel) before the budget projection runs, so
            // surpluses and transfer budgets come out realistic. This is the
            // first point at which a club's revenue — and thus the bill it can
            // carry — is known. Idempotent — re-running on a crash-recovery pass
            // converges (the bill is already ≈ target).
            app(SquadWageBudgetService::class)->assignWageBudget($game->refresh());

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
        if (in_array($this->gameMode, [Game::MODE_CAREER, Game::MODE_CAREER_PRO], true)) {
            app(NotificationService::class)->notifyTransferWindowOpen($game->refresh(), 'summer');

            // A season starts here for new games (season transitions fire this
            // from ProcessSeasonTransition). The default investment was applied
            // during the setup pipeline, so listeners that read it (academy
            // intake) see a populated row. Fired after setup_completed_at, so a
            // crash-recovery re-run short-circuits at the isSetupComplete() guard
            // and never double-fires.
            event(new SeasonStarted($game));
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
     * Initialize per-game reputation records for all teams with competition
     * entries in the user's country. Delegates to TeamReputationSeeder so
     * the same logic can be reused mid-game when a pro manager moves to a
     * previously-unseeded country.
     */
    private function initializeTeamReputations(): void
    {
        $game = Game::find($this->gameId);
        $countryCode = $game->country ?? 'ES';

        app(TeamReputationSeeder::class)->seedForCountry($this->gameId, $countryCode);
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

        // The match_state insert joins the just-inserted game_players back to
        // templates by player_id to copy fitness/morale. Every game_player
        // gets a satellite row (Pool players carry template defaults they
        // never read in practice, but the invariant "every game_player has a
        // matchState row" lets simulation code assume presence without a
        // lazy-ensure fallback at matchday time).
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
                id, game_id, player_id,
                transfermarkt_id, name, date_of_birth, nationality, height, foot,
                team_id, number, position, secondary_positions,
                market_value, market_value_cents, contract_until, annual_wage, release_clause, durability,
                overall_score,
                potential, potential_low, potential_high, tier
            )
            SELECT
                gen_random_uuid(), ?, t.player_id,
                t.transfermarkt_id, t.name, t.date_of_birth, t.nationality, t.height, t.foot,
                t.team_id, t.number, t.position, t.secondary_positions,
                t.market_value, t.market_value_cents, t.contract_until, t.annual_wage, t.release_clause, t.durability,
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

    /**
     * Seed UserSquadCareerRecord rows for the initial squad of every team the
     * user manages (first team plus, for parent clubs, the reserve team).
     *
     * Without this, starting players have no career record, so the
     * season-close snapshot processor (which only updates existing records)
     * never appends their per-season stats and the trajectory table on the
     * player detail page stays empty for them — only transfer-acquired
     * players (whose records are created by TransferCompletionService) ever
     * accumulate history.
     *
     * `joined_from` is left NULL: starting players didn't come from anywhere
     * in the game's universe, and the origin badge/label gracefully renders
     * nothing for the NULL case.
     */
    private function initializeUserSquadCareerRecords(Game $game): void
    {
        $userTeamIds = $game->userTeamIds();
        if ($userTeamIds === []) {
            return;
        }

        // Idempotency: skip if already done.
        if (UserSquadCareerRecord::where('game_id', $this->gameId)->exists()) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($userTeamIds), '?'));

        DB::insert(<<<SQL
            INSERT INTO user_squad_career_records (
                id, game_player_id, game_id, team_id, joined_season, joined_from, season_stats
            )
            SELECT
                gen_random_uuid(), gp.id, gp.game_id, gp.team_id, ?, NULL, '{}'::jsonb
            FROM game_players gp
            WHERE gp.game_id = ?
              AND gp.team_id IN ($placeholders)
            ON CONFLICT (game_player_id) DO NOTHING
        SQL, [(int) $this->season, $this->gameId, ...$userTeamIds]);
    }

    /**
     * Materialise per-game loan records for players the source data marks as
     * on loan. A loaned player is listed in the BORROWING club's squad (so his
     * game_player team_id is that club), while the template carries the OWNING
     * club's Transfermarkt id (loan.from). We create a season-long loan that
     * LoanReturnProcessor closes at season end: the player returns to the
     * owning club if it fields a squad in this game, otherwise the loan has no
     * parent and returnLoan() frees him (team_id = null → free agent).
     */
    private function initializeLoansFromTemplates(Game $game): void
    {
        // Idempotency: setup is a re-entrant unique job, so skip if loans for
        // this game already exist.
        if (Loan::where('game_id', $this->gameId)->exists()) {
            return;
        }

        $loanedTemplates = DB::table('game_player_templates')
            ->where('season', $this->season)
            ->whereNotNull('loan_from_transfermarkt_id')
            ->get(['player_id', 'team_id', 'loan_from_transfermarkt_id']);

        if ($loanedTemplates->isEmpty()) {
            return;
        }

        // Transfermarkt id → team uuid, so loan.from resolves to an owner.
        $teamIdByTransfermarktId = Team::whereNotNull('transfermarkt_id')
            ->pluck('id', 'transfermarkt_id')
            ->toArray();

        // Teams that actually field a squad in this game. An owner outside this
        // set (foreign/unknown club, or one excluded from the game) yields a
        // null parent → the player becomes a free agent when the loan ends.
        $participatingTeamIds = DB::table('game_players')
            ->where('game_id', $this->gameId)
            ->distinct()
            ->pluck('team_id')
            ->flip()
            ->toArray();

        // game_player id keyed by "player_id|team_id" for this game. The loaned
        // player is listed once (at the borrowing club), so the pair is unique.
        $gamePlayersByKey = DB::table('game_players')
            ->where('game_id', $this->gameId)
            ->whereIn('player_id', $loanedTemplates->pluck('player_id'))
            ->get(['id', 'player_id', 'team_id'])
            ->keyBy(fn ($row) => $row->player_id . '|' . $row->team_id);

        $startedAt = $this->currentDate->toDateString();
        $returnAt = $game->getSeasonEndDateFor($this->currentDate)->toDateString();

        $rows = [];
        foreach ($loanedTemplates as $template) {
            $gamePlayer = $gamePlayersByKey->get($template->player_id . '|' . $template->team_id);
            if (! $gamePlayer) {
                // Borrowing club isn't materialised in this game — nothing to loan.
                continue;
            }

            $ownerTeamId = $teamIdByTransfermarktId[$template->loan_from_transfermarkt_id] ?? null;
            $ownerInGame = $ownerTeamId !== null && isset($participatingTeamIds[$ownerTeamId]);

            $rows[] = [
                'id' => Str::uuid()->toString(),
                'game_id' => $this->gameId,
                'game_player_id' => $gamePlayer->id,
                'parent_team_id' => $ownerInGame ? $ownerTeamId : null,
                'loan_team_id' => $template->team_id,
                'started_at' => $startedAt,
                'return_at' => $returnAt,
                'status' => Loan::STATUS_ACTIVE,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('loans')->insert($chunk);
        }
    }

    /**
     * Pick the default formation that best fits the user's squad and persist
     * it on GameTactics. Combines the squad-based mechanical score from
     * FormationRecommender with the team's tactical-identity bias (curated
     * preferred_formation, with a reputation-tier fallback pool).
     *
     * Idempotent: re-running on a game that already has a non-default
     * formation persisted leaves it untouched, so the user's later edits
     * survive a setup re-dispatch.
     */
    private function setUserTeamDefaultFormation(
        FormationRecommender $formationRecommender,
        FormationBiasResolver $formationBiasResolver,
    ): void {
        $tactics = GameTactics::where('game_id', $this->gameId)->first();
        if ($tactics === null) {
            return;
        }

        // Only overwrite the placeholder set by GameCreationService. If the
        // user already saved a different shape (e.g. a setup retry after they
        // touched /lineup), respect their choice.
        $placeholder = Formation::F_4_3_3->value;
        if ($tactics->default_formation !== $placeholder) {
            return;
        }

        $players = GamePlayer::with('matchState')
            ->where('game_id', $this->gameId)
            ->where('team_id', $this->teamId)
            ->get();

        if ($players->isEmpty()) {
            return;
        }

        $bias = $formationBiasResolver->resolveForTeam($this->gameId, $this->teamId);
        $formation = $formationRecommender->getBestFormation($players, $bias);

        $tactics->update(['default_formation' => $formation->value]);
    }
}
