<?php

namespace App\Modules\Season\Processors;

use App\Modules\Season\Contracts\SeasonProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Squad\Services\PlayerGeneratorService;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\TeamReputation;
use App\Modules\Player\PlayerAge;
use App\Support\PositionMapper;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Centralised AI roster maintenance: ensures every AI team has a viable squad.
 *
 * Runs after contract expirations, retirements, and before player development.
 * Fills gaps caused by any source of attrition: retirements, expired contracts,
 * transfers, or any other mechanism that removes players.
 *
 * All generated players are young academy graduates (ages 20-23), with ability
 * driven by the team's reputation tier — mirroring the user's youth academy.
 * Higher-reputation teams produce better prospects.
 *
 * Three rules are enforced for AI teams:
 * 1. Position group minimums (3 GK, 6 DEF, 6 MID, 4 FWD) — always, even if
 *    total squad size is sufficient (e.g. user buys all AI team's goalkeepers).
 * 2. Minimum squad size of 22 — fill remaining gaps by position target priority.
 * 3. Youth intake — each AI team receives 2-3 additional young players per season.
 *
 * User team replenishment is handled separately in YouthAcademyPromotionProcessor
 * (setup pipeline), which promotes academy players first before generating synthetic ones.
 */
class SquadReplenishmentProcessor implements SeasonProcessor
{
    /**
     * Minimum total squad size for AI teams.
     */
    private const MIN_SQUAD_SIZE = 22;

    /**
     * Youth intake: number of young players to inject per AI team per season.
     */
    private const YOUTH_INTAKE_MIN = 2;
    private const YOUTH_INTAKE_MAX = 3;

    /**
     * Maximum squad size before we skip youth intake (leave buffer for transfers).
     */
    private const YOUTH_INTAKE_SQUAD_CAP = 28;

    /**
     * Position weights for youth intake (mirrors realistic academy output).
     */
    private const YOUTH_POSITION_WEIGHTS = [
        'Goalkeeper' => 5,
        'Centre-Back' => 15,
        'Left-Back' => 8,
        'Right-Back' => 8,
        'Defensive Midfield' => 10,
        'Central Midfield' => 15,
        'Attacking Midfield' => 10,
        'Left Winger' => 8,
        'Right Winger' => 8,
        'Centre-Forward' => 13,
    ];

    /**
     * Minimum players required per position group.
     * If a group is below its minimum, those positions are filled first.
     */
    private const GROUP_MINIMUMS = [
        'Goalkeeper' => 3,
        'Defender' => 6,
        'Midfielder' => 6,
        'Forward' => 4,
    ];

    /**
     * Target player count per specific position, used to determine which
     * position within a depleted group should receive the new player.
     */
    private const POSITION_TARGETS = [
        'Goalkeeper' => 3,
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


    public function __construct(
        private readonly PlayerGeneratorService $playerGenerator,
    ) {}

    public function priority(): int
    {
        return 42;
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        // Collect all player data to generate, then bulk insert at the end
        $bulkData = [];
        $bulkMeta = [];
        $releaseIds = [];

        $playersByTeam = GamePlayer::where('game_players.game_id', $game->id)
            ->whereNotNull('game_players.team_id')
            ->join('players', 'game_players.player_id', '=', 'players.id')
            ->select([
                'game_players.id',
                'game_players.team_id',
                'game_players.position',
                'game_players.game_technical_ability',
                'game_players.game_physical_ability',
                'game_players.number',
                'players.date_of_birth',
                'players.name as player_name',
            ])
            ->get()
            ->groupBy('team_id');

        $aiTeamIds = $playersByTeam->keys()->reject(fn ($id) => $id === $game->team_id)->values();
        $reputationLevels = TeamReputation::resolveLevels($game->id, $aiTeamIds->all());

        foreach ($aiTeamIds as $teamId) {
            $players = $playersByTeam->get($teamId, collect());
            $reputationLevel = $reputationLevels->get($teamId, 'established');
            $positionCounts = $players->groupBy('position')->map->count();

            // Phase 1: Fill squad gaps (position minimums + squad size minimum)
            $positionsToFill = $this->determinePositionsToFill($positionCounts, $players->count());

            foreach ($positionsToFill as $position) {
                $playerData = $this->playerGenerator->buildYouthPlayerData(
                    $game, $teamId, $position, $reputationLevel,
                );
                $bulkData[] = $playerData;
                $bulkMeta[] = ['teamId' => $teamId, 'type' => 'replenishment'];
            }

            // Phase 2: Youth intake — additional young players per season
            $currentSquadSize = $players->count() + count($positionsToFill);
            $youthCount = mt_rand(self::YOUTH_INTAKE_MIN, self::YOUTH_INTAKE_MAX);

            // Collect release candidates if squad would exceed cap
            $slotsAvailable = self::YOUTH_INTAKE_SQUAD_CAP - $currentSquadSize;
            if ($slotsAvailable < $youthCount) {
                $toRelease = $youthCount - max(0, $slotsAvailable);
                $candidates = $this->getOldestWeakestIds($players, $game->current_date, $toRelease);
                $releaseIds = array_merge($releaseIds, $candidates);
            }

            for ($i = 0; $i < $youthCount; $i++) {
                $position = $this->selectWeightedYouthPosition();
                $playerData = $this->playerGenerator->buildYouthPlayerData(
                    $game, $teamId, $position, $reputationLevel,
                );
                $bulkData[] = $playerData;
                $bulkMeta[] = ['teamId' => $teamId, 'type' => 'youth_intake'];
            }
        }

        // Batch release old/weak players
        if (!empty($releaseIds)) {
            GamePlayer::whereIn('id', $releaseIds)->update(['team_id' => null]);
        }

        // Preseed the generator's game-wide name cache from data we already
        // loaded above, so createBulk() doesn't re-query for it.
        $this->playerGenerator->seedCaches(
            $game->id,
            $playersByTeam->flatten(1)->pluck('player_name')->toArray(),
        );
        unset($playersByTeam);

        // Bulk insert all generated players
        $results = $this->playerGenerator->createBulk($game, $bulkData);

        // Build metadata from results
        $generatedPlayers = [];
        foreach ($results as $i => $result) {
            $result['type'] = $bulkMeta[$i]['type'];
            $generatedPlayers[] = $result;
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
            if (PositionMapper::getPositionGroup($position) !== $group) {
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
            $firstInGroup = PositionMapper::getPositionsByGroup($group)[0];
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
        foreach (PositionMapper::getPositionsByGroup($group) as $position) {
            $total += $positionCounts->get($position, 0);
        }
        return $total;
    }

    /**
     * Select a random position using weighted distribution for youth intake.
     */
    private function selectWeightedYouthPosition(): string
    {
        $totalWeight = array_sum(self::YOUTH_POSITION_WEIGHTS);
        $random = mt_rand(1, $totalWeight);

        foreach (self::YOUTH_POSITION_WEIGHTS as $position => $weight) {
            $random -= $weight;
            if ($random <= 0) {
                return $position;
            }
        }

        return 'Central Midfield';
    }

    /**
     * Get IDs of the oldest, weakest players from a team to release.
     * Only targets players aged 30+ sorted by ability ascending.
     *
     * @return string[]
     */
    private function getOldestWeakestIds(Collection $players, Carbon $currentDate, int $count): array
    {
        $cutoff = PlayerAge::dateOfBirthCutoff(PlayerAge::MIN_RETIREMENT_OUTFIELD, $currentDate);

        return $players
            ->filter(fn ($gp) => $gp->date_of_birth && Carbon::parse($gp->date_of_birth)->lte($cutoff))
            ->sortBy(fn ($gp) => ($gp->game_technical_ability ?? 0) + ($gp->game_physical_ability ?? 0))
            ->take($count)
            ->pluck('id')
            ->toArray();
    }
}
