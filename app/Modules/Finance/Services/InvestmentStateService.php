<?php

namespace App\Modules\Finance\Services;

use App\Models\Game;
use App\Models\GameInvestment;

/**
 * Resolves how freely the manager may change investment, and records downgrades
 * that take effect next season.
 *
 * The plan is fully editable in pre-season; once the first competitive match is
 * played the season's commitment stands. From then on the manager can invest
 * *more* at any time (handled by InfrastructureUpgradeService, full cost), but a
 * reduction is never clawed back — it is staged here and applied as the starting
 * point for the next season (consumed by BudgetAllocationService::applyDefaultAllocation).
 */
class InvestmentStateService
{
    public const STATE_PRE_SEASON = 'pre_season';
    public const STATE_LOCKED = 'locked';

    private const VALID_AREAS = ['youth_academy', 'medical', 'scouting'];

    /**
     * PRE_SEASON = free two-way editing; LOCKED = upgrade-only, downgrades staged.
     */
    public function resolveState(Game $game): string
    {
        return $game->isInPreSeason()
            ? self::STATE_PRE_SEASON
            : self::STATE_LOCKED;
    }

    public function isEditableFreely(Game $game): bool
    {
        return $this->resolveState($game) === self::STATE_PRE_SEASON;
    }

    /**
     * Stage a downgrade to take effect at next season's setup. Re-staging
     * overwrites the previous request for that area.
     *
     * @throws \InvalidArgumentException
     */
    public function stageDowngrade(Game $game, string $area, int $targetTier): GameInvestment
    {
        if (! in_array($area, self::VALID_AREAS, true)) {
            throw new \InvalidArgumentException(__('messages.infrastructure_upgrade_invalid_area'));
        }

        $investment = $game->currentInvestment;
        if (! $investment) {
            throw new \InvalidArgumentException(__('messages.budget_no_projections'));
        }

        $minimumTier = GameInvestment::minimumTierForCompetitionTier((int) ($game->competition->tier ?? 1));
        if ($targetTier < $minimumTier) {
            throw new \InvalidArgumentException(__('messages.budget_minimum_tier'));
        }

        if ($targetTier >= $investment->{"{$area}_tier"}) {
            throw new \InvalidArgumentException(__('messages.investment_downgrade_not_lower'));
        }

        $staged = $investment->staged_downgrades ?? [];
        $staged[$area] = $targetTier;
        $investment->update(['staged_downgrades' => $staged]);

        return $investment->fresh();
    }

    /**
     * Cancel a staged downgrade for an area, restoring "no change next season".
     */
    public function clearStagedDowngrade(Game $game, string $area): GameInvestment
    {
        $investment = $game->currentInvestment;
        if (! $investment) {
            throw new \InvalidArgumentException(__('messages.budget_no_projections'));
        }

        $staged = $investment->staged_downgrades ?? [];
        unset($staged[$area]);
        $investment->update(['staged_downgrades' => $staged === [] ? null : $staged]);

        return $investment->fresh();
    }
}
