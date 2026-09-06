<?php

namespace App\Modules\Season\Processors;

use App\Modules\Season\Contracts\SeasonProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Competition\Services\CountryConfig;
use App\Modules\Competition\Services\LeagueFixtureGenerator;
use App\Modules\Competition\Services\SwissDrawService;
use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\CompetitionTeam;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\GameStanding;
use App\Models\SimulatedSeason;
use App\Models\Team;
use Illuminate\Support\Facades\Log;

/**
 * Determines which teams qualify for UEFA competitions
 * based on league final standings and cup winner, driven by country config.
 *
 * Priority: 105 (runs after SupercupQualificationProcessor)
 *
 * Qualification slots are defined in config/countries.php under
 * each country's 'continental_slots' and 'cup_winner_slot' keys.
 *
 * A country may declare more than one cup winner slot — England's FA Cup pays
 * a Europa League place and its EFL Cup a Conference League one. They are
 * applied in declaration order, best competition first, so a later cascade
 * sees what an earlier one handed out.
 *
 * Cup winner cascade rules, per slot:
 * - If the cup winner is NOT already qualified via league position, they take
 *   the cup's slot.
 * - If the cup winner already holds a place at least as good as the cup's, the
 *   cup's slot cascades to the next non-qualified team in the standings.
 * - If the cup winner holds a lesser place, they are upgraded to the cup's
 *   competition and the place they vacate cascades in the same way.
 * - If there is no cup winner at all — a country the user doesn't manage never
 *   has its cups drawn — the slot cascades to the next non-qualified team, so
 *   the country still sends its full contingent.
 *
 * European holder rules:
 * - The defending UCL winner auto-qualifies for next season's UCL.
 * - The UEL winner is promoted into next season's UCL.
 * In both cases, if the holder already has a UEL/UECL spot, that spot is
 * vacated and cascades to the next non-qualified team from the same country.
 */
class UefaQualificationProcessor implements SeasonProcessor
{
    public function __construct(
        private CountryConfig $countryConfig,
    ) {}

    public function priority(): int
    {
        return 100;
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        $swissCompetitionIds = Competition::where('handler_type', 'swiss_format')
            ->pluck('id')
            ->toArray();

        $this->clearSwissFormatEntries($game, $swissCompetitionIds);

        $userCountry = $game->country ?? 'ES';
        $allQualifications = [];
        foreach ($this->countryConfig->allCountryCodes() as $countryCode) {
            $countryQualifications = $this->processCountry($game, $countryCode, $data, $userCountry);
            if (!empty($countryQualifications)) {
                $allQualifications[$countryCode] = $countryQualifications;
            }
        }

        $this->qualifyUclWinner($game, $data);
        $this->qualifyUelWinner($game, $data);
        $this->fillRemainingContinentalSlots($game, $swissCompetitionIds);

        $data->setMetadata('uefaQualifications', $allQualifications);

        return $data;
    }

    /**
     * Clear all Swiss format competition entries before rebuilding qualifications.
     *
     * Without this, filler teams from the previous season persist across seasons
     * because writeQualifications() only removes teams from configured countries.
     */
    private function clearSwissFormatEntries(Game $game, array $swissCompetitionIds): void
    {
        if (!empty($swissCompetitionIds)) {
            CompetitionEntry::where('game_id', $game->id)
                ->whereIn('competition_id', $swissCompetitionIds)
                ->delete();
        }
    }

    /**
     * @return array<string, string> teamId => competitionId qualifications for this country
     */
    private function processCountry(Game $game, string $countryCode, SeasonTransitionData $data, string $userCountry): array
    {
        $slots = $this->countryConfig->continentalSlots($countryCode);
        if (empty($slots)) {
            return [];
        }

        // Build a map of teamId => competitionId from league standings
        $qualifications = []; // teamId => competitionId
        $standings = [];      // position => teamId (from the relevant league)

        foreach ($slots as $leagueId => $continentalAllocations) {
            $leagueStandings = $this->getLeagueStandings($game, $leagueId);

            if (empty($leagueStandings)) {
                continue;
            }

            $standings = $leagueStandings;

            foreach ($continentalAllocations as $continentalId => $positions) {
                foreach ($positions as $position) {
                    if (isset($leagueStandings[$position])) {
                        $qualifications[$leagueStandings[$position]] = $continentalId;
                    }
                }
            }
        }

        // Handle cup winner slots, best competition first so a later cascade
        // sees what an earlier one handed out.
        if (!empty($standings)) {
            foreach ($this->countryConfig->cupWinnerSlots($countryCode) as $cupWinnerConfig) {
                $this->applyCupWinnerCascade(
                    $game->id,
                    $countryCode,
                    $cupWinnerConfig,
                    $qualifications,
                    $standings,
                    $data,
                    $userCountry,
                );
            }
        }

        // Write all qualifications to competition_entries
        $this->writeQualifications($game->id, $qualifications, $countryCode);

        return $qualifications;
    }

