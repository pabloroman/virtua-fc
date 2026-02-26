<?php

namespace App\Modules\Competition\Services;

use App\Models\GameStanding;
use Illuminate\Support\Facades\DB;

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
        $this->bulkUpdateAfterMatches($gameId, $competitionId, [[
            'homeTeamId' => $homeTeamId,
            'awayTeamId' => $awayTeamId,
            'homeScore' => $homeScore,
            'awayScore' => $awayScore,
        ]]);
    }

    /**
     * Update standings for multiple matches in a single query using CASE WHEN.
     *
     * @param  array<array{homeTeamId: string, awayTeamId: string, homeScore: int, awayScore: int}>  $matchResults
     */
    public function bulkUpdateAfterMatches(string $gameId, string $competitionId, array $matchResults): void
    {
        if (empty($matchResults)) {
            return;
        }

        // Aggregate increments per team across all matches
        $increments = [];

        foreach ($matchResults as $result) {
            $homeWin = $result['homeScore'] > $result['awayScore'];
            $awayWin = $result['awayScore'] > $result['homeScore'];
            $draw = $result['homeScore'] === $result['awayScore'];

            foreach ([
                ['teamId' => $result['homeTeamId'], 'gf' => $result['homeScore'], 'ga' => $result['awayScore'], 'won' => $homeWin, 'drawn' => $draw, 'lost' => $awayWin],
                ['teamId' => $result['awayTeamId'], 'gf' => $result['awayScore'], 'ga' => $result['homeScore'], 'won' => $awayWin, 'drawn' => $draw, 'lost' => $homeWin],
            ] as $team) {
                $id = $team['teamId'];
                if (! isset($increments[$id])) {
                    $increments[$id] = ['played' => 0, 'won' => 0, 'drawn' => 0, 'lost' => 0, 'goals_for' => 0, 'goals_against' => 0, 'points' => 0];
                }
                $increments[$id]['played'] += 1;
                $increments[$id]['won'] += $team['won'] ? 1 : 0;
                $increments[$id]['drawn'] += $team['drawn'] ? 1 : 0;
                $increments[$id]['lost'] += $team['lost'] ? 1 : 0;
                $increments[$id]['goals_for'] += $team['gf'];
                $increments[$id]['goals_against'] += $team['ga'];
                $increments[$id]['points'] += $team['won'] ? 3 : ($team['drawn'] ? 1 : 0);
            }
        }

        $teamIds = array_keys($increments);
        $standingIds = GameStanding::where('game_id', $gameId)
            ->where('competition_id', $competitionId)
            ->whereIn('team_id', $teamIds)
            ->pluck('id', 'team_id');

        if ($standingIds->isEmpty()) {
            return;
        }

        $ids = $standingIds->values()->toArray();
        $idList = "'" . implode("','", $ids) . "'";
        $columns = ['played', 'won', 'drawn', 'lost', 'goals_for', 'goals_against', 'points'];
        $setClauses = [];

        foreach ($columns as $column) {
            $cases = [];
            foreach ($standingIds as $teamId => $standingId) {
                $amount = $increments[$teamId][$column] ?? 0;
                if ($amount !== 0) {
                    $cases[] = "WHEN id = '{$standingId}' THEN {$column} + {$amount}";
                }
            }
            if (! empty($cases)) {
                $setClauses[] = "{$column} = CASE " . implode(' ', $cases) . " ELSE {$column} END";
            }
        }

        if (! empty($setClauses)) {
            DB::statement('UPDATE game_standings SET ' . implode(', ', $setClauses) . " WHERE id IN ({$idList})");
        }
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
     * Uses a single bulk UPDATE with CASE WHEN instead of per-row updates.
     *
     * When standings have group_label set (e.g. World Cup), positions are
     * recalculated within each group separately.
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

        if ($standings->isEmpty()) {
            return;
        }

        // Build position assignments
        $positionUpdates = []; // [standingId => [prev_position, position]]
        $hasGroups = $standings->whereNotNull('group_label')->isNotEmpty();

        if ($hasGroups) {
            foreach ($standings->groupBy('group_label') as $groupStandings) {
                $position = 1;
                foreach ($groupStandings as $standing) {
                    $positionUpdates[$standing->id] = [
                        'prev_position' => $standing->position ?: $position,
                        'position' => $position,
                    ];
                    $position++;
                }
            }
        } else {
            $position = 1;
            foreach ($standings as $standing) {
                $positionUpdates[$standing->id] = [
                    'prev_position' => $standing->position ?: $position,
                    'position' => $position,
                ];
                $position++;
            }
        }

        // Bulk update positions in a single query
        $ids = array_keys($positionUpdates);
        $idList = "'" . implode("','", $ids) . "'";

        $posCases = [];
        $prevCases = [];
        foreach ($positionUpdates as $id => $values) {
            $posCases[] = "WHEN id = '{$id}' THEN {$values['position']}";
            $prevCases[] = "WHEN id = '{$id}' THEN {$values['prev_position']}";
        }

        DB::statement(
            'UPDATE game_standings SET '
            . 'position = CASE ' . implode(' ', $posCases) . ' END, '
            . 'prev_position = CASE ' . implode(' ', $prevCases) . ' END '
            . "WHERE id IN ({$idList})"
        );
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
