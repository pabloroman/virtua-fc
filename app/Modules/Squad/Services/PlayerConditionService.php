<?php

namespace App\Modules\Squad\Services;

use App\Models\GamePlayer;
use Illuminate\Support\Facades\DB;

class PlayerConditionService
{
    // Maximum fitness
    private const MAX_FITNESS = 100;

    // Minimum fitness (players can't drop below this)
    private const MIN_FITNESS = 40;

    // Morale changes
    private const MORALE_WIN = [3, 6];
    private const MORALE_DRAW = [-1, 2];
    private const MORALE_LOSS = [-6, -2];

    // Bench frustration: morale penalty applied each match a player doesn't feature
    private const MORALE_BENCH_FRUSTRATION = [2, 3];

    // Individual event morale impacts
    private const MORALE_GOAL = [2, 4];
    private const MORALE_ASSIST = [1, 3];
    private const MORALE_OWN_GOAL = [-4, -2];

    // Morale bounds
    private const MAX_MORALE = 100;
    private const MIN_MORALE = 40;

    /**
     * Batch-update fitness and morale for all players across all matches in a matchday.
     * Single UPDATE query for all players (~500 in a typical La Liga matchday).
     *
     * @param  \Illuminate\Support\Collection  $matches  All matches in this matchday batch
     * @param  array  $matchResults  Match result data including events
     * @param  \Illuminate\Support\Collection  $allPlayersByTeam  Pre-loaded players grouped by team_id
     * @param  int  $daysSinceLastMatchday  Calendar days since the previous matchday
     */
    public function batchUpdateAfterMatchday($matches, array $matchResults, $allPlayersByTeam, int $daysSinceLastMatchday): void
    {
        $updates = [];

        // Index by matchId for O(1) lookups instead of O(n) per match
        $resultsByMatchId = [];
        foreach ($matchResults as $result) {
            $resultsByMatchId[$result['matchId']] = $result;
        }

        foreach ($matches as $match) {
            $result = $resultsByMatchId[$match->id] ?? null;
            $events = $result['events'] ?? [];
            $eventsByPlayer = $this->groupEventsByPlayer($events);

            $lineupIds = array_merge($match->home_lineup ?? [], $match->away_lineup ?? []);
            $homeWon = $match->home_score > $match->away_score;
            $awayWon = $match->away_score > $match->home_score;

            $players = collect()
                ->merge($allPlayersByTeam->get($match->home_team_id, collect()))
                ->merge($allPlayersByTeam->get($match->away_team_id, collect()));

            foreach ($players as $player) {
                if (isset($updates[$player->id])) {
                    continue; // already processed via another match
                }

                $isInLineup = in_array($player->id, $lineupIds);
                $isHome = $player->team_id === $match->home_team_id;

                $fitnessChange = $this->calculateFitnessChange($player, $isInLineup, $daysSinceLastMatchday);
                $moraleChange = $this->calculateMoraleChange(
                    $player,
                    $isInLineup,
                    $isHome ? $homeWon : $awayWon,
                    $isHome ? $awayWon : $homeWon,
                    $eventsByPlayer[$player->id] ?? []
                );

                $updates[$player->id] = [
                    'fitness' => max(self::MIN_FITNESS, min(self::MAX_FITNESS, $player->fitness + $fitnessChange)),
                    'morale' => max(self::MIN_MORALE, min(self::MAX_MORALE, $player->morale + $moraleChange)),
                ];
            }
        }

        $this->bulkUpdateConditions($updates);
    }

