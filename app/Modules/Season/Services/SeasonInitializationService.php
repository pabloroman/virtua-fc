<?php

namespace App\Modules\Season\Services;

use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Support\Str;
use App\Modules\Competition\Services\CupDrawService;
use App\Modules\Competition\Services\LeagueFixtureGenerator;
use App\Modules\Competition\Services\StandingsCalculator;
use App\Modules\Competition\Services\CountryConfig;
use App\Modules\Competition\Services\SwissDrawService;

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

    /**
     * Generate league fixtures from schedule.json, adjusted for season year.
     */
    public function generateLeagueFixtures(string $gameId, string $competitionId, string $season): void
    {
        $competition = Competition::find($competitionId);
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
            return;
        }

        $competition = Competition::find($competitionId);
        if (!$competition) {
            return;
        }

        // Build draw teams — from explicit data or auto-assign pots by market value
        if ($teamsWithPots !== null) {
            $drawTeams = $teamsWithPots;
        } else {
            $drawTeams = $this->buildDrawTeamsFromGameState($gameId, $competitionId);
        }

        if (count($drawTeams) < 36) {
            return;
        }

        // Load schedule and adjust dates for the season
        $baseSeason = $competition->season;
        $schedulePath = base_path("data/{$baseSeason}/{$competitionId}/schedule.json");
        if (!file_exists($schedulePath)) {
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

        // Initialize standings
        $teamIds = CompetitionEntry::where('game_id', $gameId)
            ->where('competition_id', $competitionId)
            ->pluck('team_id')
            ->toArray();

        $this->standingsCalculator->initializeStandings($gameId, $competitionId, $teamIds);
    }

    /**
     * Conduct cup draws for all knockout cups in a country.
     * Also updates ESPCUP entry_rounds for supercup-qualifying teams.
     */
    public function conductCupDraws(string $gameId, string $countryCode): void
    {
        // Update ESPCUP entry_rounds based on supercup qualifiers
        $this->updateCupEntryRoundsForSupercupTeams($gameId, $countryCode);

        // Draw round 1 for all domestic knockout cups (ESPCUP, ESPSUP)
        $cupIds = $this->countryConfig->domesticCupIds($countryCode);

        foreach ($cupIds as $cupId) {
            $competition = Competition::find($cupId);
            if (!$competition || $competition->handler_type !== 'knockout_cup') {
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

        // Get teams qualifying for the supercup
        $supercupTeamIds = CompetitionEntry::where('game_id', $gameId)
            ->where('competition_id', $supercupId)
            ->pluck('team_id')
            ->toArray();

        if (empty($supercupTeamIds)) {
            return;
        }

        // Reset ALL domestic cup entries to round 1
        CompetitionEntry::where('game_id', $gameId)
            ->where('competition_id', $domesticCupId)
            ->update(['entry_round' => 1]);

        // Set supercup-qualifying teams to enter at the later round
        CompetitionEntry::where('game_id', $gameId)
            ->where('competition_id', $domesticCupId)
            ->whereIn('team_id', $supercupTeamIds)
            ->update(['entry_round' => $cupEntryRound]);
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
                'scheduled_date' => Carbon::parse($fixture['date']),
                'home_score' => null,
                'away_score' => null,
                'played' => false,
            ];
        }

        foreach (array_chunk($rows, 100) as $chunk) {
            GameMatch::insert($chunk);
        }
    }
}
