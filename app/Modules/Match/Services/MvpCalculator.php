<?php

namespace App\Modules\Match\Services;

use Illuminate\Support\Collection;

/**
 * Calculates the MVP (Man of the Match) from player performances and match events.
 *
 * Shared between FullMatchSimulationService (initial simulation) and
 * MatchResimulationService (after tactical changes). The per-player scoring
 * helpers `countEvents()` and `scorePlayer()` are also consumed by
 * MatchRatingCalculator so the MVP pick and the persisted 1.0–10.0 rating
 * never drift from each other.
 */
class MvpCalculator
{
    /**
     * Score each player and return the ID of the best performer.
     *
     * @param  array  $performances  Map of playerId → performance modifier (0.70–1.30)
     * @param  Collection  $homePlayers  Home team players (need id, position_group)
     * @param  Collection  $awayPlayers  Away team players (need id, position_group)
     * @param  string  $homeTeamId
     * @param  string  $awayTeamId
     * @param  int  $homeScore  Final home score
     * @param  int  $awayScore  Final away score
     * @param  Collection  $events  Match events (MatchEventData or MatchEvent models with type/event_type and gamePlayerId/game_player_id)
     */
    public static function calculate(
        array $performances,
        Collection $homePlayers,
        Collection $awayPlayers,
        string $homeTeamId,
        string $awayTeamId,
        int $homeScore,
        int $awayScore,
        Collection $events,
    ): ?string {
        if (empty($performances)) {
            return null;
        }

        // Build lookup maps for position group and team membership
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

        $counts = self::countEvents($events);
        $lateEntryThreshold = $counts['matchLength'] - 10;

        // Score each player
        $bestPlayerId = null;
        $bestScore = -INF;
        $bestGoals = -1;
        $bestMinutesPlayed = -1;

        foreach ($performances as $playerId => $performance) {
            $group = $positionGroups[$playerId] ?? 'Midfielder';
            $teamId = $playerTeams[$playerId] ?? null;
            $teamConceded = $teamId ? ($goalsConceded[$teamId] ?? 0) : 0;

            $goalsScored = $counts['goals'][$playerId] ?? 0;
            $assistsMade = $counts['assists'][$playerId] ?? 0;
            $entryMinute = $counts['entryMinutes'][$playerId] ?? 0;
            $exitMinute = $counts['exitMinutes'][$playerId] ?? $counts['matchLength'];
            $minutesPlayed = max(0, $exitMinute - $entryMinute);

            // Late entrants (entered in the last 10 minutes) are ineligible unless
            // they scored or assisted — brief cameos shouldn't win MVP.
            if ($entryMinute > $lateEntryThreshold && $goalsScored === 0 && $assistsMade === 0) {
                continue;
            }

            $score = self::scorePlayer(
                performance: $performance,
                positionGroup: $group,
                goals: $goalsScored,
                assists: $assistsMade,
                yellowCards: $counts['yellowCards'][$playerId] ?? 0,
                redCards: $counts['redCards'][$playerId] ?? 0,
                teamConceded: $teamConceded,
                isWinner: $winningTeamId !== null && $teamId === $winningTeamId,
                isLoser: $losingTeamId !== null && $teamId === $losingTeamId,
            );

            // Tiebreaks on equal score: more goals scored, then more minutes played.
            $takeThis = $score > $bestScore
                || ($score === $bestScore && $goalsScored > $bestGoals)
                || ($score === $bestScore && $goalsScored === $bestGoals && $minutesPlayed > $bestMinutesPlayed);

            if ($takeThis) {
                $bestScore = $score;
                $bestGoals = $goalsScored;
                $bestMinutesPlayed = $minutesPlayed;
                $bestPlayerId = $playerId;
            }
        }

        return $bestPlayerId;
    }