    /**
     * Apply cup winner cascade logic to the qualifications map.
     */
    private function applyCupWinnerCascade(
        string $gameId,
        string $countryCode,
        array $cupWinnerConfig,
        array &$qualifications,
        array $standings,
        SeasonTransitionData $data,
        string $userCountry,
    ): void {
        $cupWinnerId = $this->getCupWinner($gameId, $countryCode, $cupWinnerConfig['cup']);
        $isUserCountry = $countryCode === $userCountry;

        // Only store cup winner metadata for the user's country
        if ($isUserCountry) {
            $data->setMetadata('cupWinner', [
                'country' => $countryCode,
                'cup' => $cupWinnerConfig['cup'],
                'teamId' => $cupWinnerId,
            ]);
        }

        $targetCompetition = $cupWinnerConfig['competition'];

        // No winner to reward — the cup wasn't played out (a country the user
        // doesn't manage never has its cups drawn), or it was abandoned. The
        // slot still belongs to the country, so it falls to the next team in
        // the table rather than going unused.
        if (!$cupWinnerId) {
            $nextTeam = $this->getNextNonQualifiedTeam($standings, $qualifications);
            if ($nextTeam) {
                $qualifications[$nextTeam] = $targetCompetition;
            }

            return;
        }

        $existingQualification = $qualifications[$cupWinnerId] ?? null;

        if (!$existingQualification) {
            // Cup winner is NOT already qualified — give them the cup's spot
            $qualifications[$cupWinnerId] = $targetCompetition;
            if ($isUserCountry) {
                $data->setMetadata('cupWinnerCascade', 'direct');
            }
        } elseif ($this->europeanRank($existingQualification) >= $this->europeanRank($targetCompetition)) {
            // Cup winner already holds a place at least as good as the cup's
            // — the cup's spot cascades to the next non-qualified team.
            $nextTeam = $this->getNextNonQualifiedTeam($standings, $qualifications);
            if ($nextTeam) {
                $qualifications[$nextTeam] = $targetCompetition;
            }
            if ($isUserCountry) {
                $data->setMetadata('cupWinnerCascade', "cascade_from_{$existingQualification}");
            }
        } else {
            // Cup winner held a lesser place (UECL via the league, say, when
            // the cup pays a UEL berth) — upgrade them, then cascade the spot
            // they vacated. Assign first, so the vacated spot can't come
            // straight back to them.
            $qualifications[$cupWinnerId] = $targetCompetition;

            $nextTeam = $this->getNextNonQualifiedTeam($standings, $qualifications);
            if ($nextTeam) {
                $qualifications[$nextTeam] = $existingQualification;
            }
            if ($isUserCountry) {
                $data->setMetadata(
                    'cupWinnerCascade',
                    $existingQualification === 'UECL' ? 'uecl_upgrade' : "upgrade_from_{$existingQualification}",
                );
            }
        }
    }

    /**
     * Ranks the European competitions so the cascade can compare a team's
     * existing place against the one a cup is offering, whichever cup it is.
     * England's EFL Cup pays a Conference League berth, so "already qualified
     * for something better" is not the same test as "already in the UCL".
     */
    private function europeanRank(string $competitionId): int
    {
        return match ($competitionId) {
            'UCL' => 3,
            'UEL' => 2,
            'UECL' => 1,
            default => 0,
        };
    }

