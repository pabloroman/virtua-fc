<?php

namespace App\Modules\Squad\DTOs;

use App\Models\GamePlayer;
use App\Modules\Lineup\Enums\Formation;
use App\Modules\Squad\Services\NextSeasonProjectionService;

/**
 * Per-squad reference stats that let the planner's recommendations stay
 * relative to the actual roster.
 *
 * Built once from the projection + chosen formation and shared with
 * SquadActionRecommender and SquadAdvisorService. Replaces the prior
 * absolute thresholds (e.g. "≥ 72 overall = impactful loss") which misfired
 * on squads at the extremes — an 81 overall is a real loss at a
 * Champions League club and an irrelevant body at a relegation candidate.
 */
final readonly class SquadContext
{
    /**
     * Slack applied to the worst-starter benchmark when deciding if a
     * departure is impactful. Zero = player must project at or above the
     * worst projected starter to count as a starting-XI loss.
     */
    private const IMPACTFUL_LOSS_SLACK = 0;

    /**
     * @param  array<string, int>  $worstStarterByGroup  position_group → worst projected starter's next_season_overall
     * @param  array<string, int>  $groupSizes           position_group → players available next season in the group
     * @param  array<string, int>  $formationNeeds       position_group → starters required by the formation
     */
    public function __construct(
        public array $worstStarterByGroup,
        public array $groupSizes,
        public array $formationNeeds,
    ) {}

    public static function fromProjection(array $projection, Formation $formation): self
    {
        $available = NextSeasonProjectionService::availablePool($projection);
        $needs = $formation->requirements();

        $worstStarter = [];
        $sizes = [];

        foreach ($needs as $group => $need) {
            $inGroup = $available
                ->where('position_group', $group)
                ->sortByDesc('next_season_overall')
                ->values();

            $sizes[$group] = $inGroup->count();

            if ($inGroup->isEmpty()) {
                continue;
            }

            $worstStarter[$group] = (int) $inGroup->take($need)->min('next_season_overall');
        }

        return new self(
            worstStarterByGroup: $worstStarter,
            groupSizes: $sizes,
            formationNeeds: $needs,
        );
    }

    /**
     * Worst projected starter's overall in the player's position group, or
     * null if no players are available there (the group is fully empty).
     */
    public function worstStarterFor(GamePlayer $player): ?int
    {
        return $this->worstStarterByGroup[$player->position_group] ?? null;
    }

    /**
     * How far the player projects below the worst starter in their group.
     * Positive = below starter level. Zero or negative = at or above.
     * Null when the group has no available starters to benchmark against.
     */
    public function gapToWorstStarter(GamePlayer $player): ?int
    {
        $reference = $this->worstStarterFor($player);
        if ($reference === null) {
            return null;
        }
        $projected = (int) ($player->next_season_overall ?? $player->overall_score);

        return $reference - $projected;
    }

    /**
     * Player projects at or essentially at starter level — losing them is
     * felt because they'd compete for the XI next season.
     *
     * When the group has no projected starters at all the gap is undefined;
     * we treat the loss as impactful since *any* departure from an already
     * empty group makes the squad worse.
     */
    public function isImpactfulLoss(GamePlayer $player): bool
    {
        $gap = $this->gapToWorstStarter($player);
        if ($gap === null) {
            return true;
        }

        return $gap <= self::IMPACTFUL_LOSS_SLACK;
    }

    /**
     * Surplus over the formation's needs in this group — i.e. how many
     * bench bodies the group has beyond the starting XI requirement.
     */
    public function groupSurplus(GamePlayer $player): int
    {
        $size = $this->groupSizes[$player->position_group] ?? 0;
        $need = $this->formationNeeds[$player->position_group] ?? 0;

        return $size - $need;
    }
}
