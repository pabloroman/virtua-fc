<?php

namespace App\Modules\Manager\Services;

use App\Models\Game;
use App\Modules\Manager\ManagerReputation;

/**
 * Maintains the per-game manager reputation stat that lets pro-manager
 * careers progress faster than the slow real-world cadence of club
 * reputation growth.
 *
 * The model:
 *
 *   - Reputation accumulates from season-end performance signals (final
 *     grade, promotion, cup/European trophies).
 *   - One season's delta is capped so even a "perfect" year can only
 *     lift a manager by one tier (~one step on the local..elite ladder),
 *     keeping the Bielsa-style arc at a realistic 4–6 season climb.
 *   - JobOfferService treats the resulting tier as a parallel anchor to
 *     the current club's prestige rank — see prestigeRank() there — so a
 *     manager who out-grew a small club starts fielding offers from
 *     clubs above the current club's band rather than being capped by it.
 */
class ManagerReputationService
{
    /**
     * Apply the end-of-season delta for the just-closed season to the
     * game's manager_reputation_points. Caller is responsible for
     * idempotency — typically gated by Game::season_offers_generated_for
     * inside JobOfferService::ensureEndOfSeasonOffersGenerated, where
     * this runs alongside offer generation in a single transaction.
     *
     * Returns the points after the delta has been applied (for callers
     * that want to log/audit the new value without re-reading).
     *
     * @param  array<string, mixed>  $evaluation Output of SeasonGoalService::evaluatePerformance
     */
    public function applySeasonOutcome(Game $game, array $evaluation): int
    {
        $delta = $this->computeDelta($evaluation);

        $current = (int) ($game->manager_reputation_points ?? 0);
        $next = max(ManagerReputation::MIN_POINTS, $current + $delta);

        $game->update(['manager_reputation_points' => $next]);

        return $next;
    }

    /**
     * Compute the points delta for a season's performance. Exposed for
     * tests and previews (e.g. surfacing the prospective change on the
     * end-of-season screen) without mutating any state.
     *
     * @param  array<string, mixed>  $evaluation
     */
    public function computeDelta(array $evaluation): int
    {
        $grade = $evaluation['grade'] ?? 'met';
        $promoted = (bool) ($evaluation['promoted'] ?? false);
        $trophyBoost = max(0, (int) ($evaluation['trophyBoostSteps'] ?? 0));

        // Base delta by final grade. Designed so a "met" season produces a
        // small positive drift — the manager is still learning, even an
        // average season is a year of experience — while disaster firings
        // bite hard but never wipe out the cumulative career stock thanks
        // to MAX_SEASONAL_LOSS below.
        $base = match ($grade) {
            'exceptional' => 35,
            'exceeded'    => 20,
            'met'         => 5,
            'below'       => -15,
            'disaster'    => -30,
            default       => 0,
        };

        // Promotion is a one-shot career milestone — additive so a Tier-3
        // exceeded-and-promoted season (the Lorca/Almería pattern) clears
        // the local→modest threshold in a single year.
        $promotionBonus = $promoted ? 15 : 0;

        // Each cup/European boost step is worth a domestic-cup-final's
        // worth of personal reputation. SeasonGoalService weights European
        // wins as 2 steps (vs 1 for domestic), so a Champions League win
        // is worth 2× a Copa del Rey here without us re-encoding the rule.
        $trophyBonus = $trophyBoost * 12;

        $delta = $base + $promotionBonus + $trophyBonus;

        // Asymmetric caps. The upper cap is the more important guardrail:
        // it stops a Champions-League-winning, promoted, exceptional season
        // from rocketing the manager 2 tiers in one year.
        if ($delta > 0) {
            return min($delta, ManagerReputation::MAX_SEASONAL_GAIN);
        }

        return max($delta, -ManagerReputation::MAX_SEASONAL_LOSS);
    }
}