    /**
     * Get league standings: real standings first, then simulated results as fallback.
     *
     * @return array<int, string> position => teamId
     */
    private function getLeagueStandings(Game $game, string $leagueId): array
    {
        // Try real standings first (filter played > 0 to skip bootstrapped zeros)
        $standings = GameStanding::where('game_id', $game->id)
            ->where('competition_id', $leagueId)
            ->where('played', '>', 0)
            ->orderBy('position')
            ->pluck('team_id', 'position')
            ->toArray();

        if (!empty($standings)) {
            return $standings;
        }

        // Fall back to simulated season results
        $simulated = SimulatedSeason::where('game_id', $game->id)
            ->where('season', $game->season)
            ->where('competition_id', $leagueId)
            ->first();

        if (!$simulated || empty($simulated->results)) {
            return [];
        }

        // Convert 0-indexed results array to 1-indexed position => teamId map
        $standings = [];
        foreach ($simulated->results as $index => $teamId) {
            $standings[$index + 1] = $teamId;
        }

        return $standings;
    }

    /**
     * Find the domestic cup winner from the final cup tie. The final is the
     * last round of the cup's schedule.json for the game's base season.
     *
     * A ghost team — a lower-division cup entrant with no squad — can win a
     * cup outright, and the deeper a country's ghost field the likelier it
     * is (England's FA Cup is 44 ghosts in 64). It cannot then take a place
     * in a 36-team Swiss league phase, so a winner registered in no playable
     * league counts as no winner and its slot cascades to the league table.
     */
    private function getCupWinner(string $gameId, string $countryCode, string $cupId): ?string
    {
        $baseSeason = Game::where('id', $gameId)->value('base_season');
        $finalRound = $baseSeason ? LeagueFixtureGenerator::finalKnockoutRound($cupId, $baseSeason) : null;

        if (!$finalRound) {
            return null;
        }

        $finalTie = CupTie::where('game_id', $gameId)
            ->where('competition_id', $cupId)
            ->where('round_number', $finalRound)
            ->where('completed', true)
            ->first();

        $winnerId = $finalTie?->winner_id;

        if ($winnerId === null || !$this->hasSquad($gameId, $winnerId)) {
            return null;
        }

        return $winnerId;
    }

    /**
     * Whether a team has any players in this game. A ghost has none, which is
     * the same test MatchSimulator uses when it forbids a squad-less side
     * from scoring.
     */
    private function hasSquad(string $gameId, string $teamId): bool
    {
        return GamePlayer::where('game_id', $gameId)
            ->where('team_id', $teamId)
            ->exists();
    }

    /**
     * Find the next team in standings that isn't already qualified for any competition.
     */
    private function getNextNonQualifiedTeam(array $standings, array $qualifications): ?string
    {
        foreach ($standings as $position => $teamId) {
            if (!isset($qualifications[$teamId])) {
                return $teamId;
            }
        }

        return null;
    }

    /**
     * Write all qualifications to competition_entries, removing old country teams first.
     */
    private function writeQualifications(string $gameId, array $qualifications, string $countryCode): void
    {
        $countryTeamIds = Team::where('country', $countryCode)->pluck('id')->toArray();

        // Group qualifications by competition, skipping any that don't exist
        // (e.g. UECL is in config but may not be seeded yet)
        $byCompetition = [];
        foreach ($qualifications as $teamId => $competitionId) {
            $byCompetition[$competitionId][] = $teamId;
        }

        $validCompetitionIds = Competition::whereIn('id', array_keys($byCompetition))->pluck('id')->toArray();

        foreach ($byCompetition as $competitionId => $teamIds) {
            if (!in_array($competitionId, $validCompetitionIds)) {
                continue;
            }
            // Remove old teams from this country from the competition
            CompetitionEntry::where('game_id', $gameId)
                ->where('competition_id', $competitionId)
                ->whereIn('team_id', $countryTeamIds)
                ->delete();

            // Add new qualifiers in bulk
            $rows = array_map(fn (string $teamId) => [
                'game_id' => $gameId,
                'competition_id' => $competitionId,
                'team_id' => $teamId,
                'entry_round' => 1,
            ], $teamIds);

            CompetitionEntry::upsert(
                $rows,
                ['game_id', 'competition_id', 'team_id'],
                ['entry_round']
            );
        }
    }

