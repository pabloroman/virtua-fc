<?php

namespace App\Modules\Season\Services;

use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Support\Str;
use App\Modules\Competition\Services\CupDrawService;
use App\Modules\Competition\Services\CupEntryRoundService;
use App\Modules\Competition\Services\LeagueFixtureGenerator;
use App\Modules\Competition\Services\StandingsCalculator;
use App\Modules\Competition\Services\CountryConfig;
use App\Modules\Competition\Services\SwissDrawService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Shared season initialization operations used by both initial game setup
 * (SetupNewGame) and subsequent season transitions (ContinentalAndCupInitProcessor).
 *
 * This service has NO idempotency checks — callers are responsible for ensuring
 * operations are not run twice.
 */
class SeasonInitializationService
{
    public function __construct(
        private LeagueFixtureGenerator $leagueFixtureGenerator,
        private SwissDrawService $swissDrawService,
        private StandingsCalculator $standingsCalculator,
        private CupDrawService $cupDrawService,
        private CountryConfig $countryConfig,
        private CupEntryRoundService $cupEntryRoundService,
    ) {}

    /** @var array<string, ?Competition> */
    private array $competitionCache = [];

    /** @var array<string, string> */
    private array $baseSeasonCache = [];

    /**
     * The reference-data season a game was created from — the `data/{season}/`
     * folder its schedules come from and the origin its fixture dates are
     * offset against. Read from the game rather than Competition::season, which
     * is shared by every save and moves when reference data is refreshed.
     *
     * Throws rather than defaulting: generating a season's fixtures against a
     * guessed calendar is worse than not generating them.
     */
    private function baseSeasonFor(string $gameId): string
    {
        if (! array_key_exists($gameId, $this->baseSeasonCache)) {
            $this->baseSeasonCache[$gameId] = Game::where('id', $gameId)->value('base_season')
                ?? throw new \RuntimeException("Cannot resolve a base season: game {$gameId} does not exist");
        }

        return $this->baseSeasonCache[$gameId];
    }

    private function findCompetition(string $competitionId): ?Competition
    {
        if (! array_key_exists($competitionId, $this->competitionCache)) {
            $this->competitionCache[$competitionId] = Competition::find($competitionId);
        }

        return $this->competitionCache[$competitionId];
    }

    /**
     * Generate league fixtures from schedule.json, adjusted for season year.
     */
    public function generateLeagueFixtures(string $gameId, string $competitionId, string $season): void
    {
        $competition = $this->findCompetition($competitionId);
        if (!$competition || !$competition->isLeague()) {
            return;
        }

        $baseSeason = $this->baseSeasonFor($gameId);
        $matchdays = LeagueFixtureGenerator::loadMatchdays($competitionId, $baseSeason);

        $yearDiff = (int) $season - (int) $baseSeason;
        if ($yearDiff !== 0) {
            $matchdays = LeagueFixtureGenerator::adjustMatchdayYears($matchdays, $yearDiff);
        }

        $teamIds = CompetitionEntry::where('game_id', $gameId)
            ->where('competition_id', $competitionId)
            ->pluck('team_id')
            ->toArray();

        if (empty($teamIds)) {
            return;
        }

        $teamCount = count($teamIds);
        if ($teamCount % 2 !== 0) {
            throw new \RuntimeException(
                "Cannot generate fixtures for {$competitionId}: odd team count ({$teamCount}). " .
                'This likely indicates a promotion/relegation imbalance in the season transition.'
            );
        }

        $fixtures = $this->leagueFixtureGenerator->generate($teamIds, $matchdays);

        $this->insertFixtures($gameId, $competitionId, $fixtures);
    }