    /**
     * Perform bulk update of fitness and morale using a single query.
     */
    private function bulkUpdateConditions(array $updates): void
    {
        if (empty($updates)) {
            return;
        }

        $ids = array_keys($updates);
        $fitnessCases = [];
        $moraleCases = [];

        foreach ($updates as $id => $values) {
            $fitnessCases[] = "WHEN id = '{$id}' THEN {$values['fitness']}";
            $moraleCases[] = "WHEN id = '{$id}' THEN {$values['morale']}";
        }

        $idList = "'" . implode("','", $ids) . "'";

        DB::statement("
            UPDATE game_players
            SET fitness = CASE " . implode(' ', $fitnessCases) . " END,
                morale = CASE " . implode(' ', $moraleCases) . " END
            WHERE id IN ({$idList})
        ");
    }

    /**
     * Calculate fitness change for a player using nonlinear recovery.
     *
     * Recovery is slow near fitness 100 and faster at lower fitness, creating
     * natural equilibria based on match frequency. Players who play every week
     * stabilize around 88-93; congested periods push them into the 70s-80s.
     *
     * Formula: recoveryRate = base × physicalMod × (1 + scaling × (100 − fitness) / 100)
     */
    private function calculateFitnessChange(GamePlayer $player, bool $playedMatch, int $daysSinceLastMatch): int
    {
        $config = config('match_simulation.fatigue');
        $currentFitness = $player->fitness;

        // Nonlinear recovery: faster when far below 100, slow near the top
        $baseRecovery = $config['base_recovery_per_day'];
        $scaling = $config['recovery_scaling'];
        $maxRecoveryDays = $config['max_recovery_days'];
        $physicalModifier = $this->getPhysicalRecoveryModifier($player, $config);

        $effectiveBase = $baseRecovery * $physicalModifier;
        $recoveryRate = $effectiveBase * (1 + $scaling * (self::MAX_FITNESS - $currentFitness) / 100);

        $recoveryDays = min($daysSinceLastMatch, $maxRecoveryDays);
        $recovery = (int) round($recoveryRate * $recoveryDays);

        if ($playedMatch) {
            // Position-based match loss with age modifier
            $positionGroup = $player->position_group;
            $lossRange = $config['fitness_loss'][$positionGroup] ?? [9, 13];
            $ageModifier = $this->getAgeLossModifier($player, $config);
            $loss = (int) round(rand($lossRange[0], $lossRange[1]) * $ageModifier);

            return $recovery - $loss;
        }

        return $recovery;
    }

    /**
     * Get age-based modifier for fitness loss (veterans lose more per match).
     */
    private function getAgeLossModifier(GamePlayer $player, array $config): float
    {
        $age = $player->age;
        $ageMod = $config['age_loss_modifier'];

        return match (true) {
            $age < $ageMod['young_threshold'] => $ageMod['young'],
            $age < $ageMod['peak_threshold'] => $ageMod['peak'],
            $age < $ageMod['veteran_threshold'] => $ageMod['experienced'],
            default => $ageMod['veteran'],
        };
    }

    /**
     * Get physical ability modifier for recovery rate (fitter players recover faster).
     */
    private function getPhysicalRecoveryModifier(GamePlayer $player, array $config): float
    {
        $physical = $player->current_physical_ability;
        $physMod = $config['physical_recovery_modifier'];

        return match (true) {
            $physical >= $physMod['high_threshold'] => $physMod['high'],
            $physical >= $physMod['low_threshold'] => $physMod['medium'],
            default => $physMod['low'],
        };
    }

    /**
     * Calculate morale change for a player.
     */
    private function calculateMoraleChange(
        GamePlayer $player,
        bool $playedMatch,
        bool $teamWon,
        bool $teamLost,
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
                    default => 0,
                };
            }
        }

        // Bench frustration: players who don't get game time gradually lose morale
        // regardless of team results. Offsets the win bonus for non-playing players.
        // Better players get more frustrated — star players have higher expectations.
        if (!$playedMatch) {
            $ability = $player->current_technical_ability;
            // Multiplier ranges from ~0.3x (ability 20) to ~1.5x (ability 100)
            $frustrationMultiplier = 0.3 + ($ability / 100.0) * 1.2;
            $baseFrustration = rand(self::MORALE_BENCH_FRUSTRATION[0], self::MORALE_BENCH_FRUSTRATION[1]);
            $change -= max(1, (int) round($baseFrustration * $frustrationMultiplier));
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

}
