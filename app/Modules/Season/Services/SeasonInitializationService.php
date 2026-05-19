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
    ) {}

    /** @var array<string, ?Competition> */
    private array $competitionCache = [];

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

        $baseSeason = $competition->season;
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

        // Build draw teams — from explicit data or auto-assign pots by market value
        if ($teamsWithPots !== null) {
            $drawTeams = $teamsWithPots;
        } else {
            $drawTeams = $this->buildDrawTeamsFromGameState($gameId, $competitionId);
        }

        if (count($drawTeams) < 36) {
            Log::info("[SeasonInit] {$competitionId}: only " . count($drawTeams) . ' draw teams (need 36), skipping');

            return;
        }

        // Load schedule and adjust dates for the season
        $baseSeason = $competition->season;
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
     * — domestic (ESPCUP, ESPSUP) and continental (UEFASUP).
     * Also updates ESPCUP entry_rounds for supercup-qualifying teams.
     */
    public function conductCupDraws(string $gameId, string $countryCode): void
    {
        // Update ESPCUP entry_rounds based on supercup qualifiers
        $this->updateCupEntryRoundsForSupercupTeams($gameId, $countryCode);

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
     * Update ESPCUP entry_rounds for teams qualifying for the supercup.
     * Supercup-qualifying teams enter the cup at a later round.
     *
     * Upserts (rather than a plain UPDATE) so a supercup team that isn't
     * already a cup entry is inserted at the configured entry_round. The
     * supercup qualifier path now scrubs reserves up front, so any team
     * landing here is eligible for the cup — the upsert is a tiny safety
     * belt for the silent-UPDATE-miss case that produced the 113-team
     * round-1 OddCupDrawPoolException in production.
     */
    private function updateCupEntryRoundsForSupercupTeams(string $gameId, string $countryCode): void
    {
        $supercupConfig = $this->countryConfig->supercup($countryCode);
        if (!$supercupConfig) {
            return;
        }

        $cupEntryRound = $supercupConfig['cup_entry_round'] ?? null;
        if (!$cupEntryRound) {
            return;
        }

        $domesticCupId = $supercupConfig['cup'];
        $supercupId = $supercupConfig['competition'];

        $supercupTeamIds = CompetitionEntry::where('game_id', $gameId)
            ->where('competition_id', $supercupId)
            ->pluck('team_id')
            ->toArray();

        if (empty($supercupTeamIds)) {
            return;
        }

        // Scrub reserves from the supercup list before the upsert below.
        // The upstream SupercupQualificationProcessor scrubs reserves at
        // qualification time now, but games whose supercup field was
        // written by the previous code can still carry one. Without this
        // filter, the upsert would insert the reserve into the cup,
        // breaking the reserve-never-in-cup invariant. Note: this does
        // not by itself recover an in-flight transition that already
        // contains a reserve supercup pick (round 1 parity is fixed
        // upstream by SupercupQualificationProcessor re-deriving the
        // field with the corrected query).
        $reserveTeamIds = Team::where('country', $countryCode)
            ->whereNotNull('parent_team_id')
            ->pluck('id')
            ->all();

        if (!empty($reserveTeamIds)) {
            $reserveLookup = array_flip($reserveTeamIds);
            $supercupTeamIds = array_values(array_filter(
                $supercupTeamIds,
                fn (string $teamId) => !isset($reserveLookup[$teamId]),
            ));
        }

        // Reset ALL domestic cup entries to round 1
        CompetitionEntry::where('game_id', $gameId)
            ->where('competition_id', $domesticCupId)
            ->update(['entry_round' => 1]);

        if (empty($supercupTeamIds)) {
            return;
        }

        $supercupRows = array_map(fn (string $teamId) => [
            'game_id' => $gameId,
            'competition_id' => $domesticCupId,
            'team_id' => $teamId,
            'entry_round' => $cupEntryRound,
        ], $supercupTeamIds);

        CompetitionEntry::upsert(
            $supercupRows,
            ['game_id', 'competition_id', 'team_id'],
            ['entry_round']
        );
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