    /**
     * Aggregate counts and timing data from match events.
     *
     * Accepts MatchEventData DTOs (type, gamePlayerId), MatchEvent models
     * (event_type, game_player_id) and the snake_case array shape emitted by
     * MatchEventData::toArray() (which is what `$matchResult['events']` carries
     * inside MatchResultProcessor). Match length switches from 90 to 120 when
     * any event minute exceeds regulation stoppage (>93).
     *
     * @param  iterable<mixed>  $events
     * @return array{
     *   goals: array<string,int>,
     *   assists: array<string,int>,
     *   yellowCards: array<string,int>,
     *   redCards: array<string,int>,
     *   entryMinutes: array<string,int>,
     *   exitMinutes: array<string,int>,
     *   matchLength: int,
     * }
     */
    public static function countEvents(iterable $events): array
    {
        $goals = [];
        $assists = [];
        $yellowCards = [];
        $redCards = [];
        $entryMinutes = [];
        $exitMinutes = [];
        $matchLength = 90;

        foreach ($events as $event) {
            $phase = null;
            if (is_array($event)) {
                $type = $event['type'] ?? $event['event_type'] ?? null;
                $playerId = $event['gamePlayerId'] ?? $event['game_player_id'] ?? null;
                $minute = $event['minute'] ?? 0;
                $metadata = $event['metadata'] ?? null;
                $phase = $event['phase'] ?? null;
            } else {
                $type = $event->type ?? $event->event_type ?? null;
                $playerId = $event->gamePlayerId ?? $event->game_player_id ?? null;
                $minute = $event->minute ?? 0;
                $metadata = $event->metadata ?? null;
                $phase = $event->phase ?? null;
            }

            // Phase-based ET detection — works whether `phase` is a MatchPhase
            // enum (Eloquent model) or its string value (array). Fall back to
            // a minute heuristic if phase is missing (legacy callers).
            $isExtraTime = match (true) {
                $phase instanceof \App\Modules\Match\Enums\MatchPhase => $phase->isExtraTime(),
                is_string($phase) => str_starts_with($phase, 'et_'),
                default => $minute > 93,
            };
            if ($isExtraTime) {
                $matchLength = 120;
            }

            if (! $playerId) {
                continue;
            }

            match ($type) {
                'goal' => $goals[$playerId] = ($goals[$playerId] ?? 0) + 1,
                'assist' => $assists[$playerId] = ($assists[$playerId] ?? 0) + 1,
                'yellow_card' => $yellowCards[$playerId] = ($yellowCards[$playerId] ?? 0) + 1,
                'red_card' => $redCards[$playerId] = ($redCards[$playerId] ?? 0) + 1,
                default => null,
            };

            if ($type === 'substitution') {
                $exitMinutes[$playerId] = $minute;
                $playerInId = $metadata['player_in_id'] ?? null;
                if ($playerInId) {
                    $entryMinutes[$playerInId] = $minute;
                }
            } elseif ($type === 'red_card') {
                $exitMinutes[$playerId] = $minute;
            }
        }

        return compact('goals', 'assists', 'yellowCards', 'redCards', 'entryMinutes', 'exitMinutes', 'matchLength');
    }

    /**
     * Compute the raw per-player performance score used by both MVP selection
     * and the persisted 1.0–10.0 rating. The rating converts via
     * `round(max(1, min(10, $score * 4 + 5)), 1)`.
     *
     * Mirrors `resources/js/modules/player-ratings.js` exactly so client
     * (live-match) and server (persisted) numbers agree.
     */
    public static function scorePlayer(
        float $performance,
        string $positionGroup,
        int $goals,
        int $assists,
        int $yellowCards,
        int $redCards,
        int $teamConceded,
        bool $isWinner,
        bool $isLoser,
    ): float {
        // Position-scaled event bonuses (rarer contributions score higher)
        $goalBonuses = ['Goalkeeper' => 0.55, 'Defender' => 0.45, 'Midfielder' => 0.35, 'Forward' => 0.30];
        $assistBonuses = ['Goalkeeper' => 0.25, 'Defender' => 0.15, 'Midfielder' => 0.15, 'Forward' => 0.15];

        // Normalized performance: map 0.70-1.30 to 0.0-1.0
        $score = ($performance - 0.70) / 0.60;

        // Position-scaled goal/assist bonuses
        $score += $goals * ($goalBonuses[$positionGroup] ?? 0.15);
        $score += $assists * ($assistBonuses[$positionGroup] ?? 0.10);

        // Card penalties
        $score -= $yellowCards * 0.10;
        $score -= $redCards * 0.30;

        // Clean sheet bonus for goalkeepers and defenders
        if ($teamConceded === 0) {
            $score += match ($positionGroup) {
                'Goalkeeper' => 0.20,
                'Defender' => 0.15,
                default => 0.0,
            };
        } elseif ($teamConceded === 1) {
            $score += match ($positionGroup) {
                'Goalkeeper' => 0.05,
                'Defender' => 0.05,
                default => 0.0,
            };
        }

        // Goals conceded penalty for goalkeepers
        if ($positionGroup === 'Goalkeeper') {
            $score -= match (true) {
                $teamConceded >= 4 => 0.20,
                $teamConceded >= 3 => 0.10,
                default => 0.0,
            };
        }

        // Winning team edge
        if ($isWinner) {
            $score += 0.08;
        }

        // Goals against penalty for losing team (linear per goal conceded)
        if ($isLoser) {
            $score -= min($teamConceded * 0.04, 0.20);
        }

        return $score;
    }
}