    /**
     * Qualify the defending UCL winner into next season's UCL.
     *
     * Mirrors qualifyUelWinner: if the holder did not already secure a UCL
     * spot via their league finish, they are inserted in place of a
     * non-configured-country filler, and any UEL/UECL spot they held
     * cascades to the next non-qualified team from their country's league.
     */
    private function qualifyUclWinner(Game $game, SeasonTransitionData $data): void
    {
        $uclWinnerId = $data->getMetadata(SeasonTransitionData::META_UCL_WINNER);
        if (!$uclWinnerId) {
            return;
        }

        $this->promoteWinnerToUcl($game, $uclWinnerId);
    }

    /**
     * Qualify the UEL winner into next season's UCL.
     *
     * If the winner is already in UCL, do nothing.
     * Otherwise, add them to UCL (replacing a non-configured-country team to
     * maintain 36), and cascade any vacated UEL/UECL spot to the next
     * non-qualified team from the same country's league standings.
     */
    private function qualifyUelWinner(Game $game, SeasonTransitionData $data): void
    {
        $uelWinnerId = $data->getMetadata(SeasonTransitionData::META_UEL_WINNER);
        if (!$uelWinnerId) {
            return;
        }

        $this->promoteWinnerToUcl($game, $uelWinnerId);
    }

    /**
     * Insert a European holder into next season's UCL, replacing a
     * non-configured-country filler to keep the field size stable, and
     * cascade any UEL/UECL spot they previously held.
     *
     * No-op if UCL isn't seeded yet or the team is already in UCL.
     */
    private function promoteWinnerToUcl(Game $game, string $winnerId): void
    {
        $uclCompetition = Competition::find('UCL');
        if (!$uclCompetition) {
            return;
        }

        $alreadyInUcl = CompetitionEntry::where('game_id', $game->id)
            ->where('competition_id', 'UCL')
            ->where('team_id', $winnerId)
            ->exists();

        if ($alreadyInUcl) {
            return;
        }

        // Find a non-configured-country team to replace
        $configuredCountries = collect($this->countryConfig->allCountryCodes())
            ->filter(fn (string $code) => !empty($this->countryConfig->continentalSlots($code)))
            ->all();

        $replaceable = CompetitionEntry::where('competition_entries.game_id', $game->id)
            ->where('competition_entries.competition_id', 'UCL')
            ->join('teams', 'competition_entries.team_id', '=', 'teams.id')
            ->whereNotIn('teams.country', $configuredCountries)
            ->select('competition_entries.*')
            ->get();

        if ($replaceable->isNotEmpty()) {
            // Remove a random non-configured-country team
            $toRemove = $replaceable->random();
            CompetitionEntry::where('game_id', $game->id)
                ->where('competition_id', 'UCL')
                ->where('team_id', $toRemove->team_id)
                ->delete();
        }

        CompetitionEntry::updateOrCreate(
            [
                'game_id' => $game->id,
                'competition_id' => 'UCL',
                'team_id' => $winnerId,
            ],
            [
                'entry_round' => 1,
            ]
        );

        // Cascade: if the holder had a UEL or UECL spot (e.g. via league or cup
        // winner cascade), remove it and give that spot to the next non-qualified
        // team from the same country's league standings.
        $this->cascadeVacatedSpot($game, $winnerId);
    }

    /**
     * If a team holds a UEL or UECL entry that is now redundant because they
     * were upgraded to UCL, remove it and cascade the spot to the next
     * non-qualified team from the same country's league standings.
     */
    private function cascadeVacatedSpot(Game $game, string $teamId): void
    {
        $vacatedEntry = CompetitionEntry::where('game_id', $game->id)
            ->where('team_id', $teamId)
            ->whereIn('competition_id', ['UEL', 'UECL'])
            ->first();

        if (!$vacatedEntry) {
            return;
        }

        $vacatedCompetition = $vacatedEntry->competition_id;

        CompetitionEntry::where('game_id', $game->id)
            ->where('team_id', $teamId)
            ->where('competition_id', $vacatedCompetition)
            ->delete();

        // Find the team's country to look up league standings
        $team = Team::find($teamId);
        if (!$team) {
            return;
        }

        $countryCode = $team->country;
        $slots = $this->countryConfig->continentalSlots($countryCode);
        if (empty($slots)) {
            return;
        }

        // Get the league standings and current qualifications for this country
        $leagueId = array_key_first($slots);
        $leagueStandings = $this->getLeagueStandings($game, $leagueId);
        if (empty($leagueStandings)) {
            return;
        }

        // Build current qualifications map from competition_entries
        $countryTeamIds = Team::where('country', $countryCode)->pluck('id')->toArray();

        $currentQualifications = CompetitionEntry::where('game_id', $game->id)
            ->whereIn('competition_id', ['UCL', 'UEL', 'UECL'])
            ->whereIn('team_id', $countryTeamIds)
            ->pluck('competition_id', 'team_id')
            ->toArray();

        $nextTeam = $this->getNextNonQualifiedTeam($leagueStandings, $currentQualifications);
        if ($nextTeam) {
            CompetitionEntry::updateOrCreate(
                [
                    'game_id' => $game->id,
                    'competition_id' => $vacatedCompetition,
                    'team_id' => $nextTeam,
                ],
                ['entry_round' => 1]
            );
        }
    }

