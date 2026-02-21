<?php

namespace App\Modules\Season\Processors;

use App\Modules\Season\Contracts\SeasonEndProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Squad\DTOs\GeneratedPlayerData;
use App\Modules\Squad\Services\PlayerGeneratorService;
use App\Models\Game;
use App\Models\GamePlayer;
use Carbon\Carbon;

/**
 * Centralised AI roster maintenance: ensures every AI team has a viable squad.
 *
 * Runs after contract expirations (5), retirements (7), and before player
 * development (10). Fills gaps caused by any source of attrition: retirements,
 * expired contracts, transfers, or any other mechanism that removes players.
 *
 * Two rules are enforced:
 * 1. Position group minimums (2 GK, 5 DEF, 5 MID, 3 FWD) — always, even if
 *    total squad size is sufficient (e.g. user buys all AI team's goalkeepers).
 * 2. Minimum squad size of 22 — fill remaining gaps by position target priority.
 *
 * Priority: 8
 */
class SquadReplenishmentProcessor implements SeasonEndProcessor
{
    /**
     * Minimum total squad size for AI teams.
     */
    private const MIN_SQUAD_SIZE = 22;

    /**
     * Minimum players required per position group.
     * If a group is below its minimum, those positions are filled first.
     */
    private const GROUP_MINIMUMS = [
        'Goalkeeper' => 2,
        'Defender' => 5,
        'Midfielder' => 5,
        'Forward' => 3,
    ];

    /**
     * Target player count per specific position, used to determine which
     * position within a depleted group should receive the new player.
     */
    private const POSITION_TARGETS = [
        'Goalkeeper' => 2,
        'Centre-Back' => 3,
        'Left-Back' => 1,
        'Right-Back' => 1,
        'Defensive Midfield' => 1,
        'Central Midfield' => 2,
        'Attacking Midfield' => 1,
        'Left Midfield' => 1,
        'Right Midfield' => 1,
        'Left Winger' => 1,
        'Right Winger' => 1,
        'Centre-Forward' => 2,
        'Second Striker' => 1,
    ];

    /**
     * Map each canonical position to its group.
     */
    private const POSITION_TO_GROUP = [
        'Goalkeeper' => 'Goalkeeper',
        'Centre-Back' => 'Defender',
        'Left-Back' => 'Defender',
        'Right-Back' => 'Defender',
        'Defensive Midfield' => 'Midfielder',
        'Central Midfield' => 'Midfielder',
        'Attacking Midfield' => 'Midfielder',
        'Left Midfield' => 'Midfielder',
        'Right Midfield' => 'Midfielder',
        'Left Winger' => 'Forward',
        'Right Winger' => 'Forward',
        'Centre-Forward' => 'Forward',
        'Second Striker' => 'Forward',
    ];

    public function __construct(
        private readonly PlayerGeneratorService $playerGenerator,
    ) {}

    public function priority(): int
    {
        return 8;
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        $generatedPlayers = [];

        // Get all AI team rosters (grouped by team)
        $teamRosters = GamePlayer::where('game_id', $game->id)
            ->where('team_id', '!=', $game->team_id)
            ->get()
            ->groupBy('team_id');

        foreach ($teamRosters as $teamId => $players) {
            $teamAvgAbility = $this->calculateTeamAverageAbility($players);
            $positionCounts = $players->groupBy('position')->map->count();

            // Determine positions needed: both squad size deficit and group minimum gaps
            $positionsToFill = $this->determinePositionsToFill($positionCounts, $players->count());

            foreach ($positionsToFill as $position) {
                $newPlayer = $this->generatePlayer(
                    $game,
                    $teamId,
                    $position,
                    $teamAvgAbility,
                    $data->newSeason,
                );

                $generatedPlayers[] = [
                    'playerId' => $newPlayer->id,
                    'playerName' => $newPlayer->player->name,
                    'position' => $newPlayer->position,
                    'teamId' => $teamId,
                ];
            }
        }

        return $data->setMetadata('squadReplenishment', $generatedPlayers);
    }

