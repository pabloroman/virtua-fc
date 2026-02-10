<?php

namespace App\Game\Processors;

use App\Game\Contracts\SeasonEndProcessor;
use App\Game\DTO\SeasonTransitionData;
use App\Game\Promotions\PromotionRelegationFactory;
use App\Models\CompetitionTeam;
use App\Models\Game;
use App\Models\GameStanding;

/**
 * Handles promotion and relegation between divisions.
 *
 * Uses PromotionRelegationFactory to get the rules for each country/league system.
 * Rules define which positions are relegated/promoted and whether playoffs are involved.
 *
 * Priority: 26 (runs after Supercopa qualification, before fixture generation)
 */
class PromotionRelegationProcessor implements SeasonEndProcessor
{
    public function __construct(
        private PromotionRelegationFactory $ruleFactory,
    ) {}

    public function priority(): int
    {
        return 26;
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        $allPromoted = [];
        $allRelegated = [];

        // Process all configured promotion/relegation rules
        foreach ($this->ruleFactory->all() as $rule) {
            $promoted = $rule->getPromotedTeams($game);
            $relegated = $rule->getRelegatedTeams($game);

            // Skip if no teams to move (e.g., playoffs not complete)
            if (empty($promoted) && empty($relegated)) {
                continue;
            }

            $this->swapTeams(
                promoted: $promoted,
                relegated: $relegated,
                topDivision: $rule->getTopDivision(),
                bottomDivision: $rule->getBottomDivision(),
                gameId: $game->id,
                newSeason: $data->newSeason,
            );

            $allPromoted = array_merge($allPromoted, $promoted);
            $allRelegated = array_merge($allRelegated, $relegated);
        }

        // Store in metadata for display on season end screen
        $data->setMetadata('promotedTeams', $allPromoted);
        $data->setMetadata('relegatedTeams', $allRelegated);

        return $data;
    }

    /**
     * Swap teams between divisions.
     */
    private function swapTeams(
        array $promoted,
        array $relegated,
        string $topDivision,
        string $bottomDivision,
        string $gameId,
        string $newSeason,
    ): void {
        $promotedIds = array_column($promoted, 'teamId');
        $relegatedIds = array_column($relegated, 'teamId');

        // Move relegated teams: top → bottom
        foreach ($relegatedIds as $teamId) {
            $this->moveTeam($teamId, $topDivision, $bottomDivision, $gameId, $newSeason);
        }

        // Move promoted teams: bottom → top
        foreach ($promotedIds as $teamId) {
            $this->moveTeam($teamId, $bottomDivision, $topDivision, $gameId, $newSeason);
        }

        // Re-sort positions in both divisions
        $this->resortPositions($gameId, $topDivision);
        $this->resortPositions($gameId, $bottomDivision);
    }

    /**
     * Move a team from one division to another.
     */
    private function moveTeam(
        string $teamId,
        string $fromDivision,
        string $toDivision,
        string $gameId,
        string $newSeason,
    ): void {
        // Update competition_teams
        CompetitionTeam::where('competition_id', $fromDivision)
            ->where('team_id', $teamId)
            ->where('season', $newSeason)
            ->delete();

        CompetitionTeam::updateOrCreate(
            [
                'competition_id' => $toDivision,
                'team_id' => $teamId,
                'season' => $newSeason,
            ],
            ['entry_round' => 1]
        );

        // Update game_standings
        GameStanding::where('game_id', $gameId)
            ->where('competition_id', $fromDivision)
            ->where('team_id', $teamId)
            ->update([
                'competition_id' => $toDivision,
                'position' => 99, // Will be re-sorted
            ]);

        // Update game's primary competition if the player's team moved
        Game::where('id', $gameId)
            ->where('team_id', $teamId)
            ->update(['competition_id' => $toDivision]);
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
            $standing->update(['position' => $position++]);
        }
    }
}