    /**
     * Fill remaining slots to reach a full league phase in the user's
     * swiss_format competition.
     *
     * Only the competition the user's team participates in needs a full draw.
     * Other swiss_format competitions are never initialized (no fixtures, no standings),
     * so filling them would waste the European team pool.
     *
     * Fillers come from European teams (competitions with country='EU') that are not
     * already in the target competition. Only teams from non-configured countries
     * (those without continental_slots) are eligible, since configured countries
     * already have all their spots allocated via processCountry().
     */
    private function fillRemainingContinentalSlots(Game $game, array $swissCompetitionIds): void
    {
        if (empty($swissCompetitionIds)) {
            return;
        }

        // Find which swiss competition the user's team qualified for (if any)
        $userCompetitionId = CompetitionEntry::where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->whereIn('competition_id', $swissCompetitionIds)
            ->value('competition_id');

        if (!$userCompetitionId) {
            Log::info('[UEFA] User team not in any Swiss format competition, skipping filler allocation');

            return;
        }

        // Collect teams already in the user's competition
        $usedTeamIds = CompetitionEntry::where('game_id', $game->id)
            ->where('competition_id', $userCompetitionId)
            ->pluck('team_id')
            ->toArray();

        $currentCount = count($usedTeamIds);
        $needed = SwissDrawService::LEAGUE_PHASE_TEAMS - $currentCount;

        Log::info("[UEFA] {$userCompetitionId}: {$currentCount}/"
            . SwissDrawService::LEAGUE_PHASE_TEAMS . " teams, need {$needed} fillers");

        if ($needed <= 0) {
            return;
        }

        // Countries with continental_slots already have their spots filled by
        // processCountry(). Fillers must come from other European countries only.
        $configuredCountries = collect($this->countryConfig->allCountryCodes())
            ->filter(fn (string $code) => !empty($this->countryConfig->continentalSlots($code)))
            ->all();

        // European team pool: teams registered in any competition with country='EU',
        // excluding teams already in the target competition and teams from configured countries.
        $europeanTeamPool = CompetitionTeam::query()
            ->join('competitions', 'competition_teams.competition_id', '=', 'competitions.id')
            ->join('teams', 'competition_teams.team_id', '=', 'teams.id')
            ->where('competitions.country', 'EU')
            // Each competition's current season only — the pivot keeps previous
            // seasons' rows, which would pad the pool with clubs that have since
            // dropped out of the European pool entirely.
            ->whereColumn('competition_teams.season', 'competitions.season')
            ->whereNotIn('competition_teams.team_id', $usedTeamIds)
            ->whereNotIn('teams.country', $configuredCountries)
            ->distinct()
            ->pluck('competition_teams.team_id')
            ->toArray();

        $fillerTeams = array_slice($europeanTeamPool, 0, $needed);

        if (!empty($fillerTeams)) {
            $rows = array_map(fn (string $teamId) => [
                'game_id' => $game->id,
                'competition_id' => $userCompetitionId,
                'team_id' => $teamId,
                'entry_round' => 1,
            ], $fillerTeams);

            CompetitionEntry::upsert(
                $rows,
                ['game_id', 'competition_id', 'team_id'],
                ['entry_round']
            );

            Log::info("[UEFA] {$userCompetitionId}: filled " . count($fillerTeams) . ' teams from pool of ' . count($europeanTeamPool));
        }

        if (count($fillerTeams) < $needed) {
            Log::warning("[UEFA] {$userCompetitionId}: need {$needed} fillers but only " . count($fillerTeams) . ' available in European pool');
        }
    }
}
