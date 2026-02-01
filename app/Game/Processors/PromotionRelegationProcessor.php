<?php

namespace App\Game\Processors;

use App\Game\Contracts\SeasonEndProcessor;
use App\Game\DTO\SeasonTransitionData;
use App\Models\CompetitionTeam;
use App\Models\Game;
use App\Models\GameStanding;

/**
 * Handles promotion and relegation between La Liga and La Liga 2.
 *
 * Rules:
 * - Bottom 3 of La Liga (ESP1) are relegated to La Liga 2 (ESP2)
 * - Top 3 of La Liga 2 (ESP2) are promoted to La Liga (ESP1)
 *
 * Note: Real Spanish football has playoffs for 3rd-6th in La Liga 2,
 * but we simplify to direct promotion for top 3.
 *
 * Priority: 26 (runs after Supercopa qualification, before fixture generation)
 */
class PromotionRelegationProcessor implements SeasonEndProcessor
{
    private const PRIMERA_DIVISION = 'ESP1';
    private const SEGUNDA_DIVISION = 'ESP2';
    private const RELEGATED_POSITIONS = [18, 19, 20]; // Bottom 3
    private const PROMOTED_POSITIONS = [1, 2, 3];     // Top 3

    public function priority(): int
    {
        return 26;
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        // Get teams to be relegated from La Liga
        $relegatedTeams = $this->getTeamsByPosition(
            $game->id,
            self::PRIMERA_DIVISION,
            self::RELEGATED_POSITIONS
        );

        // Get teams to be promoted from La Liga 2
        $promotedTeams = $this->getTeamsByPosition(
            $game->id,
            self::SEGUNDA_DIVISION,
            self::PROMOTED_POSITIONS
        );

        // Update competition_teams for the new season
        $this->updateCompetitionTeams($relegatedTeams, $promotedTeams, $data->newSeason);

        // Update standings for new season
        $this->updateStandings($game->id, $relegatedTeams, $promotedTeams);

        // Store in metadata for display
        $data->setMetadata('relegatedTeams', $relegatedTeams);
        $data->setMetadata('promotedTeams', $promotedTeams);

        return $data;
    }

    /**
     * Get team IDs at specific positions in a competition.
     *
     * @return array<array{teamId: string, position: int, teamName: string}>
     */
    private function getTeamsByPosition(string $gameId, string $competitionId, array $positions): array
    {
        return GameStanding::where('game_id', $gameId)
            ->where('competition_id', $competitionId)
            ->whereIn('position', $positions)
            ->with('team')
            ->get()
            ->map(fn ($standing) => [
                'teamId' => $standing->team_id,
                'position' => $standing->position,
                'teamName' => $standing->team->name ?? 'Unknown',
            ])
            ->toArray();
    }

    /**
     * Update competition_teams for the new season.
     * Relegated teams move to Segunda, promoted teams move to Primera.
     */
    private function updateCompetitionTeams(array $relegatedTeams, array $promotedTeams, string $newSeason): void
    {
        $relegatedIds = array_column($relegatedTeams, 'teamId');
        $promotedIds = array_column($promotedTeams, 'teamId');

        // Move relegated teams: ESP1 -> ESP2
        foreach ($relegatedIds as $teamId) {
            // Remove from Primera
            CompetitionTeam::where('competition_id', self::PRIMERA_DIVISION)
                ->where('team_id', $teamId)
                ->where('season', $newSeason)
                ->delete();

            // Add to Segunda
            CompetitionTeam::updateOrCreate(
                [
                    'competition_id' => self::SEGUNDA_DIVISION,
                    'team_id' => $teamId,
                    'season' => $newSeason,
                ],
                ['entry_round' => 1]
            );
        }

        // Move promoted teams: ESP2 -> ESP1
        foreach ($promotedIds as $teamId) {
            // Remove from Segunda
            CompetitionTeam::where('competition_id', self::SEGUNDA_DIVISION)
                ->where('team_id', $teamId)
                ->where('season', $newSeason)
                ->delete();

            // Add to Primera
            CompetitionTeam::updateOrCreate(
                [
                    'competition_id' => self::PRIMERA_DIVISION,
                    'team_id' => $teamId,
                    'season' => $newSeason,
                ],
                ['entry_round' => 1]
            );
        }
    }

    /**
     * Update standings: remove old entries and create new ones in correct divisions.
     */
    private function updateStandings(string $gameId, array $relegatedTeams, array $promotedTeams): void
    {
        $relegatedIds = array_column($relegatedTeams, 'teamId');
        $promotedIds = array_column($promotedTeams, 'teamId');

        // Move relegated teams' standings to Segunda
        foreach ($relegatedIds as $teamId) {
            // Update competition_id from ESP1 to ESP2
            GameStanding::where('game_id', $gameId)
                ->where('competition_id', self::PRIMERA_DIVISION)
                ->where('team_id', $teamId)
                ->update([
                    'competition_id' => self::SEGUNDA_DIVISION,
                    'position' => 22, // Will be re-sorted
                ]);
        }

        // Move promoted teams' standings to Primera
        foreach ($promotedIds as $teamId) {
            // Update competition_id from ESP2 to ESP1
            GameStanding::where('game_id', $gameId)
                ->where('competition_id', self::SEGUNDA_DIVISION)
                ->where('team_id', $teamId)
                ->update([
                    'competition_id' => self::PRIMERA_DIVISION,
                    'position' => 20, // Will be re-sorted
                ]);
        }

        // Re-sort positions in both divisions
        $this->resortPositions($gameId, self::PRIMERA_DIVISION);
        $this->resortPositions($gameId, self::SEGUNDA_DIVISION);
    }

    /**
     * Re-sort positions in a competition (1, 2, 3, ...).
     */
    private function resortPositions(string $gameId, string $competitionId): void
    {
        $standings = GameStanding::where('game_id', $gameId)
            ->where('competition_id', $competitionId)
            ->orderBy('position')
            ->get();

        $position = 1;
        foreach ($standings as $standing) {
            $standing->update(['position' => $position]);
            $position++;
        }
    }
}
