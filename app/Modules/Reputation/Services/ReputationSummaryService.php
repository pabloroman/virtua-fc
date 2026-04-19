<?php

namespace App\Modules\Reputation\Services;

use App\Models\ClubProfile;
use App\Models\Competition;
use App\Models\Game;
use App\Models\GameStanding;
use App\Models\TeamReputation;

/**
 * Shapes the data shown on the Club > Reputation page: current tier,
 * progression within tier, the seeded anchor the club can't fall more than
 * two tiers below, and a directional hint projecting how the season's
 * current standing would move reputation at season end. Read-side only.
 */
class ReputationSummaryService
{
    /**
     * @return array{
     *   current_level: string,
     *   current_points: int,
     *   base_level: string,
     *   tier_floor: string,
     *   tier_index: int,
     *   base_tier_index: int,
     *   points_in_tier: int,
     *   tier_span: int,
     *   points_to_next_tier: ?int,
     *   tier_thresholds: array<string,int>,
     *   direction: 'rising'|'stable'|'declining',
     *   direction_detail: array{points_delta:int,gravity:int,net:int,position:?int},
     * }
     */
    public function build(Game $game): array
    {
        $reputation = TeamReputation::where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->first();

        $currentLevel = $reputation?->reputation_level ?? ClubProfile::REPUTATION_LOCAL;
        $currentPoints = (int) ($reputation?->reputation_points ?? 0);
        $baseLevel = $reputation?->base_reputation_level ?? $currentLevel;

        $thresholds = TeamReputation::TIER_THRESHOLDS;
        $tierIndex = ClubProfile::getReputationTierIndex($currentLevel);
        $baseTierIndex = ClubProfile::getReputationTierIndex($baseLevel);

        $currentThreshold = $thresholds[$currentLevel] ?? 0;
        $nextLevel = ClubProfile::REPUTATION_TIERS[$tierIndex + 1] ?? null;
        $nextThreshold = $nextLevel !== null ? ($thresholds[$nextLevel] ?? null) : null;

        $pointsInTier = max(0, $currentPoints - $currentThreshold);
        $tierSpan = $nextThreshold !== null ? $nextThreshold - $currentThreshold : 0;
        $pointsToNextTier = $nextThreshold !== null ? max(0, $nextThreshold - $currentPoints) : null;

        $floorIndex = max(0, $baseTierIndex - TeamReputation::MAX_TIER_DROP_BELOW_BASE);
        $tierFloor = ClubProfile::REPUTATION_TIERS[$floorIndex];

        [$direction, $detail] = $this->projectDirection($game, $currentLevel);

        return [
            'current_level' => $currentLevel,
            'current_points' => $currentPoints,
            'base_level' => $baseLevel,
            'tier_floor' => $tierFloor,
            'tier_index' => $tierIndex,
            'base_tier_index' => $baseTierIndex,
            'points_in_tier' => $pointsInTier,
            'tier_span' => $tierSpan,
            'points_to_next_tier' => $pointsToNextTier,
            'tier_thresholds' => $thresholds,
            'direction' => $direction,
            'direction_detail' => $detail,
        ];
    }

    /**
     * Project how reputation would move at season end if the league ended
     * with the team at its current standings position — same formula the
     * SeasonClosingPipeline's ReputationUpdateProcessor uses, so the hint
     * stays calibrated against the actual mechanic.
     *
     * @return array{0: 'rising'|'stable'|'declining', 1: array{points_delta:int,gravity:int,net:int,position:?int}}
     */
    private function projectDirection(Game $game, string $level): array
    {
        $position = GameStanding::where('game_id', $game->id)
            ->where('competition_id', $game->competition_id)
            ->where('team_id', $game->team_id)
            ->value('position');

        $position = $position !== null ? (int) $position : null;

        $detail = ['points_delta' => 0, 'gravity' => 0, 'net' => 0, 'position' => $position];

        if ($position === null) {
            return ['stable', $detail];
        }

        $competition = Competition::find($game->competition_id);
        $tier = $competition?->tier ?? 1;
        $deltas = config("reputation.position_deltas.{$tier}", config('reputation.position_deltas.1'));

        $pointsDelta = 0;
        foreach ($deltas as $maxPosition => $delta) {
            if ($position <= $maxPosition) {
                $pointsDelta = $delta;
                break;
            }
        }
        if ($pointsDelta === 0 && !empty($deltas)) {
            $pointsDelta = end($deltas);
        }

        $gravity = (int) (config('reputation.gravity', [])[$level] ?? 0);
        $net = $pointsDelta - $gravity;

        $direction = $net > 5 ? 'rising' : ($net < -5 ? 'declining' : 'stable');

        return [$direction, [
            'points_delta' => $pointsDelta,
            'gravity' => $gravity,
            'net' => $net,
            'position' => $position,
        ]];
    }
}
