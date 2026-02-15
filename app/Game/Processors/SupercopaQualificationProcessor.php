<?php

namespace App\Game\Processors;

use App\Game\Contracts\SeasonEndProcessor;
use App\Game\DTO\SeasonTransitionData;
use App\Game\Services\CountryConfig;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\CompetitionEntry;
use App\Models\GameStanding;

/**
 * Determines supercopa qualifiers for the next season,
 * driven by country config.
 *
 * Qualification rules (per country's supercopa config):
 * - The two domestic cup finalists
 * - League champion and runner-up
 * - If there's overlap, the next highest league team qualifies
 *
 * Priority: 25 (runs after stats reset but before fixture generation)
 */
class SupercopaQualificationProcessor implements SeasonEndProcessor
{
    public function __construct(
        private CountryConfig $countryConfig,
    ) {}

    public function priority(): int
    {
        return 25;
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        foreach ($this->countryConfig->allCountryCodes() as $countryCode) {
            $supercopaConfig = $this->countryConfig->supercopa($countryCode);
            if (!$supercopaConfig) {
                continue;
            }

            $this->processCountrySupercopa($game, $data, $supercopaConfig);
        }

        return $data;
    }

    private function processCountrySupercopa(Game $game, SeasonTransitionData $data, array $config): void
    {
        $cupId = $config['cup'];
        $leagueId = $config['league'];
        $supercopaId = $config['competition'];
        $cupFinalRound = $config['cup_final_round'];

        // Get cup finalists
        $cupFinalists = $this->getCupFinalists($game->id, $cupId, $cupFinalRound);

        // Get league top teams (enough to handle overlaps)
        $leagueTopTeams = $this->getLeagueTopTeams($game->id, $leagueId, 4);

        // Determine the 4 Supercopa qualifiers
        $qualifiers = $this->determineQualifiers($cupFinalists, $leagueTopTeams);

        // Update supercopa competition_entries for this game
        $this->updateSupercopaTeams($game->id, $supercopaId, $qualifiers);

        // Store qualifiers in metadata for display
        $data->setMetadata('supercopaQualifiers', $qualifiers);
    }

    /**
     * Get the two cup finalists.
     *
     * @return array{winner: string|null, runnerUp: string|null}
     */
    private function getCupFinalists(string $gameId, string $cupId, int $finalRound): array
    {
        $finalTie = CupTie::where('game_id', $gameId)
            ->where('competition_id', $cupId)
            ->where('round_number', $finalRound)
            ->where('completed', true)
            ->first();

        if (!$finalTie) {
            return ['winner' => null, 'runnerUp' => null];
        }

        return [
            'winner' => $finalTie->winner_id,
            'runnerUp' => $finalTie->getLoserId(),
        ];
    }

    /**
     * Get the top N teams from league standings.
     *
     * @return array<int, string> Team IDs in order of position
     */
    private function getLeagueTopTeams(string $gameId, string $leagueId, int $count): array
    {
        return GameStanding::where('game_id', $gameId)
            ->where('competition_id', $leagueId)
            ->orderBy('position')
            ->limit($count)
            ->pluck('team_id')
            ->toArray();
    }

    /**
     * Determine the 4 Supercopa qualifiers, handling overlaps.
     *
     * @return array<string> 4 team IDs
     */
    private function determineQualifiers(array $cupFinalists, array $leagueTopTeams): array
    {
        $qualifiers = [];
        $usedTeams = [];

        // Add cup finalists first (if available)
        if ($cupFinalists['winner']) {
            $qualifiers[] = $cupFinalists['winner'];
            $usedTeams[$cupFinalists['winner']] = true;
        }
        if ($cupFinalists['runnerUp']) {
            $qualifiers[] = $cupFinalists['runnerUp'];
            $usedTeams[$cupFinalists['runnerUp']] = true;
        }

        // Add league teams until we have 4 qualifiers
        foreach ($leagueTopTeams as $teamId) {
            if (count($qualifiers) >= 4) {
                break;
            }

            if (isset($usedTeams[$teamId])) {
                continue;
            }

            $qualifiers[] = $teamId;
            $usedTeams[$teamId] = true;
        }

        return $qualifiers;
    }

    /**
     * Update supercopa competition_entries for this game.
     */
    private function updateSupercopaTeams(string $gameId, string $supercopaId, array $teamIds): void
    {
        // Remove old entries
        CompetitionEntry::where('game_id', $gameId)
            ->where('competition_id', $supercopaId)
            ->delete();

        // Insert new qualifiers
        foreach ($teamIds as $teamId) {
            CompetitionEntry::create([
                'game_id' => $gameId,
                'competition_id' => $supercopaId,
                'team_id' => $teamId,
                'entry_round' => 1,
            ]);
        }
    }
}
