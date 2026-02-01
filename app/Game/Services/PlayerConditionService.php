<?php

namespace App\Game\Services;

use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use Carbon\Carbon;

class PlayerConditionService
{
    // Fitness loss by position group (midfielders run the most)
    private const FITNESS_LOSS = [
        'Goalkeeper' => [5, 8],
        'Defender' => [12, 18],
        'Midfielder' => [15, 22],
        'Forward' => [12, 18],
    ];

    // Base recovery per day of rest (only for players who played)
    private const FITNESS_RECOVERY_PER_DAY = 4;

    // Fitness loss for players who don't play (lose match sharpness)
    private const FITNESS_DECAY_NOT_PLAYING = [2, 4];

    // Maximum fitness
    private const MAX_FITNESS = 100;

    // Minimum fitness (players can't drop below this)
    private const MIN_FITNESS = 40;

    // Minimum fitness for unused players (they plateau here)
    private const MIN_FITNESS_UNUSED = 60;

    // Morale changes
    private const MORALE_WIN = [3, 6];
    private const MORALE_DRAW = [-1, 2];
    private const MORALE_LOSS = [-6, -2];

    // Individual event morale impacts
    private const MORALE_GOAL = [2, 4];
    private const MORALE_ASSIST = [1, 3];
    private const MORALE_OWN_GOAL = [-4, -2];
    private const MORALE_RED_CARD = [-5, -3];
    private const MORALE_YELLOW_CARD = [-1, 0];
    private const MORALE_INJURY = [-3, -1];

    // Morale bounds
    private const MAX_MORALE = 100;
    private const MIN_MORALE = 20;

    /**
     * Update fitness and morale for all players after a match.
     *
     * @param GameMatch $match The completed match
     * @param array $events Array of match events
     * @param Carbon|null $previousMatchDate Date of the previous match (for recovery calculation)
     */
    public function updateAfterMatch(GameMatch $match, array $events, ?Carbon $previousMatchDate = null): void
    {
        $gameId = $match->game_id;

        // Get all players for both teams
        $allPlayers = GamePlayer::where('game_id', $gameId)
            ->whereIn('team_id', [$match->home_team_id, $match->away_team_id])
            ->get();

        // Get lineup player IDs
        $homeLineupIds = $match->home_lineup ?? [];
        $awayLineupIds = $match->away_lineup ?? [];
        $allLineupIds = array_merge($homeLineupIds, $awayLineupIds);

        // Calculate days since previous match for recovery
        $daysSinceLastMatch = 0;
        if ($previousMatchDate) {
            $daysSinceLastMatch = $previousMatchDate->diffInDays($match->scheduled_date);
        }

        // Group events by player for individual morale effects
        $eventsByPlayer = $this->groupEventsByPlayer($events);

        // Determine match result for each team
        $homeWon = $match->home_score > $match->away_score;
        $awayWon = $match->away_score > $match->home_score;
        $isDraw = $match->home_score === $match->away_score;

        foreach ($allPlayers as $player) {
            $isInLineup = in_array($player->id, $allLineupIds);
            $isHomePlayer = $player->team_id === $match->home_team_id;

            // Calculate fitness change
            $fitnessChange = $this->calculateFitnessChange(
                $player,
                $isInLineup,
                $daysSinceLastMatch
            );

            // Calculate morale change
            $moraleChange = $this->calculateMoraleChange(
                $player,
                $isInLineup,
                $isHomePlayer ? $homeWon : $awayWon,
                $isHomePlayer ? $awayWon : $homeWon,
                $isDraw,
                $eventsByPlayer[$player->id] ?? []
            );

            // Apply changes
            $newFitness = max(self::MIN_FITNESS, min(self::MAX_FITNESS, $player->fitness + $fitnessChange));
            $newMorale = max(self::MIN_MORALE, min(self::MAX_MORALE, $player->morale + $moraleChange));

            $player->update([
                'fitness' => $newFitness,
                'morale' => $newMorale,
            ]);
        }
    }

    /**
     * Apply fitness recovery for players between matches.
     * Call this for teams NOT playing on a matchday.
     */
    public function applyRecovery(string $gameId, string $teamId, int $days = 1): void
    {
        $players = GamePlayer::where('game_id', $gameId)
            ->where('team_id', $teamId)
            ->get();

        foreach ($players as $player) {
            $recovery = min(
                self::MAX_FITNESS - $player->fitness,
                self::FITNESS_RECOVERY_PER_DAY * $days
            );

            if ($recovery > 0) {
                $player->increment('fitness', $recovery);
            }
        }
    }

