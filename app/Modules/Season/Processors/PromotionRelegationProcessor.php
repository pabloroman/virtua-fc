<?php

namespace App\Modules\Season\Processors;

use App\Modules\Season\Contracts\SeasonProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Season\Processors\SeasonSimulationProcessor;
use App\Modules\Competition\Promotions\PromotionRelegationFactory;
use App\Models\Game;
use App\Models\CompetitionEntry;
use App\Models\GameStanding;
use Illuminate\Support\Facades\DB;

/**
 * Handles promotion and relegation between divisions.
 *
 * Uses PromotionRelegationFactory to get the rules for each country/league system.
 * Rules define which positions are relegated/promoted and whether playoffs are involved.
 *
 * Priority: 26 (runs after supercup qualification, before fixture generation)
 */
class PromotionRelegationProcessor implements SeasonProcessor
{
    public function __construct(
        private PromotionRelegationFactory $ruleFactory,
        private SeasonSimulationProcessor $simulationProcessor,
    ) {}

    public function priority(): int
    {
        return 26;
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        return DB::transaction(function () use ($game, $data) {
            $allPromoted = [];
            $allRelegated = [];
            $affectedCompetitionIds = [];

            // Process all configured promotion/relegation rules
            foreach ($this->ruleFactory->all() as $rule) {
                $promoted = $rule->getPromotedTeams($game);
                $relegated = $rule->getRelegatedTeams($game);

                // Skip if no teams to move (e.g., playoffs not complete)
                if (empty($promoted) && empty($relegated)) {
                    continue;
                }

                // Validate balance: promoted count must equal relegated count
                if (count($promoted) !== count($relegated)) {
                    throw new \RuntimeException(
                        "Promotion/relegation imbalance between {$rule->getTopDivision()} and {$rule->getBottomDivision()}: " .
                        count($promoted) . ' promoted vs ' . count($relegated) . ' relegated. ' .
                        'Cannot proceed with unbalanced swap.'
                    );
                }

                $this->swapTeams(
                    promoted: $promoted,
                    relegated: $relegated,
                    topDivision: $rule->getTopDivision(),
                    bottomDivision: $rule->getBottomDivision(),
                    gameId: $game->id,
                );

                $affectedCompetitionIds[] = $rule->getTopDivision();
                $affectedCompetitionIds[] = $rule->getBottomDivision();

                $allPromoted = array_merge($allPromoted, $promoted);
                $allRelegated = array_merge($allRelegated, $relegated);
            }

            // Update competition in transition data if the player's team moved
            $game->refresh();
            if ($game->competition_id !== $data->competitionId) {
                $data->competitionId = $game->competition_id;
            }

            // Re-simulate only the leagues that had roster changes from promotion/relegation
            if (!empty($affectedCompetitionIds)) {
                $this->resimulateAffectedLeagues($game, array_unique($affectedCompetitionIds));
            }

            // Store in metadata for display on season end screen
            $data->setMetadata('promotedTeams', $allPromoted);
            $data->setMetadata('relegatedTeams', $allRelegated);

            return $data;
        });
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
    ): void {
        $promotedIds = array_column($promoted, 'teamId');
        $relegatedIds = array_column($relegated, 'teamId');
        $playerTeamId = Game::where('id', $gameId)->value('team_id');

        // Move relegated teams: top → bottom
        foreach ($relegatedIds as $teamId) {
            $this->moveTeam($teamId, $topDivision, $bottomDivision, $gameId, $playerTeamId);
        }

        // Move promoted teams: bottom → top
        foreach ($promotedIds as $teamId) {
            $this->moveTeam($teamId, $bottomDivision, $topDivision, $gameId, $playerTeamId);
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
        string $playerTeamId,
    ): void {
        // Update competition_entries
        CompetitionEntry::where('game_id', $gameId)
            ->where('competition_id', $fromDivision)
            ->where('team_id', $teamId)
            ->delete();

        CompetitionEntry::updateOrCreate(
            [
                'game_id' => $gameId,
                'competition_id' => $toDivision,
                'team_id' => $teamId,
            ],
            ['entry_round' => 1]
        );

        // Update game_standings.
        // Delete from source (if exists — simulated leagues have no standings).
        GameStanding::where('game_id', $gameId)
            ->where('competition_id', $fromDivision)
            ->where('team_id', $teamId)
            ->delete();

        $targetHasStandings = GameStanding::where('game_id', $gameId)
            ->where('competition_id', $toDivision)
            ->exists();

        $isPlayerTeam = $teamId === $playerTeamId;

        if ($targetHasStandings) {
            // Target division already has real standings — just add this team
            GameStanding::firstOrCreate([
                'game_id' => $gameId,
                'competition_id' => $toDivision,
                'team_id' => $teamId,
            ], [
                'position' => 99, // Will be re-sorted
                'played' => 0,
                'won' => 0,
                'drawn' => 0,
                'lost' => 0,
                'goals_for' => 0,
                'goals_against' => 0,
                'points' => 0,
            ]);
        }

        // Update game's primary competition if the player's team moved
        if ($isPlayerTeam) {
            Game::where('id', $gameId)
                ->where('team_id', $teamId)
                ->update(['competition_id' => $toDivision]);
        }
    }

    /**
     * Re-simulate only the leagues affected by promotion/relegation roster changes.
     *
     * @param  string[]  $competitionIds  Competition IDs that had team swaps
     */
    private function resimulateAffectedLeagues(Game $game, array $competitionIds): void
    {
        $this->simulationProcessor->simulateNonPlayedLeagues($game, $competitionIds, forceResimulate: true);
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

        if ($standings->isEmpty()) {
            return;
        }

        foreach ($standings->values() as $index => $standing) {
            $newPosition = $index + 1;
            if ($standing->position !== $newPosition) {
                $standing->update(['position' => $newPosition]);
            }
        }
    }
}