    /**
     * Initialize a Swiss format competition (fixtures + standings).
     * Only initializes if the given team participates.
     *
     * @param array|null $teamsWithPots [{id, pot, country}, ...] — null = auto-assign pots by market value
     */
    public function initializeSwissCompetition(
        string $gameId,
        string $teamId,
        string $competitionId,
        string $season,
        ?array $teamsWithPots = null,
    ): void {
        $participates = CompetitionEntry::where('game_id', $gameId)
            ->where('competition_id', $competitionId)
            ->where('team_id', $teamId)
            ->exists();

        if (!$participates) {
            Log::info("[SeasonInit] {$teamId} does not participate in {$competitionId}, skipping Swiss init");

            return;
        }

        $competition = $this->findCompetition($competitionId);
        if (!$competition) {
            Log::warning("[SeasonInit] Competition {$competitionId} not found, skipping Swiss init");

            return;
        }

        // CountryConfig::swissFormatCompetitionIds() folds in every continental
        // competition, so UEFASUP — a two-club knockout — reaches this method
        // whenever the user manages one of its two participants. It has no
        // league phase to draw; its tie is created by conductCupDraws().
        if ($competition->handler_type !== 'swiss_format') {
            return;
        }

        // Build draw teams — from explicit data or auto-assign pots by market value
        if ($teamsWithPots !== null) {
            $drawTeams = $teamsWithPots;
        } else {
            $drawTeams = $this->buildDrawTeamsFromGameState($gameId, $competitionId);
        }

        // Deliberately a skip rather than a throw: GameSetupStatus re-dispatches
        // SetupNewGame while setup is incomplete, so throwing here would spin.
        // Logged at error level because the result — a European competition with
        // entries but no fixtures — is invisible in-game and always a data fault.
        if (count($drawTeams) < SwissDrawService::LEAGUE_PHASE_TEAMS) {
            Log::error("[SeasonInit] {$competitionId}: only " . count($drawTeams)
                . ' of ' . SwissDrawService::LEAGUE_PHASE_TEAMS
                . ' draw teams, skipping — the competition will have no fixtures');

            return;
        }

        // Load schedule and adjust dates for the season
        $baseSeason = $this->baseSeasonFor($gameId);
        $schedulePath = base_path("data/{$baseSeason}/{$competitionId}/schedule.json");
        if (!file_exists($schedulePath)) {
            Log::warning("[SeasonInit] Schedule missing: {$schedulePath}");

            return;
        }

        $scheduleData = json_decode(file_get_contents($schedulePath), true);
        $matchdayDates = [];
        foreach ($scheduleData['league'] as $md) {
            $matchdayDates[$md['round']] = $md['date'];
        }

        // Adjust dates for season year difference
        $yearDiff = (int) $season - (int) $baseSeason;
        if ($yearDiff !== 0) {
            foreach ($matchdayDates as $round => $date) {
                $matchdayDates[$round] = Carbon::parse($date)->addYears($yearDiff)->format('Y-m-d');
            }
        }

        $fixtures = $this->swissDrawService->generateFixtures($drawTeams, $matchdayDates);

        $this->insertFixtures($gameId, $competitionId, $fixtures);

        Log::info("[SeasonInit] {$competitionId} initialized: " . count($fixtures) . " fixtures for season {$season}");

        // Initialize standings
        $teamIds = CompetitionEntry::where('game_id', $gameId)
            ->where('competition_id', $competitionId)
            ->pluck('team_id')
            ->toArray();

        $this->standingsCalculator->initializeStandings($gameId, $competitionId, $teamIds);
    }

