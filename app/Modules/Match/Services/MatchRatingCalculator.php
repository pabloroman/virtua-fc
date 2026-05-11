<?php

namespace App\Modules\Match\Services;

use Illuminate\Support\Collection;

/**
 * Builds the per-player 1.0–10.0 match rating that gets persisted to
 * game_player_match_ratings and shown on the post-match summary.
 *
 * Shares MvpCalculator::countEvents() and MvpCalculator::scorePlayer() so the
 * MVP pick and the rating distribution stay in lock-step. The JS port at
 * resources/js/modules/player-ratings.js still drives in-match (live) display;
 * full-time numbers come from this calculator.
 */
class MatchRatingCalculator
{
    /**
     * Compute ratings for every player that has a performance modifier in this match.
     *
     * Accepts the same `$matchResult` shape that MatchResultProcessor::processAll()
     * loops over: `performances` (map), `homeTeamId`, `awayTeamId`, `homeScore`,
     * `awayScore`, `events` (array of MatchEventData::toArray() rows).
     * MatchResimulationService passes the same shape, but with `events` carrying
     * MatchEvent models — both are handled by MvpCalculator::countEvents().
     *
     * @param  array{
     *   performances: array<string,float>,
     *   homeTeamId: string,
     *   awayTeamId: string,
     *   homeScore: int,
     *   awayScore: int,
     *   events: iterable<mixed>,
     * } $matchResult
     * @param  Collection  $homePlayers  Need id + position_group
     * @param  Collection  $awayPlayers  Need id + position_group
     * @return array<string, array{rating: float, performance_modifier: float}>
     */
    public function calculate(array $matchResult, Collection $homePlayers, Collection $awayPlayers): array
    {
        $performances = $matchResult['performances'] ?? [];
        if (empty($performances)) {
            return [];
        }

        $homeTeamId = $matchResult['homeTeamId'];
        $awayTeamId = $matchResult['awayTeamId'];
        $homeScore = (int) $matchResult['homeScore'];
        $awayScore = (int) $matchResult['awayScore'];

        $positionGroups = [];
        $playerTeams = [];
        foreach ($homePlayers as $player) {
            $positionGroups[$player->id] = $player->position_group;
            $playerTeams[$player->id] = $homeTeamId;
        }
        foreach ($awayPlayers as $player) {
            $positionGroups[$player->id] = $player->position_group;
            $playerTeams[$player->id] = $awayTeamId;
        }

        $goalsConceded = [
            $homeTeamId => $awayScore,
            $awayTeamId => $homeScore,
        ];

        $winningTeamId = match (true) {
            $homeScore > $awayScore => $homeTeamId,
            $awayScore > $homeScore => $awayTeamId,
            default => null,
        };
        $losingTeamId = match (true) {
            $homeScore > $awayScore => $awayTeamId,
            $awayScore > $homeScore => $homeTeamId,
            default => null,
        };

        $counts = MvpCalculator::countEvents($matchResult['events'] ?? []);

        $ratings = [];
        foreach ($performances as $playerId => $performance) {
            $teamId = $playerTeams[$playerId] ?? null;
            if ($teamId === null) {
                // Player not on either roster — skip rather than score them
                // against unknown team context.
                continue;
            }

            $score = MvpCalculator::scorePlayer(
                performance: (float) $performance,
                positionGroup: $positionGroups[$playerId] ?? 'Midfielder',
                goals: $counts['goals'][$playerId] ?? 0,
                assists: $counts['assists'][$playerId] ?? 0,
                yellowCards: $counts['yellowCards'][$playerId] ?? 0,
                redCards: $counts['redCards'][$playerId] ?? 0,
                teamConceded: $goalsConceded[$teamId] ?? 0,
                isWinner: $winningTeamId !== null && $teamId === $winningTeamId,
                isLoser: $losingTeamId !== null && $teamId === $losingTeamId,
            );

            // Same scale conversion as resources/js/modules/player-ratings.js:179
            $rating = round(max(1.0, min(10.0, $score * 4 + 5)), 1);

            $ratings[$playerId] = [
                'rating' => $rating,
                'performance_modifier' => (float) $performance,
            ];
        }

        return $ratings;
    }
}