    /**
     * Determine which positions to fill based on two rules:
     *
     * 1. Group minimums are always enforced (e.g. 2 GK, 5 DEF) regardless of total squad size.
     *    This prevents situations like a team having 25 players but 0 goalkeepers.
     * 2. If total squad size is below MIN_SQUAD_SIZE, additional players are generated
     *    to reach the minimum, prioritised by position target gaps.
     *
     * @param  \Illuminate\Support\Collection  $positionCounts  Current player counts keyed by position
     * @param  int  $currentSquadSize  Current total number of players on the team
     * @return string[]  Positions to fill
     */
    private function determinePositionsToFill($positionCounts, int $currentSquadSize): array
    {
        $positions = [];

        // Phase 1: Always enforce group minimums (e.g. must have 2 GK even if squad is full)
        foreach (self::GROUP_MINIMUMS as $group => $groupMin) {
            $groupCurrent = $this->countGroupPlayers($positionCounts, $group);
            $groupDeficit = max(0, $groupMin - $groupCurrent);

            if ($groupDeficit > 0) {
                // Pick the most-depleted positions within this group
                $groupPositions = $this->getMostDepletedPositionsInGroup($positionCounts, $group, $groupDeficit);
                $positions = array_merge($positions, $groupPositions);
            }
        }

        // Update counts to account for phase 1 additions
        $updatedPositionCounts = clone $positionCounts;
        foreach ($positions as $pos) {
            $updatedPositionCounts[$pos] = ($updatedPositionCounts->get($pos, 0)) + 1;
        }

        // Phase 2: Fill up to MIN_SQUAD_SIZE using position target gaps
        $totalAfterPhase1 = $currentSquadSize + count($positions);
        $squadDeficit = max(0, self::MIN_SQUAD_SIZE - $totalAfterPhase1);

        if ($squadDeficit > 0) {
            $gaps = [];
            foreach (self::POSITION_TARGETS as $position => $target) {
                $current = $updatedPositionCounts->get($position, 0);
                $gap = $target - $current;

                for ($i = 0; $i < max(0, $gap); $i++) {
                    $gaps[] = ['position' => $position, 'priority' => $gap - $i];
                }
            }

            // Sort by biggest gap first
            usort($gaps, fn ($a, $b) => $b['priority'] <=> $a['priority']);

            foreach (array_slice($gaps, 0, $squadDeficit) as $entry) {
                $positions[] = $entry['position'];
            }

            // If still short (all positions at target), use fallback rotation
            if (count($positions) < (self::MIN_SQUAD_SIZE - $currentSquadSize)) {
                $remaining = (self::MIN_SQUAD_SIZE - $currentSquadSize) - count($positions);
                $fallbackPositions = ['Central Midfield', 'Centre-Back', 'Centre-Forward', 'Goalkeeper'];

                for ($i = 0; $i < $remaining; $i++) {
                    $positions[] = $fallbackPositions[$i % count($fallbackPositions)];
                }
            }
        }

        return $positions;
    }

    /**
     * Pick the most-depleted positions within a group to fill.
     *
     * @return string[]
     */
    private function getMostDepletedPositionsInGroup($positionCounts, string $group, int $count): array
    {
        $candidates = [];
        foreach (self::POSITION_TARGETS as $position => $target) {
            if (self::POSITION_TO_GROUP[$position] !== $group) {
                continue;
            }
            $current = $positionCounts->get($position, 0);
            $gap = $target - $current;
            if ($gap > 0) {
                for ($i = 0; $i < $gap; $i++) {
                    $candidates[] = ['position' => $position, 'priority' => $gap - $i];
                }
            }
        }

        usort($candidates, fn ($a, $b) => $b['priority'] <=> $a['priority']);

        $result = [];
        foreach (array_slice($candidates, 0, $count) as $entry) {
            $result[] = $entry['position'];
        }

        // If not enough candidates from targets (e.g. all positions at target but group still short),
        // pick the first position in the group
        if (count($result) < $count) {
            $firstInGroup = array_search($group, self::POSITION_TO_GROUP);
            for ($i = count($result); $i < $count; $i++) {
                $result[] = $firstInGroup;
            }
        }

        return $result;
    }

    /**
     * Count players in a position group from the position counts collection.
     */
    private function countGroupPlayers($positionCounts, string $group): int
    {
        $total = 0;
        foreach (self::POSITION_TO_GROUP as $position => $posGroup) {
            if ($posGroup === $group) {
                $total += $positionCounts->get($position, 0);
            }
        }
        return $total;
    }

    /**
     * Calculate the average ability across a team's roster.
     */
    private function calculateTeamAverageAbility($players): int
    {
        if ($players->isEmpty()) {
            return 55;
        }

        $totalAbility = $players->sum(function ($player) {
            return (int) round(($player->game_technical_ability + $player->game_physical_ability) / 2);
        });

        return (int) round($totalAbility / $players->count());
    }

    /**
     * Generate a player scaled to the team's average ability.
     */
    private function generatePlayer(
        Game $game,
        string $teamId,
        string $position,
        int $teamAvgAbility,
        string $newSeason,
    ): GamePlayer {
        // Ability within ±10% of team average, with some variance
        $variance = mt_rand(-10, 10);
        $baseAbility = max(35, min(90, $teamAvgAbility + $variance));

        $techBias = mt_rand(-5, 5);
        $technical = max(30, min(95, $baseAbility + $techBias));
        $physical = max(30, min(95, $baseAbility - $techBias));

        $seasonYear = (int) $newSeason;
        $age = mt_rand(21, 29);
        $dateOfBirth = Carbon::createFromDate($seasonYear - $age, mt_rand(1, 12), mt_rand(1, 28));

        return $this->playerGenerator->create($game, new GeneratedPlayerData(
            teamId: $teamId,
            position: $position,
            technical: $technical,
            physical: $physical,
            dateOfBirth: $dateOfBirth,
            contractYears: mt_rand(2, 4),
        ));
    }
}
