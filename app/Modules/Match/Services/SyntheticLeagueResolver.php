<?php

namespace App\Modules\Match\Services;

use App\Models\ClubProfile;
use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\TeamReputation;
use App\Modules\Competition\Services\LeagueFixtureGenerator;
use App\Modules\Competition\Services\StandingsCalculator;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Lazily generates fixtures and resolves match scorelines for non-user
 * league competitions, on demand. Standings & match rows are persisted
 * after first view so subsequent visits are pure reads.
 *
 * Scorelines come from independent home/away Poisson draws with λ derived
 * from each team's reputation tier plus a small home boost. No MatchEvent,
 * lineup, or MVP data is produced.
 *
 * Concurrency: a Postgres transactional advisory lock keyed on
 * (gameId, competitionId) serializes initialization and resolution so
 * a fast double-click cannot create duplicate fixtures or apply standings
 * twice.
 */
class SyntheticLeagueResolver
{
    /**
     * Reputation → goals-per-match λ. Values are tuned so the average synthetic
     * scoreline (~2.6 goals across two evenly-matched teams once the home boost
     * is applied) lands close to top-flight averages while still allowing
     * upsets via Poisson variance.
     */
    private const LAMBDA_BY_REPUTATION = [
        ClubProfile::REPUTATION_LOCAL        => 0.9,
        ClubProfile::REPUTATION_MODEST       => 1.1,
        ClubProfile::REPUTATION_ESTABLISHED  => 1.3,
        ClubProfile::REPUTATION_CONTINENTAL  => 1.55,
        ClubProfile::REPUTATION_ELITE        => 1.8,
    ];

    private const HOME_BOOST = 0.3;

    private const MAX_GOALS_PER_TEAM = 7;

    /** Handler types this resolver supports. Cups & swiss are out of scope. */
    private const SUPPORTED_HANDLER_TYPES = ['league', 'league_with_playoff'];

    public function __construct(
        private readonly LeagueFixtureGenerator $fixtureGenerator,
        private readonly StandingsCalculator $standingsCalculator,
    ) {}

    /**
     * Convenience: ensure fixtures exist and resolve every match due by
     * `$upToDate` (defaults to the game's current date).
     */
    public function catchUp(Game $game, Competition $competition, ?CarbonInterface $upToDate = null): void
    {
        if (! $this->isSupported($competition) || $this->isUsersPrimaryLeague($game, $competition)) {
            return;
        }

        $this->withLock($game->id, $competition->id, function () use ($game, $competition, $upToDate): void {
            $this->ensureInitializedLocked($game, $competition);
            $this->resolveDueMatchesLocked($game, $competition, $upToDate ?? $game->current_date);
        });
    }

    /**
     * Generate fixtures and zero-initialize standings if they don't exist yet.
     * Idempotent.
     */
    public function ensureInitialized(Game $game, Competition $competition): void
    {
        if (! $this->isSupported($competition) || $this->isUsersPrimaryLeague($game, $competition)) {
            return;
        }

        $this->withLock($game->id, $competition->id, function () use ($game, $competition): void {
            $this->ensureInitializedLocked($game, $competition);
        });
    }

    /**
     * Resolve every unplayed fixture with `scheduled_date <= $upToDate` via
     * Poisson scoreline draws and apply the results to standings. Idempotent.
     */
    public function resolveDueMatches(Game $game, Competition $competition, ?CarbonInterface $upToDate = null): void
    {
        if (! $this->isSupported($competition) || $this->isUsersPrimaryLeague($game, $competition)) {
            return;
        }

        $this->withLock($game->id, $competition->id, function () use ($game, $competition, $upToDate): void {
            $this->resolveDueMatchesLocked($game, $competition, $upToDate ?? $game->current_date);
        });
    }

    private function isSupported(Competition $competition): bool
    {
        return in_array($competition->handler_type, self::SUPPORTED_HANDLER_TYPES, true);
    }

    /**
     * The user's primary league is simulated match-by-match by the real engine —
     * never let synthetic Poisson draws touch it.
     */
    private function isUsersPrimaryLeague(Game $game, Competition $competition): bool
    {
        return $competition->id === $game->competition_id;
    }

    /**
     * Wrap the work in a transaction with a Postgres advisory lock keyed on
     * (gameId, competitionId). The lock is held for the duration of the
     * transaction and released automatically on commit/rollback.
     */
    private function withLock(string $gameId, string $competitionId, callable $work): void
    {
        DB::transaction(function () use ($gameId, $competitionId, $work): void {
            // Two-arg form (int4, int4) so we don't depend on PG13+
            // hashtextextended. The (gameId, competitionId) tuple is
            // unique enough for our purposes.
            DB::statement('SELECT pg_advisory_xact_lock(hashtext(?), hashtext(?))', [$gameId, $competitionId]);
            $work();
        });
    }

