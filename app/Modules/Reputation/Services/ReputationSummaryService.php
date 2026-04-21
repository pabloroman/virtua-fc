<?php

namespace App\Modules\Reputation\Services;

use App\Models\ClubProfile;
use App\Models\Competition;
use App\Models\Game;
use App\Models\GameStanding;
use App\Models\TeamReputation;

/**
 * Shapes the data shown on the Club > Reputation page: current tier,
 * progression within tier, loyalty, and a ladder of season-end outcomes
 * derived from the same config the closing pipeline consumes. Read-side only.
 */
class ReputationSummaryService
{
    /**
     * @return array{
     *   current_level: string,
     *   tier_index: int,
     *   points_in_tier: int,
     *   tier_span: int,
     *   loyalty_points: int,
     *   qualitative_distance: ?string,
     *   outcome_ladder: list<array{position_range:string,impact_key:string,is_current:bool,size:int}>,
     *   tier_maintenance_applies: bool,
     * }
     */
    public function build(Game $game): array
    {
        $reputation = TeamReputation::where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->first();

        $currentLevel = $reputation?->reputation_level ?? ClubProfile::REPUTATION_LOCAL;
        $currentPoints = (int) ($reputation?->reputation_points ?? 0);
        $loyaltyPoints = (int) ($reputation?->loyalty_points ?? 0);

        $thresholds = TeamReputation::TIER_THRESHOLDS;
        $tierIndex = ClubProfile::getReputationTierIndex($currentLevel);

        $currentThreshold = $thresholds[$currentLevel] ?? 0;
        $nextLevel = ClubProfile::REPUTATION_TIERS[$tierIndex + 1] ?? null;
        $nextThreshold = $nextLevel !== null ? ($thresholds[$nextLevel] ?? null) : null;

        $pointsInTier = max(0, $currentPoints - $currentThreshold);
        $tierSpan = $nextThreshold !== null ? $nextThreshold - $currentThreshold : 0;
        $pointsToNextTier = $nextThreshold !== null ? max(0, $nextThreshold - $currentPoints) : null;

        $position = $this->currentLeaguePosition($game);
        $outcomeLadder = $this->buildOutcomeLadder($game, $position);
        $qualitativeDistance = $this->qualitativeDistance($pointsToNextTier);
        $tierMaintenanceApplies = (int) (config('reputation.gravity', [])[$currentLevel] ?? 0) > 0;

        return [
            'current_level' => $currentLevel,
            'tier_index' => $tierIndex,
            'points_in_tier' => $pointsInTier,
            'tier_span' => $tierSpan,
            'loyalty_points' => $loyaltyPoints,
            'qualitative_distance' => $qualitativeDistance,
            'outcome_ladder' => $outcomeLadder,
            'tier_maintenance_applies' => $tierMaintenanceApplies,
        ];
    }

    private function currentLeaguePosition(Game $game): ?int
    {
        $position = GameStanding::where('game_id', $game->id)
            ->where('competition_id', $game->competition_id)
            ->where('team_id', $game->team_id)
            ->value('position');

        return $position !== null ? (int) $position : null;
    }

    /**
     * Derive an always-visible ladder of season-end outcomes from the same
     * position-delta config the season-close processor consumes. The player
     * sees the decision landscape — what each finish is worth — whether or
     * not a standing exists yet, and the current projected band is marked
     * once a standing is recorded.
     *
     * Impact keys are qualitative buckets: the UI never prints raw points.
     *
     * @return list<array{position_range:string,impact_key:string,is_current:bool,size:int}>
     */
    private function buildOutcomeLadder(Game $game, ?int $position): array
    {
        $competition = Competition::find($game->competition_id);
        $tier = $competition?->tier ?? 1;
        $deltas = config("reputation.position_deltas.{$tier}", config('reputation.position_deltas.1'));

        $ladder = [];
        $previousMax = 0;
        foreach ($deltas as $maxPosition => $delta) {
            $low = $previousMax + 1;
            $high = (int) $maxPosition;
            $range = $high >= 99 ? $low . '+' : ($low === $high ? (string) $low : $low . '–' . $high);
            $isCurrent = $position !== null && $position >= $low && $position <= $high;
            // Catchall (99) represents the relegation zone; cap it at a plausible
            // tail width so the horizontal bar reads proportionally to league size.
            $size = $high >= 99 ? 3 : max(1, $high - $low + 1);

            $ladder[] = [
                'position_range' => $range,
                'impact_key' => $this->impactKey((int) $delta),
                'is_current' => $isCurrent,
                'size' => $size,
            ];

            $previousMax = $high;
        }

        return $ladder;
    }

    private function impactKey(int $delta): string
    {
        return match (true) {
            $delta >= 30 => 'major_leap',
            $delta >= 15 => 'solid_step',
            $delta >= 5 => 'small_step',
            $delta === 0 => 'stalls',
            default => 'setback',
        };
    }

    /**
     * Translate the points-to-next-tier gap into a felt distance the player
     * can reason about in terms of seasons, not raw points. Buckets are tuned
     * against a strong-season league reward of ~+30.
     */
    private function qualitativeDistance(?int $pointsToNextTier): ?string
    {
        if ($pointsToNextTier === null) {
            return null;
        }

        return match (true) {
            $pointsToNextTier <= 30 => 'one_strong_season',
            $pointsToNextTier <= 60 => 'two_strong_seasons',
            $pointsToNextTier <= 100 => 'several_seasons',
            default => 'long_road',
        };
    }
}
