<?php

namespace App\Game\Services;

use App\Models\GameStanding;
use Illuminate\Support\Facades\DB;

class StandingsCalculator
{
    /**
     * Update standings after a match result.
     */
    public function updateAfterMatch(
        string $gameId,
        string $competitionId,
        string $homeTeamId,
        string $awayTeamId,
        int $homeScore,
        int $awayScore,
    ): void {
        // Determine match outcome
        $homeWin = $homeScore > $awayScore;
        $awayWin = $awayScore > $homeScore;
        $draw = $homeScore === $awayScore;

        // Update home team standing
        $this->updateTeamStanding(
            gameId: $gameId,
            competitionId: $competitionId,
            teamId: $homeTeamId,
            goalsFor: $homeScore,
            goalsAgainst: $awayScore,
            won: $homeWin,
            drawn: $draw,
            lost: $awayWin,
        );

        // Update away team standing
        $this->updateTeamStanding(
            gameId: $gameId,
            competitionId: $competitionId,
            teamId: $awayTeamId,
            goalsFor: $awayScore,
            goalsAgainst: $homeScore,
            won: $awayWin,
            drawn: $draw,
            lost: $homeWin,
        );

        // Recalculate positions for all teams in competition
        $this->recalculatePositions($gameId, $competitionId);
    }

    /**
     * Update a single team's standing record.
     */
    private function updateTeamStanding(
        string $gameId,
        string $competitionId,
        string $teamId,
        int $goalsFor,
        int $goalsAgainst,
        bool $won,
        bool $drawn,
        bool $lost,
    ): void {
        $points = $won ? 3 : ($drawn ? 1 : 0);

        GameStanding::where('game_id', $gameId)
            ->where('competition_id', $competitionId)
            ->where('team_id', $teamId)
            ->update([
                'played' => DB::raw('played + 1'),
                'won' => DB::raw('won + ' . ($won ? 1 : 0)),
                'drawn' => DB::raw('drawn + ' . ($drawn ? 1 : 0)),
                'lost' => DB::raw('lost + ' . ($lost ? 1 : 0)),
                'goals_for' => DB::raw('goals_for + ' . $goalsFor),
                'goals_against' => DB::raw('goals_against + ' . $goalsAgainst),
                'points' => DB::raw('points + ' . $points),
            ]);
    }

    /**
     * Recalculate positions for all teams in a competition.
     * Order by: points DESC, goal difference DESC, goals for DESC
     */
    public function recalculatePositions(string $gameId, string $competitionId): void
    {
        // Get all standings ordered by ranking criteria
        $standings = GameStanding::where('game_id', $gameId)
            ->where('competition_id', $competitionId)
            ->orderByDesc('points')
            ->orderByRaw('(goals_for - goals_against) DESC')
            ->orderByDesc('goals_for')
            ->get();

        // Update positions
        $position = 1;
        foreach ($standings as $standing) {
            $standing->update([
                'prev_position' => $standing->position ?: $position,
                'position' => $position,
            ]);
            $position++;
        }
    }

    /**
     * Initialize standings for all teams in a competition.
     */
    public function initializeStandings(string $gameId, string $competitionId, array $teamIds): void
    {
        $position = 1;
        foreach ($teamIds as $teamId) {
            GameStanding::create([
                'game_id' => $gameId,
                'competition_id' => $competitionId,
                'team_id' => $teamId,
                'position' => $position,
                'prev_position' => null,
                'played' => 0,
                'won' => 0,
                'drawn' => 0,
                'lost' => 0,
                'goals_for' => 0,
                'goals_against' => 0,
                'points' => 0,
            ]);
            $position++;
        }
    }
}
