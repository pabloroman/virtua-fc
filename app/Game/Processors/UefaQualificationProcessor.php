<?php

namespace App\Game\Processors;

use App\Game\Contracts\SeasonEndProcessor;
use App\Game\DTO\SeasonTransitionData;
use App\Game\Services\CountryConfig;
use App\Models\Competition;
use App\Models\Game;
use App\Models\CompetitionEntry;
use App\Models\GameStanding;

/**
 * Determines which teams qualify for UEFA competitions
 * based on league final standings, driven by country config.
 *
 * Priority: 105 (runs after SupercupQualificationProcessor)
 *
 * Qualification slots are defined in config/countries.php under
 * each country's 'continental_slots' key.
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
            $slots = $this->countryConfig->continentalSlots($countryCode);

            foreach ($slots as $leagueId => $continentalAllocations) {
                $standings = GameStanding::where('game_id', $game->id)
                    ->where('competition_id', $leagueId)
                    ->orderBy('position')
                    ->pluck('team_id', 'position')
                    ->toArray();

                if (empty($standings)) {
                    continue;
                }

                foreach ($continentalAllocations as $continentalId => $positions) {
                    $this->updateQualifiers(
                        $game->id,
                        $continentalId,
                        $positions,
                        $standings,
                        $countryCode,
                    );
                }
            }
        }

        return $data;
    }

    /**
     * Update qualifiers for a UEFA competition.
     * Removes old teams from this country and adds new qualifiers.
     */
    private function updateQualifiers(
        string $gameId,
        string $competitionId,
        array $qualifyingPositions,
        array $standings,
        string $countryCode,
    ): void {
        $competition = Competition::find($competitionId);
        if (!$competition) {
            return;
        }

        // Get new qualifying team IDs
        $newQualifiers = [];
        foreach ($qualifyingPositions as $position) {
            if (isset($standings[$position])) {
                $newQualifiers[] = $standings[$position];
            }
        }

        if (empty($newQualifiers)) {
            return;
        }

        // Remove old teams from this country from the competition
        $countryTeamIds = \App\Models\Team::where('country', $countryCode)->pluck('id')->toArray();
        CompetitionEntry::where('game_id', $gameId)
            ->where('competition_id', $competitionId)
            ->whereIn('team_id', $countryTeamIds)
            ->delete();

        // Add new qualifiers
        foreach ($newQualifiers as $teamId) {
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
