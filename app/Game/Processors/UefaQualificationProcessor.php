<?php

namespace App\Game\Processors;

use App\Game\Contracts\SeasonEndProcessor;
use App\Game\DTO\SeasonTransitionData;
use App\Models\Competition;
use App\Models\Game;
use App\Models\GameCompetitionTeam;
use App\Models\GameStanding;

/**
 * Determines which Spanish teams qualify for UEFA competitions
 * based on La Liga final standings.
 *
 * Priority: 105 (runs after SupercopaQualificationProcessor)
 *
 * Qualification rules (simplified):
 * - La Liga 1st-4th → Champions League
 * - La Liga 5th-6th → Europa League
 * - Copa del Rey winner → Conference League (if not already qualified for UCL/UEL)
 */
class UefaQualificationProcessor implements SeasonEndProcessor
{
    private const UCL_POSITIONS = [1, 2, 3, 4];
    private const UEL_POSITIONS = [5, 6];

    public function priority(): int
    {
        return 105;
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        // Get La Liga final standings
        $standings = GameStanding::where('game_id', $game->id)
            ->where('competition_id', 'ESP1')
            ->orderBy('position')
            ->pluck('team_id', 'position')
            ->toArray();

        if (empty($standings)) {
            return $data;
        }

        $newSeason = $data->newSeason;

        // Qualify Spanish teams for UCL
        $this->updateSpanishQualifiers($game->id, 'UCL', self::UCL_POSITIONS, $standings);

        // Qualify Spanish teams for UEL
        $this->updateSpanishQualifiers($game->id, 'UEL', self::UEL_POSITIONS, $standings);

        return $data;
    }

    /**
     * Update Spanish qualifiers for a UEFA competition.
     * Removes old Spanish teams and adds new qualifiers.
     */
    private function updateSpanishQualifiers(
        string $gameId,
        string $competitionId,
        array $qualifyingPositions,
        array $standings,
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

        // Remove old Spanish teams from this competition for this game
        $spanishTeamIds = \App\Models\Team::where('country', 'ES')->pluck('id')->toArray();
        GameCompetitionTeam::where('game_id', $gameId)
            ->where('competition_id', $competitionId)
            ->whereIn('team_id', $spanishTeamIds)
            ->delete();

        // Add new qualifiers
        foreach ($newQualifiers as $teamId) {
            GameCompetitionTeam::updateOrCreate(
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
