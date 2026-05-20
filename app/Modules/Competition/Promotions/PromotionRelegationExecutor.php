<?php

namespace App\Modules\Competition\Promotions;

use App\Models\CompetitionEntry;
use App\Models\Game;
use App\Models\GameStanding;
use App\Models\SimulatedSeason;
use App\Modules\Season\Processors\SeasonSimulationProcessor;
use Illuminate\Support\Facades\DB;

/**
 * Applies a {@see PromotionRelegationPlan} to the database in a single
 * transaction.
 *
 * For each move: delete the team's CompetitionEntry row in the source,
 * insert one in the destination, delete its GameStanding in the source,
 * and insert a placeholder GameStanding in the destination (position 99,
 * zeroed stats — the destination either has real standings from the played
 * season or a previously regenerated simulated set; either way we just
 * append).
 *
 * After all moves: re-sort positions in every touched competition, regenerate
 * SimulatedSeason rows for non-played competitions (so they reflect the new
 * roster rather than the pre-swap one), and bump Game.competition_id if the
 * user's team moved.
 *
 * The executor trusts the plan it receives. All correctness invariants
 * (tier counts, no-coexistence, no-double-move) are checked by the planner
 * before the plan is handed over.
 */
class PromotionRelegationExecutor
{
    public function __construct(
        private readonly SeasonSimulationProcessor $simulationProcessor,
    ) {}

    public function apply(PromotionRelegationPlan $plan, Game $game): void
    {
        if ($plan->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($plan, $game) {
            foreach ($plan->moves as $move) {
                $this->moveTeam(
                    gameId: $game->id,
                    teamId: $move->teamId,
                    fromComp: $move->fromCompetitionId,
                    toComp: $move->toCompetitionId,
                    userTeamId: $game->team_id,
                );
            }

            foreach ($plan->touchedCompetitionIds as $competitionId) {
                $this->resortPositions($game->id, $competitionId);
            }

            // Game model may now have a stale competition_id cache if the user
            // team moved — refresh before downstream re-simulation calls.
            $game->refresh();
        });

        if (!empty($plan->touchedCompetitionIds)) {
            // Forces a fresh simulation for non-played leagues that lost/gained
            // a team. Reuses the existing SeasonSimulationProcessor so the
            // logic for choosing which competitions count as "simulated" stays
            // in one place. Outside the transaction because simulation writes
            // a new SimulatedSeason row per league and we don't want to hold
            // the transaction open for that.
            $this->simulationProcessor->simulateNonPlayedLeagues(
                $game,
                $plan->touchedCompetitionIds,
                forceResimulate: true,
            );
        }
    }

    /**
     * Move a single team between two competitions. Mirrors the previous
     * processor's moveTeam() exactly so the on-disk state shape is unchanged
     * and downstream processors / views keep working.
     */
    private function moveTeam(
        string $gameId,
        string $teamId,
        string $fromComp,
        string $toComp,
        ?string $userTeamId,
    ): void {
        CompetitionEntry::where('game_id', $gameId)
            ->where('competition_id', $fromComp)
            ->where('team_id', $teamId)
            ->delete();

        CompetitionEntry::updateOrCreate(
            [
                'game_id' => $gameId,
                'competition_id' => $toComp,
                'team_id' => $teamId,
            ],
            ['entry_round' => 1],
        );

        GameStanding::where('game_id', $gameId)
            ->where('competition_id', $fromComp)
            ->where('team_id', $teamId)
            ->delete();

        $targetHasStandings = GameStanding::where('game_id', $gameId)
            ->where('competition_id', $toComp)
            ->exists();

        if ($targetHasStandings) {
            GameStanding::firstOrCreate(
                [
                    'game_id' => $gameId,
                    'competition_id' => $toComp,
                    'team_id' => $teamId,
                ],
                [
                    'position' => 99,
                    'played' => 0,
                    'won' => 0,
                    'drawn' => 0,
                    'lost' => 0,
                    'goals_for' => 0,
                    'goals_against' => 0,
                    'points' => 0,
                ],
            );
        }

        if ($userTeamId !== null && $teamId === $userTeamId) {
            Game::where('id', $gameId)
                ->where('team_id', $teamId)
                ->update(['competition_id' => $toComp]);
        }

        // Refresh the SimulatedSeason row for the source competition so its
        // results array doesn't drift from the new entry list. The
        // SeasonSimulationProcessor re-simulation step below replaces it
        // entirely; this minimal in-place edit covers the brief window between
        // the move and the re-simulation if anything reads from it.
        $this->dropFromSimulatedSeason($gameId, $fromComp, $teamId);
    }

    private function dropFromSimulatedSeason(string $gameId, string $competitionId, string $teamId): void
    {
        $sim = SimulatedSeason::where('game_id', $gameId)
            ->where('competition_id', $competitionId)
            ->first();

        if (!$sim || !is_array($sim->results)) {
            return;
        }

        $filtered = array_values(array_filter(
            $sim->results,
            fn ($id) => $id !== $teamId,
        ));

        if (count($filtered) !== count($sim->results)) {
            $sim->results = $filtered;
            $sim->save();
        }
    }

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
