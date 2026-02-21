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
 * Ensures every AI team maintains a minimum squad size after season-end attrition.
 *
 * Contract expirations and retirements can shrink AI rosters below functional levels.
 * This processor fills gaps with generated players, prioritising the most depleted
 * position groups so that every AI team can field a competitive lineup.
 *
 * Priority: 8 (runs after retirement replacements, before player development)
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
            $currentCount = $players->count();

            if ($currentCount >= self::MIN_SQUAD_SIZE) {
                continue;
            }

            $deficit = self::MIN_SQUAD_SIZE - $currentCount;
            $teamAvgAbility = $this->calculateTeamAverageAbility($players);
            $positionCounts = $players->groupBy('position')->map->count();

            $positionsToFill = $this->determinePositionsToFill($positionCounts, $deficit);

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
     * Determine which positions to fill based on group and position deficits.
     *
     * Positions are selected by finding the largest gap between the target count
     * and the current count for each position, prioritising group minimums first.
     *
     * @param  \Illuminate\Support\Collection  $positionCounts  Current player counts keyed by position
     * @param  int  $deficit  Number of players to generate
     * @return string[]  Positions to fill
     */
    private function determinePositionsToFill($positionCounts, int $deficit): array
    {
        $positions = [];

        // Build a priority queue: positions sorted by how far below target they are
        $gaps = [];
        foreach (self::POSITION_TARGETS as $position => $target) {
            $current = $positionCounts->get($position, 0);
            $gap = $target - $current;

            if ($gap > 0) {
                // Weight group-minimum violations higher
                $group = self::POSITION_TO_GROUP[$position];
                $groupCurrent = $this->countGroupPlayers($positionCounts, $group);
                $groupMin = self::GROUP_MINIMUMS[$group];
                $groupDeficit = max(0, $groupMin - $groupCurrent);

                // Positions in groups below minimum get priority (higher score)
                $priority = $groupDeficit > 0 ? $gap + 100 : $gap;

                for ($i = 0; $i < $gap; $i++) {
                    $gaps[] = ['position' => $position, 'priority' => $priority - $i];
                }
            }
        }

        // Sort by priority descending (biggest gaps first)
        usort($gaps, fn ($a, $b) => $b['priority'] <=> $a['priority']);

        // Take the top N positions
        foreach (array_slice($gaps, 0, $deficit) as $entry) {
            $positions[] = $entry['position'];
        }

        // If we still need more players (all positions at target), spread across groups below minimum
        if (count($positions) < $deficit) {
            $remaining = $deficit - count($positions);
            $fallbackPositions = ['Central Midfield', 'Centre-Back', 'Centre-Forward', 'Goalkeeper'];

            for ($i = 0; $i < $remaining; $i++) {
                $positions[] = $fallbackPositions[$i % count($fallbackPositions)];
            }
        }

        return $positions;
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
