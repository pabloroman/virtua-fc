<?php

namespace App\Modules\Season\Processors;

use App\Modules\Season\Contracts\SeasonProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Competition\Services\CountryConfig;
use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\CompetitionTeam;
use App\Models\CupTie;
use App\Models\Game;
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
 * Cup winner cascade rules:
 * - If cup winner is NOT already qualified via league position, they get the UEL slot.
 * - If cup winner already qualifies for UCL or UEL via league, the UEL cup spot
 *   cascades to the next non-qualified team in standings.
 * - If cup winner qualifies for UECL via league, they get upgraded to UEL and
 *   the UECL spot cascades to the next non-qualified team.
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

        // Handle cup winner slot
        $cupWinnerConfig = $this->countryConfig->cupWinnerSlot($countryCode);
        if ($cupWinnerConfig && !empty($standings)) {
            $this->applyCupWinnerCascade(
                $game->id,
                $countryCode,
                $cupWinnerConfig,
                $qualifications,
                $standings,
                $slots,
                $data,
                $userCountry,
            );
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
        array $slots,
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

        if (!$cupWinnerId) {
            return;
        }

        $targetCompetition = $cupWinnerConfig['competition']; // UEL

        $existingQualification = $qualifications[$cupWinnerId] ?? null;

        if (!$existingQualification) {
            // Cup winner is NOT already qualified — give them the UEL spot
            $qualifications[$cupWinnerId] = $targetCompetition;
            if ($isUserCountry) {
                $data->setMetadata('cupWinnerCascade', 'direct');
            }
        } elseif ($existingQualification === 'UCL' || $existingQualification === $targetCompetition) {
            // Cup winner already in UCL or UEL — cascade the cup's UEL spot
            // to the next non-qualified team
            $nextTeam = $this->getNextNonQualifiedTeam($standings, $qualifications);
            if ($nextTeam) {
                $qualifications[$nextTeam] = $targetCompetition;
            }
            if ($isUserCountry) {
                $data->setMetadata('cupWinnerCascade', "cascade_from_{$existingQualification}");
            }
        } elseif ($existingQualification === 'UECL') {
            // Cup winner was in UECL via league — upgrade them to UEL
            $qualifications[$cupWinnerId] = $targetCompetition;

            // Cascade the now-vacant UECL spot to the next non-qualified team
            $nextTeam = $this->getNextNonQualifiedTeam($standings, $qualifications);
            if ($nextTeam) {
                $qualifications[$nextTeam] = 'UECL';
            }
            if ($isUserCountry) {
                $data->setMetadata('cupWinnerCascade', 'uecl_upgrade');
            }
        }
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
     * Find the domestic cup winner from the final cup tie.
     */
    private function getCupWinner(string $gameId, string $countryCode, string $cupId): ?string
    {
        $supercupConfig = $this->countryConfig->supercup($countryCode);
        $finalRound = $supercupConfig['cup_final_round'] ?? null;

        if (!$finalRound) {
            return null;
        }

        $finalTie = CupTie::where('game_id', $gameId)
            ->where('competition_id', $cupId)
            ->where('round_number', $finalRound)
            ->where('completed', true)
            ->first();

        return $finalTie?->winner_id;
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

        // PLANES-SEAM: cross-plane JOIN. competition_entries=tenant, teams=control.
        // Restored while both planes share one physical Postgres. Re-split
        // before the planes are physically separated. See CLAUDE.md →
        // "Control plane / tenant plane".
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
     * Fill remaining slots to reach 36 teams in the user's swiss_format competition.
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
        $needed = 36 - $currentCount;

        Log::info("[UEFA] {$userCompetitionId}: {$currentCount}/36 teams, need {$needed} fillers");

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
