<?php

namespace App\Modules\Transfer\Services;

use App\Models\GamePlayer;
use Illuminate\Support\Collection;

/**
 * Computes how badly an AI club wants a given player — a 0..1 "desire" score
 * blending positional need (squad depth at the player's position group) with
 * quality upgrade (the player's ability vs the buyer's current best there).
 *
 * Stateless and query-free: callers pass an in-memory roster Collection, so the
 * same logic composes into both the interactive counter-offer path
 * (ScoutingService::evaluateCounterOffer) and the per-matchday opening-offer
 * path (TransferService::calculateOfferPrice). Mirrors the AITeamBudgetCalculator
 * precedent (pure compute, no DB access).
 *
 * This is the BUYER-side counterpart to ScoutingService::calculateAskingPrice,
 * which models SELLER reluctance (importance + contract leverage). The two axes
 * are intentionally separate and must not be routed through each other.
 */
class SquadNeedService
{
    /**
     * Ideal squad depth per position group. Single source of truth — also
     * referenced by AITransferMarketService so the user-selling and AI-to-AI
     * paths can't drift.
     */
    public const IDEAL_GROUP_COUNTS = [
        'Goalkeeper' => 3,
        'Defender' => 6,
        'Midfielder' => 6,
        'Forward' => 4,
    ];

    /** Desire blend weights (need + quality + finance == 1.0). */
    private const W_NEED = 0.45;
    private const W_UPGRADE = 0.40;
    private const W_FINANCE = 0.15;

    /**
     * Quality-upgrade mapping. A player whose overall_score sits UPGRADE_OFFSET
     * points above the buyer's current best at the position reads as a small
     * upgrade; UPGRADE_SPAN points above reads as a full (1.0) upgrade. The
     * offset means a marginally-worse player still registers minor squad-depth
     * desire rather than a hard zero.
     */
    private const UPGRADE_OFFSET = 5;
    private const UPGRADE_SPAN = 20;

    /**
     * Position-group deficit for a roster: how many short of the ideal depth,
     * floored at 0.
     *
     * @param  Collection<int, GamePlayer>  $buyerRoster
     */
    public function positionDeficit(Collection $buyerRoster, string $positionGroup): int
    {
        $ideal = self::IDEAL_GROUP_COUNTS[$positionGroup] ?? 4;
        $current = $buyerRoster->filter(
            fn (GamePlayer $p) => $p->position_group === $positionGroup
        )->count();

        return max(0, $ideal - $current);
    }

    /**
     * Highest overall_score among the roster's players in the given group, or
     * null when the buyer has nobody there.
     *
     * @param  Collection<int, GamePlayer>  $buyerRoster
     */
    public function bestInGroup(Collection $buyerRoster, string $positionGroup): ?int
    {
        $inGroup = $buyerRoster->filter(
            fn (GamePlayer $p) => $p->position_group === $positionGroup
        );

        if ($inGroup->isEmpty()) {
            return null;
        }

        return (int) $inGroup->max('overall_score');
    }

    /**
     * Average overall_score across the roster (the buyer's general level).
     *
     * @param  Collection<int, GamePlayer>  $buyerRoster
     */
    public function squadAverageAbility(Collection $buyerRoster): float
    {
        if ($buyerRoster->isEmpty()) {
            return 50.0;
        }

        return (float) $buyerRoster->avg('overall_score');
    }

    /**
     * How much this buyer wants the target player, 0..1.
     *
     * @param  Collection<int, GamePlayer>  $buyerRoster
     * @param  float|null  $financialHeadroom  0..1 spending freedom; null = neutral 0.5.
     */
    public function desireScore(Collection $buyerRoster, GamePlayer $target, ?float $financialHeadroom = null): float
    {
        $group = $target->position_group;
        $ideal = self::IDEAL_GROUP_COUNTS[$group] ?? 4;

        $need = $ideal > 0 ? $this->positionDeficit($buyerRoster, $group) / $ideal : 0.0;

        // Fall back to the squad average when the buyer has nobody in the group,
        // so an empty position still reads as a quality-relevant gap.
        $best = $this->bestInGroup($buyerRoster, $group) ?? $this->squadAverageAbility($buyerRoster);
        $upgrade = $this->clamp(($target->overall_score - $best + self::UPGRADE_OFFSET) / self::UPGRADE_SPAN, 0.0, 1.0);

        $finance = $financialHeadroom ?? 0.5;

        $desire = self::W_NEED * $need
            + self::W_UPGRADE * $upgrade
            + self::W_FINANCE * $finance;

        return $this->clamp($desire, 0.0, 1.0);
    }

    /**
     * Deterministic ±$band jitter derived from a stable seed string (e.g. an
     * offer uuid, or player+buyer+date). Reproducible — the same seed always
     * yields the same value — so negotiation outcomes vary between offers
     * without breaking test determinism. Mirrors the crc32 seeding used in
     * AITeamBudgetCalculator.
     */
    public function jitter(string $seed, float $band): float
    {
        $unit = (crc32($seed) & 0x7FFFFFFF) / 0x7FFFFFFF; // 0..1

        return ($unit * 2.0 - 1.0) * $band; // -band..+band
    }

    private function clamp(float $value, float $min, float $max): float
    {
        return max($min, min($max, $value));
    }
}