    /**
     * Conduct cup draws for every knockout cup this game participates in
     * — the country's domestic cups and supercup, plus continental
     * knockouts (UEFASUP). Entry rounds are settled first — the round each
     * club's data file declared, then the supercup skip-ahead — so round 1
     * is drawn from the right field. Parity is not adjusted here; it comes
     * from the cup's own size (see cup_qualification.target_size).
     */
    public function conductCupDraws(string $gameId, string $countryCode): void
    {
        $this->cupEntryRoundService->assignEntryRounds($gameId, $countryCode);

        $cupIds = array_merge(
            $this->countryConfig->domesticCupIds($countryCode),
            Competition::query()
                ->where('handler_type', 'knockout_cup')
                ->where('scope', Competition::SCOPE_CONTINENTAL)
                ->pluck('id')
                ->all(),
        );

        $userTeamId = Game::where('id', $gameId)->value('team_id');

        foreach ($cupIds as $cupId) {
            // Mirror initializeSwissCompetition: skip cups the user's team
            // isn't in. No entries → no draw → no orphaned background work.
            // Downstream reporting (season summary, trophies) already handles
            // the "did not compete" case.
            $userParticipates = CompetitionEntry::where('game_id', $gameId)
                ->where('competition_id', $cupId)
                ->where('team_id', $userTeamId)
                ->exists();

            if (!$userParticipates) {
                continue;
            }

            if ($this->cupDrawService->needsDrawForRound($gameId, $cupId, 1)) {
                $this->cupDrawService->conductDraw($gameId, $cupId, 1);
            }
        }
    }

    /**
     * Build draw teams from game state (for subsequent seasons without JSON data).
     * Auto-assigns pots by average squad market value.
     *
     * @return array<array{id: string, pot: int, country: string}>
     */
    private function buildDrawTeamsFromGameState(string $gameId, string $competitionId): array
    {
        $entries = CompetitionEntry::where('game_id', $gameId)
            ->where('competition_id', $competitionId)
            ->pluck('team_id')
            ->toArray();

        if (empty($entries)) {
            return [];
        }

        // Calculate average market value per team (single query)
        $avgValues = GamePlayer::where('game_id', $gameId)
            ->whereIn('team_id', $entries)
            ->groupBy('team_id')
            ->selectRaw('team_id, AVG(market_value_cents) as avg_value')
            ->pluck('avg_value', 'team_id')
            ->toArray();

        $teamValues = [];
        foreach ($entries as $teamId) {
            $teamValues[] = [
                'team_id' => $teamId,
                'avg_value' => (float) ($avgValues[$teamId] ?? 0),
            ];
        }

        // Sort by value descending
        usort($teamValues, fn ($a, $b) => $b['avg_value'] <=> $a['avg_value']);

        // Get countries for all teams in one query
        $teamCountries = Team::whereIn('id', $entries)->pluck('country', 'id')->toArray();

        // Assign pots: top 9 → Pot 1, next 9 → Pot 2, etc.
        $drawTeams = [];
        foreach ($teamValues as $i => $tv) {
            $pot = (int) floor($i / 9) + 1;
            if ($pot > 4) {
                $pot = 4;
            }

            $drawTeams[] = [
                'id' => $tv['team_id'],
                'pot' => $pot,
                'country' => $teamCountries[$tv['team_id']] ?? 'XX',
            ];
        }

        return $drawTeams;
    }

    /**
     * Insert fixture rows into game_matches in chunks.
     */
    private function insertFixtures(string $gameId, string $competitionId, array $fixtures): void
    {
        $rows = [];
        foreach ($fixtures as $fixture) {
            $rows[] = [
                'id' => Str::uuid()->toString(),
                'game_id' => $gameId,
                'competition_id' => $competitionId,
                'round_number' => $fixture['matchday'],
                'home_team_id' => $fixture['homeTeamId'],
                'away_team_id' => $fixture['awayTeamId'],
                // Postgres accepts 'YYYY-MM-DD' directly; skip Carbon::parse to
                // avoid hundreds of object allocations on the hot path.
                'scheduled_date' => $fixture['date'],
                'home_score' => null,
                'away_score' => null,
                'played' => false,
            ];
        }

        // Single bulk INSERT via the query builder — bypasses Eloquent's
        // per-row mutator pipeline. 380 rows × 10 short columns is well
        // under Postgres's parameter limit.
        DB::table('game_matches')->insert($rows);
    }
}
