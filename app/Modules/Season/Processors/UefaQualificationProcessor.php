<?php

namespace App\Modules\Season\Processors;

use App\Modules\Season\Contracts\SeasonEndProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Competition\Services\CountryConfig;
use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\GameStanding;
use App\Models\SimulatedSeason;
use App\Models\Team;

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
 */
class UefaQualificationProcessor implements SeasonEndProcessor
{
    public function __construct(
        private CountryConfig $countryConfig,
    ) {}

    public function priority(): int
    {
        return 105;
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        foreach ($this->countryConfig->allCountryCodes() as $countryCode) {
            $this->processCountry($game, $countryCode);
        }

        $this->qualifyUelWinner($game, $data);
        $this->fillRemainingContinentalSlots($game);

        return $data;
    }

    private function processCountry(Game $game, string $countryCode): void
    {
        $slots = $this->countryConfig->continentalSlots($countryCode);
        if (empty($slots)) {
            return;
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
            );
        }

        // Write all qualifications to competition_entries
        $this->writeQualifications($game->id, $qualifications, $countryCode);
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
    ): void {
        $cupWinnerId = $this->getCupWinner($gameId, $countryCode, $cupWinnerConfig['cup']);
        if (!$cupWinnerId) {
            return;
        }

        $targetCompetition = $cupWinnerConfig['competition']; // UEL
        $leagueId = $cupWinnerConfig['league'];

        $existingQualification = $qualifications[$cupWinnerId] ?? null;

        if (!$existingQualification) {
            // Cup winner is NOT already qualified — give them the UEL spot
            $qualifications[$cupWinnerId] = $targetCompetition;
        } elseif ($existingQualification === 'UCL' || $existingQualification === $targetCompetition) {
            // Cup winner already in UCL or UEL — cascade the cup's UEL spot
            // to the next non-qualified team
            $nextTeam = $this->getNextNonQualifiedTeam($standings, $qualifications);
            if ($nextTeam) {
                $qualifications[$nextTeam] = $targetCompetition;
            }
        } elseif ($existingQualification === 'UECL') {
            // Cup winner was in UECL via league — upgrade them to UEL
            $qualifications[$cupWinnerId] = $targetCompetition;

            // Cascade the now-vacant UECL spot to the next non-qualified team
            $nextTeam = $this->getNextNonQualifiedTeam($standings, $qualifications);
            if ($nextTeam) {
                $qualifications[$nextTeam] = 'UECL';
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
        // Try real standings first
        $standings = GameStanding::where('game_id', $game->id)
            ->where('competition_id', $leagueId)
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

        // Group qualifications by competition
        $byCompetition = [];
        foreach ($qualifications as $teamId => $competitionId) {
            $byCompetition[$competitionId][] = $teamId;
        }

        foreach ($byCompetition as $competitionId => $teamIds) {
            $competition = Competition::find($competitionId);
            if (!$competition) {
                continue;
            }

            // Remove old teams from this country from the competition
            CompetitionEntry::where('game_id', $gameId)
                ->where('competition_id', $competitionId)
                ->whereIn('team_id', $countryTeamIds)
                ->delete();

            // Add new qualifiers
            foreach ($teamIds as $teamId) {
                CompetitionEntry::updateOrCreate(
                    [
                        'game_id' => $gameId,
                        'competition_id' => $competitionId,
                        'team_id' => $teamId,
                    ],
                    [
                        'entry_round' => 1,
                    ]
                );
            }
        }
    }

    /**
     * Qualify the UEL winner into next season's UCL.
     *
     * If the winner is already in UCL, do nothing.
     * Otherwise, add them and remove a non-configured-country team to maintain 36.
     */
    private function qualifyUelWinner(Game $game, SeasonTransitionData $data): void
    {
        $uelWinnerId = $data->getMetadata(SeasonTransitionData::META_UEL_WINNER);
        if (!$uelWinnerId) {
            return;
        }

        $uclCompetition = Competition::find('UCL');
        if (!$uclCompetition) {
            return;
        }

        // Check if already in UCL
        $alreadyInUcl = CompetitionEntry::where('game_id', $game->id)
            ->where('competition_id', 'UCL')
            ->where('team_id', $uelWinnerId)
            ->exists();

        if ($alreadyInUcl) {
            return;
        }

        // Find a non-configured-country team to replace
        $configuredCountries = collect($this->countryConfig->allCountryCodes())
            ->filter(fn (string $code) => !empty($this->countryConfig->continentalSlots($code)))
            ->all();

        $replaceable = CompetitionEntry::where('game_id', $game->id)
            ->where('competition_id', 'UCL')
            ->get()
            ->filter(function (CompetitionEntry $entry) use ($configuredCountries) {
                $team = Team::find($entry->team_id);
                return $team && !in_array($team->country, $configuredCountries);
            });

        if ($replaceable->isNotEmpty()) {
            // Remove a random non-configured-country team
            $toRemove = $replaceable->random();
            CompetitionEntry::where('game_id', $game->id)
                ->where('competition_id', 'UCL')
                ->where('team_id', $toRemove->team_id)
                ->delete();
        }

        // Add UEL winner to UCL
        CompetitionEntry::updateOrCreate(
            [
                'game_id' => $game->id,
                'competition_id' => 'UCL',
                'team_id' => $uelWinnerId,
            ],
            [
                'entry_round' => 1,
            ]
        );
    }

    /**
     * Fill remaining continental slots to reach 36 teams per swiss_format competition.
     *
     * Selects teams with GamePlayer records, ranked by average market value,
     * excluding teams already in any swiss_format competition.
     */
    private function fillRemainingContinentalSlots(Game $game): void
    {
        $swissCompetitionIds = Competition::where('handler_type', 'swiss_format')
            ->pluck('id')
            ->toArray();

        if (empty($swissCompetitionIds)) {
            return;
        }

        // Collect all teams already in any swiss_format competition
        $usedTeamIds = CompetitionEntry::where('game_id', $game->id)
            ->whereIn('competition_id', $swissCompetitionIds)
            ->pluck('team_id')
            ->toArray();

        foreach ($swissCompetitionIds as $competitionId) {
            $currentCount = CompetitionEntry::where('game_id', $game->id)
                ->where('competition_id', $competitionId)
                ->count();

            $needed = 36 - $currentCount;
            if ($needed <= 0) {
                continue;
            }

            // Find filler teams: have GamePlayer records, not in any swiss competition
            $fillerTeams = GamePlayer::where('game_id', $game->id)
                ->whereNotIn('team_id', $usedTeamIds)
                ->groupBy('team_id')
                ->selectRaw('team_id, AVG(market_value_cents) as avg_value')
                ->orderByDesc('avg_value')
                ->limit($needed)
                ->pluck('team_id')
                ->toArray();

            foreach ($fillerTeams as $teamId) {
                CompetitionEntry::updateOrCreate(
                    [
                        'game_id' => $game->id,
                        'competition_id' => $competitionId,
                        'team_id' => $teamId,
                    ],
                    [
                        'entry_round' => 1,
                    ]
                );

                // Track used teams across competitions
                $usedTeamIds[] = $teamId;
            }
        }
    }
}
