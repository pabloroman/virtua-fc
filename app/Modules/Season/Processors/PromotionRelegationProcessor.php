<?php

namespace App\Modules\Season\Processors;

use App\Modules\Season\Contracts\SeasonEndProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Competition\Promotions\PromotionRelegationFactory;
use App\Modules\Finance\Services\SeasonSimulationService;
use App\Models\Competition;
use App\Models\Game;
use App\Models\CompetitionEntry;
use App\Models\GameStanding;

/**
 * Handles promotion and relegation between divisions.
 *
 * Uses PromotionRelegationFactory to get the rules for each country/league system.
 * Rules define which positions are relegated/promoted and whether playoffs are involved.
 *
 * Priority: 26 (runs after supercup qualification, before fixture generation)
 */
class PromotionRelegationProcessor implements SeasonEndProcessor
{
    public function __construct(
        private PromotionRelegationFactory $ruleFactory,
        private SeasonSimulationService $simulationService,
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

        // Update competition in transition data if the player's team moved
        $game->refresh();
        if ($game->competition_id !== $data->competitionId) {
            $data->competitionId = $game->competition_id;
        }

        // Re-simulate non-played leagues with the post-promotion roster
        // so SimulatedSeason records are accurate for the rest of the season.
        if (!empty($allPromoted) || !empty($allRelegated)) {
            $this->resimulateNonPlayedLeagues($game);
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

        $isPlayerTeam = Game::where('id', $gameId)
            ->where('team_id', $teamId)
            ->exists();

        if ($targetHasStandings) {
            // Target division already has real standings — just add this team
            GameStanding::create([
                'game_id' => $gameId,
                'competition_id' => $toDivision,
                'team_id' => $teamId,
                'position' => 99, // Will be re-sorted
                'played' => 0,
                'won' => 0,
                'drawn' => 0,
                'lost' => 0,
                'goals_for' => 0,
                'goals_against' => 0,
                'points' => 0,
            ]);
        } elseif ($isPlayerTeam) {
            // Player's team moving to a previously-simulated division.
            // Bootstrap standings for ALL teams so the league becomes playable.
            $this->bootstrapDivisionStandings($gameId, $toDivision);
        }

        // Update game's primary competition if the player's team moved
        if ($isPlayerTeam) {
            Game::where('id', $gameId)
                ->where('team_id', $teamId)
                ->update(['competition_id' => $toDivision]);
        }
    }

    /**
     * Create standings for all teams in a division that previously had none.
     * Called when the player's team moves to a simulated division, making it playable.
     */
    private function bootstrapDivisionStandings(string $gameId, string $competitionId): void
    {
        $teamIds = CompetitionEntry::where('game_id', $gameId)
            ->where('competition_id', $competitionId)
            ->pluck('team_id')
            ->toArray();

        $position = 1;
        foreach ($teamIds as $teamId) {
            GameStanding::create([
                'game_id' => $gameId,
                'competition_id' => $competitionId,
                'team_id' => $teamId,
                'position' => $position++,
                'played' => 0,
                'won' => 0,
                'drawn' => 0,
                'lost' => 0,
                'goals_for' => 0,
                'goals_against' => 0,
                'points' => 0,
            ]);
        }
    }

    /**
     * Re-simulate non-played leagues after roster changes.
     */
    private function resimulateNonPlayedLeagues(Game $game): void
    {
        $userCompetition = Competition::find($game->competition_id);
        if (!$userCompetition) {
            return;
        }

        $leagues = Competition::where('country', $userCompetition->country)
            ->where('role', Competition::ROLE_PRIMARY)
            ->where('id', '!=', $userCompetition->id)
            ->get();

        foreach ($leagues as $league) {
            $hasRealStandings = GameStanding::where('game_id', $game->id)
                ->where('competition_id', $league->id)
                ->where('played', '>', 0)
                ->exists();

            if (!$hasRealStandings) {
                $this->simulationService->simulateLeague($game, $league);
            }
        }
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