    /**
     * Calculate fitness change for a player.
     */
    private function calculateFitnessChange(GamePlayer $player, bool $playedMatch, int $daysSinceLastMatch): int
    {
        $change = 0;

        if ($playedMatch) {
            // Players who played: lose fitness from exertion, but recover from rest days

            // Recovery from rest days since last match (only for active players)
            if ($daysSinceLastMatch > 0) {
                // Diminishing returns: first few days recover more
                $recoveryDays = min($daysSinceLastMatch, 7);
                $recovery = (int) (self::FITNESS_RECOVERY_PER_DAY * $recoveryDays * 0.7);
                $change += $recovery;
            }

            // Fatigue from playing
            $positionGroup = $player->position_group;
            $lossRange = self::FITNESS_LOSS[$positionGroup] ?? [12, 18];
            $loss = rand($lossRange[0], $lossRange[1]);

            // Low fitness players lose more (they're pushing themselves harder)
            if ($player->fitness < 70) {
                $loss += 5;
            }

            $change -= $loss;
        } else {
            // Players who didn't play: lose "match sharpness"
            // They're not getting game time, so fitness decays

            // Only decay if above the unused minimum
            if ($player->fitness > self::MIN_FITNESS_UNUSED) {
                $decay = rand(self::FITNESS_DECAY_NOT_PLAYING[0], self::FITNESS_DECAY_NOT_PLAYING[1]);
                $change -= $decay;

                // Don't let them fall below the unused minimum from decay alone
                $projectedFitness = $player->fitness + $change;
                if ($projectedFitness < self::MIN_FITNESS_UNUSED) {
                    $change = self::MIN_FITNESS_UNUSED - $player->fitness;
                }
            }
        }

        return $change;
    }

    /**
     * Calculate morale change for a player.
     */
    private function calculateMoraleChange(
        GamePlayer $player,
        bool $playedMatch,
        bool $teamWon,
        bool $teamLost,
        bool $isDraw,
        array $playerEvents
    ): int {
        $change = 0;

        // Match result affects all squad members, but more for those who played
        $resultMultiplier = $playedMatch ? 1.0 : 0.5;

        if ($teamWon) {
            $change += (int) (rand(self::MORALE_WIN[0], self::MORALE_WIN[1]) * $resultMultiplier);
        } elseif ($teamLost) {
            $change += (int) (rand(self::MORALE_LOSS[0], self::MORALE_LOSS[1]) * $resultMultiplier);
        } else {
            $change += (int) (rand(self::MORALE_DRAW[0], self::MORALE_DRAW[1]) * $resultMultiplier);
        }

        // Individual event impacts (only for players who participated)
        if ($playedMatch) {
            foreach ($playerEvents as $event) {
                $change += match ($event['event_type']) {
                    'goal' => rand(self::MORALE_GOAL[0], self::MORALE_GOAL[1]),
                    'assist' => rand(self::MORALE_ASSIST[0], self::MORALE_ASSIST[1]),
                    'own_goal' => rand(self::MORALE_OWN_GOAL[0], self::MORALE_OWN_GOAL[1]),
                    'red_card' => rand(self::MORALE_RED_CARD[0], self::MORALE_RED_CARD[1]),
                    'yellow_card' => rand(self::MORALE_YELLOW_CARD[0], self::MORALE_YELLOW_CARD[1]),
                    'injury' => rand(self::MORALE_INJURY[0], self::MORALE_INJURY[1]),
                    default => 0,
                };
            }
        }

        // Players with very low morale are harder to boost
        if ($player->morale < 40 && $change > 0) {
            $change = (int) ($change * 0.7);
        }

        // Players with very high morale don't drop as easily
        if ($player->morale > 85 && $change < 0) {
            $change = (int) ($change * 0.7);
        }

        return $change;
    }

    /**
     * Group match events by player ID for quick lookup.
     */
    private function groupEventsByPlayer(array $events): array
    {
        $grouped = [];

        foreach ($events as $event) {
            $playerId = $event['game_player_id'] ?? null;
            if ($playerId) {
                $grouped[$playerId][] = $event;
            }
        }

        return $grouped;
    }

    /**
     * Get previous match date for a team.
     */
    public function getPreviousMatchDate(string $gameId, string $teamId, string $currentMatchId): ?Carbon
    {
        $previousMatch = GameMatch::where('game_id', $gameId)
            ->where('played', true)
            ->where('id', '!=', $currentMatchId)
            ->where(function ($query) use ($teamId) {
                $query->where('home_team_id', $teamId)
                    ->orWhere('away_team_id', $teamId);
            })
            ->orderByDesc('played_at')
            ->first();

        return $previousMatch?->scheduled_date;
    }
}
