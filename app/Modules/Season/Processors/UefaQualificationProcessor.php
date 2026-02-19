<?php

namespace App\Modules\Season\Processors;

use App\Modules\Season\Contracts\SeasonEndProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Competition\Services\CountryConfig;
use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameStanding;
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
            $leagueStandings = GameStanding::where('game_id', $game->id)
                ->where('competition_id', $leagueId)
                ->orderBy('position')
                ->pluck('team_id', 'position')
                ->toArray();

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
        array $cupWinnerConfig,
        array &$qualifications,
        array $standings,
        array $slots,
    ): void {
        $cupWinnerId = $this->getCupWinner($gameId, $cupWinnerConfig['cup']);
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
     * Find the Copa del Rey winner from the final cup tie.
     */
    private function getCupWinner(string $gameId, string $cupId): ?string
    {
        $supercupConfig = config("countries.ES.supercup");
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
}