    private function ensureInitializedLocked(Game $game, Competition $competition): void
    {
        $hasFixtures = GameMatch::where('game_id', $game->id)
            ->where('competition_id', $competition->id)
            ->exists();

        if ($hasFixtures) {
            return;
        }

        $teamIds = CompetitionEntry::where('game_id', $game->id)
            ->where('competition_id', $competition->id)
            ->pluck('team_id')
            ->toArray();

        if (empty($teamIds)) {
            Log::info('[SyntheticLeague] No competition entries; skipping init', [
                'game_id' => $game->id,
                'competition_id' => $competition->id,
            ]);

            return;
        }

        if (count($teamIds) % 2 !== 0 || count($teamIds) < 4) {
            Log::warning('[SyntheticLeague] Cannot generate fixtures: invalid team count', [
                'game_id' => $game->id,
                'competition_id' => $competition->id,
                'team_count' => count($teamIds),
            ]);

            return;
        }

        $matchdays = LeagueFixtureGenerator::loadMatchdays($competition->id, $competition->season);
        $yearDiff = (int) $game->season - (int) $competition->season;
        if ($yearDiff !== 0) {
            $matchdays = LeagueFixtureGenerator::adjustMatchdayYears($matchdays, $yearDiff);
        }

        $fixtures = $this->fixtureGenerator->generate($teamIds, $matchdays);

        $rows = [];
        foreach ($fixtures as $fixture) {
            $rows[] = [
                'id' => Str::uuid()->toString(),
                'game_id' => $game->id,
                'competition_id' => $competition->id,
                'round_number' => $fixture['matchday'],
                'home_team_id' => $fixture['homeTeamId'],
                'away_team_id' => $fixture['awayTeamId'],
                'scheduled_date' => $fixture['date'],
                'home_score' => null,
                'away_score' => null,
                'played' => false,
            ];
        }

        DB::table('game_matches')->insert($rows);

        $this->standingsCalculator->initializeStandings($game->id, $competition->id, $teamIds);

        Log::info('[SyntheticLeague] Initialized', [
            'game_id' => $game->id,
            'competition_id' => $competition->id,
            'fixtures' => count($rows),
            'teams' => count($teamIds),
        ]);
    }

    private function resolveDueMatchesLocked(Game $game, Competition $competition, ?CarbonInterface $upToDate): void
    {
        if ($upToDate === null) {
            return;
        }

        $cutoff = Carbon::parse($upToDate)->toDateString();

        $unplayed = GameMatch::where('game_id', $game->id)
            ->where('competition_id', $competition->id)
            ->where('played', false)
            ->where('scheduled_date', '<=', $cutoff)
            ->orderBy('scheduled_date')
            ->orderBy('round_number')
            ->get(['id', 'home_team_id', 'away_team_id']);

        if ($unplayed->isEmpty()) {
            return;
        }

        $teamIds = $unplayed
            ->flatMap(fn (GameMatch $m) => [$m->home_team_id, $m->away_team_id])
            ->unique()
            ->values()
            ->all();

        $reputations = TeamReputation::resolveLevels($game->id, $teamIds);

        // Cache λ per team to avoid repeated lookups on the hot loop.
        $lambdas = [];
        foreach ($teamIds as $teamId) {
            $level = $reputations[$teamId] ?? ClubProfile::REPUTATION_LOCAL;
            $lambdas[$teamId] = self::LAMBDA_BY_REPUTATION[$level] ?? self::LAMBDA_BY_REPUTATION[ClubProfile::REPUTATION_LOCAL];
        }

        $matchResults = [];
        $scoreCases = [];
        $matchIds = [];

        foreach ($unplayed as $match) {
            $homeLambda = $lambdas[$match->home_team_id] + self::HOME_BOOST;
            $awayLambda = $lambdas[$match->away_team_id];

            $homeScore = $this->drawPoisson($homeLambda);
            $awayScore = $this->drawPoisson($awayLambda);

            $matchResults[] = [
                'homeTeamId' => $match->home_team_id,
                'awayTeamId' => $match->away_team_id,
                'homeScore' => $homeScore,
                'awayScore' => $awayScore,
            ];

            $scoreCases[$match->id] = [$homeScore, $awayScore];
            $matchIds[] = $match->id;
        }

        // Bulk-update game_matches with one statement per column.
        $idList = "'" . implode("','", $matchIds) . "'";
        $homeCases = [];
        $awayCases = [];
        foreach ($scoreCases as $id => [$home, $away]) {
            $homeCases[] = "WHEN id = '{$id}' THEN {$home}";
            $awayCases[] = "WHEN id = '{$id}' THEN {$away}";
        }

        DB::statement(
            'UPDATE game_matches SET '
            . 'home_score = CASE ' . implode(' ', $homeCases) . ' END, '
            . 'away_score = CASE ' . implode(' ', $awayCases) . ' END, '
            . 'played = TRUE, '
            . 'standings_applied = TRUE '
            . "WHERE id IN ({$idList})"
        );

        $this->standingsCalculator->bulkUpdateAfterMatches($game->id, $competition->id, $matchResults);
        $this->standingsCalculator->recalculatePositions($game->id, $competition->id);

        Log::info('[SyntheticLeague] Resolved due matches', [
            'game_id' => $game->id,
            'competition_id' => $competition->id,
            'count' => count($matchResults),
            'cutoff' => $cutoff,
        ]);
    }

    /**
     * Draw a non-negative integer from a Poisson distribution with mean λ
     * using Knuth's algorithm. Capped at MAX_GOALS_PER_TEAM to keep scorelines
     * plausible for football.
     */
    private function drawPoisson(float $lambda): int
    {
        $L = exp(-$lambda);
        $k = 0;
        $p = 1.0;

        do {
            $k++;
            $p *= mt_rand() / mt_getrandmax();
        } while ($p > $L && $k <= self::MAX_GOALS_PER_TEAM + 1);

        return min($k - 1, self::MAX_GOALS_PER_TEAM);
    }
}
