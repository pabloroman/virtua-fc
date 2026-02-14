<?php

namespace App\Game\Services;

use App\Models\GameStanding;

class StandingsCalculator
{
    /**
     * Update standings after a match result.
     * Note: Call recalculatePositions() separately after processing all matches for a matchday.
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
        $standing = GameStanding::where('game_id', $gameId)
            ->where('competition_id', $competitionId)
            ->where('team_id', $teamId)
            ->first();

        if (! $standing) {
            return;
        }

        $standing->played += 1;
        $standing->won += $won ? 1 : 0;
        $standing->drawn += $drawn ? 1 : 0;
        $standing->lost += $lost ? 1 : 0;
        $standing->goals_for += $goalsFor;
        $standing->goals_against += $goalsAgainst;
        $standing->points += $won ? 3 : ($drawn ? 1 : 0);
        $standing->save();
    }

    /**
     * Reverse a previously recorded match result from standings.
     * Used when a substitution changes the match outcome.
     */
    public function reverseMatchResult(
        string $gameId,
        string $competitionId,
        string $homeTeamId,
        string $awayTeamId,
        int $homeScore,
        int $awayScore,
    ): void {
        $homeWin = $homeScore > $awayScore;
        $awayWin = $awayScore > $homeScore;
        $draw = $homeScore === $awayScore;

        // Reverse home team standing
        $this->reverseTeamStanding(
            gameId: $gameId,
            competitionId: $competitionId,
            teamId: $homeTeamId,
            goalsFor: $homeScore,
            goalsAgainst: $awayScore,
            won: $homeWin,
            drawn: $draw,
            lost: $awayWin,
        );

        // Reverse away team standing
        $this->reverseTeamStanding(
            gameId: $gameId,
            competitionId: $competitionId,
            teamId: $awayTeamId,
            goalsFor: $awayScore,
            goalsAgainst: $homeScore,
            won: $awayWin,
            drawn: $draw,
            lost: $homeWin,
        );
    }

    /**
     * Reverse a single team's standing record (undo a match result).
     */
    private function reverseTeamStanding(
        string $gameId,
        string $competitionId,
        string $teamId,
        int $goalsFor,
        int $goalsAgainst,
        bool $won,
        bool $drawn,
        bool $lost,
    ): void {
        $standing = GameStanding::where('game_id', $gameId)
            ->where('competition_id', $competitionId)
            ->where('team_id', $teamId)
            ->first();

        if (! $standing) {
            return;
        }

        $standing->played -= 1;
        $standing->won -= $won ? 1 : 0;
        $standing->drawn -= $drawn ? 1 : 0;
        $standing->lost -= $lost ? 1 : 0;
        $standing->goals_for -= $goalsFor;
        $standing->goals_against -= $goalsAgainst;
        $standing->points -= $won ? 3 : ($drawn ? 1 : 0);
        $standing->save();
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
        $rows = [];
        $position = 1;
        foreach ($teamIds as $teamId) {
            $rows[] = [
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
            ];
            $position++;
        }

        foreach (array_chunk($rows, 100) as $chunk) {
            GameStanding::insert($chunk);
        }
    }
}
