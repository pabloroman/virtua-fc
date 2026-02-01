<?php

namespace App\Game\Processors;

use App\Game\Contracts\SeasonEndProcessor;
use App\Game\DTO\SeasonTransitionData;
use App\Models\CompetitionTeam;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameStanding;

/**
 * Determines Supercopa de España qualifiers for the next season.
 *
 * Qualification rules:
 * - The two Copa del Rey finalists
 * - La Liga champion and runner-up
 * - If there's overlap (e.g., La Liga winner also in Copa final),
 *   the next highest La Liga team qualifies
 *
 * Priority: 35 (runs after standings are finalized but before reset)
 */
class SupercopaQualificationProcessor implements SeasonEndProcessor
{
    private const COPA_COMPETITION_ID = 'ESPCUP';
    private const LIGA_COMPETITION_ID = 'ESP1';
    private const SUPERCOPA_COMPETITION_ID = 'ESPSUP';
    private const COPA_FINAL_ROUND = 7;

    public function priority(): int
    {
        return 35;
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        // Get Copa del Rey finalists
        $copaFinalists = $this->getCopaFinalists($game->id);

        // Get La Liga top teams (enough to handle overlaps)
        $ligaTopTeams = $this->getLigaTopTeams($game->id, 4);

        // Determine the 4 Supercopa qualifiers
        $qualifiers = $this->determineQualifiers($copaFinalists, $ligaTopTeams);

        // Update Supercopa competition_teams for the new season
        $this->updateSupercopaTeams($qualifiers, $data->newSeason);

        // Store qualifiers in metadata for display
        $data->setMetadata('supercopaQualifiers', $qualifiers);

        return $data;
    }

    /**
     * Get the two Copa del Rey finalists.
     *
     * @return array{winner: string|null, runnerUp: string|null}
     */
    private function getCopaFinalists(string $gameId): array
    {
        $finalTie = CupTie::where('game_id', $gameId)
            ->where('competition_id', self::COPA_COMPETITION_ID)
            ->where('round_number', self::COPA_FINAL_ROUND)
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
     * Get the top N teams from La Liga standings.
     *
     * @return array<int, string> Team IDs in order of position
     */
    private function getLigaTopTeams(string $gameId, int $count): array
    {
        return GameStanding::where('game_id', $gameId)
            ->where('competition_id', self::LIGA_COMPETITION_ID)
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
    private function determineQualifiers(array $copaFinalists, array $ligaTopTeams): array
    {
        $qualifiers = [];
        $usedTeams = [];

        // Add Copa finalists first (if available)
        if ($copaFinalists['winner']) {
            $qualifiers[] = $copaFinalists['winner'];
            $usedTeams[$copaFinalists['winner']] = true;
        }
        if ($copaFinalists['runnerUp']) {
            $qualifiers[] = $copaFinalists['runnerUp'];
            $usedTeams[$copaFinalists['runnerUp']] = true;
        }

        // Add La Liga teams until we have 4 qualifiers
        foreach ($ligaTopTeams as $teamId) {
            if (count($qualifiers) >= 4) {
                break;
            }

            // Skip if team already qualified via Copa
            if (isset($usedTeams[$teamId])) {
                continue;
            }

            $qualifiers[] = $teamId;
            $usedTeams[$teamId] = true;
        }

        return $qualifiers;
    }

    /**
     * Update ESPSUP competition_teams for the new season.
     */
    private function updateSupercopaTeams(array $teamIds, string $newSeason): void
    {
        // Remove old season entries
        CompetitionTeam::where('competition_id', self::SUPERCOPA_COMPETITION_ID)
            ->where('season', $newSeason)
            ->delete();

        // Insert new qualifiers
        foreach ($teamIds as $teamId) {
            CompetitionTeam::create([
                'competition_id' => self::SUPERCOPA_COMPETITION_ID,
                'team_id' => $teamId,
                'season' => $newSeason,
                'entry_round' => 1,
            ]);
        }
    }
}
