<?php

namespace App\Modules\Squad\Services;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Modules\Player\PlayerAge;
use App\Modules\Squad\Enums\SquadAction;
use App\Modules\Squad\Enums\SquadRole;
use Illuminate\Support\Collection;

/**
 * Recommends a single action verb (Play often / Develop / Keep / Renew /
 * List / Replace) per player on the planner. Reads `squad_role` written
 * by PlayerSquadRoleClassifier and the contract / wage state on the player.
 *
 * Phase 3: emits passive labels; no deeplinks yet. Phase 6 will wire the
 * UI side of each action to the corresponding existing flow.
 */
class SquadActionRecommender
{
    /**
     * A player is "close to first-team level" if their potential gap is small
     * relative to their current ability — promising but not yet a finished article.
     */
    private const READY_FOR_MINUTES_GAP = 8;

    /**
     * Reserves below this ability are usually not worth listing — no market.
     */
    private const LIST_MIN_OVERALL = 60;

    /**
     * A departing player counts as a real loss when their projected next-season
     * ability is still useful to the senior squad. Below this we let the
     * departure pass without a "Replace" nudge — slot already has cover or
     * the player no longer contributes enough to feel.
     */
    private const IMPACTFUL_LOSS_MIN_OVERALL = 72;

    /**
     * Younger contract-expiring players whose ceiling is still well above their
     * current ability are worth a renewal offer even when ovr is modest — they
     * can be a cheap development project rather than walking for free.
     */
    private const RENEWABLE_YOUTH_POTENTIAL_GAP = 10;

    /**
     * Recommend an action for every player in the projection and write a
     * `squad_action` attribute. Returns the projection unchanged in shape.
     */
    public function recommend(array $projection, Game $game): array
    {
        $players = collect()
            ->merge($projection['staying']['goalkeepers'])
            ->merge($projection['staying']['defenders'])
            ->merge($projection['staying']['midfielders'])
            ->merge($projection['staying']['forwards'])
            ->merge($projection['incoming'])
            ->merge($projection['outgoing']);

        foreach ($players as $player) {
            $player->setAttribute('squad_action', $this->recommendOne($player, $game));
        }

        return $projection;
    }

    private function recommendOne(GamePlayer $player, Game $game): ?SquadAction
    {
        /** @var SquadRole|null $role */
        $role = $player->squad_role ?? null;

        if ($role === null) {
            return null;
        }

        // DEPARTING — split by *why* the player is leaving. Contract expiring
        // unrenewed is still fixable (you can offer a new deal); retirement
        // and already-signed exits aren't. Within each branch, only surface
        // an action when there's something useful to do.
        if ($role === SquadRole::DEPARTING) {
            return $this->recommendForDeparting($player, $game);
        }

        $contractCritical = $player->contractNeedsAttention($game->current_date);

        return match ($role) {
            // Wonderkids ready for the senior XI need real minutes to keep
            // growing; the not-yet-ready ones just stay in-house (KEEP =
            // hidden chip — the role badge sparkle already signals the
            // long-term investment).
            SquadRole::WONDERKID => $this->isReadyForMinutes($player)
                ? SquadAction::PLAY_OFTEN
                : SquadAction::KEEP,
            // Prospects benefit from real matches at a lower level — loan
            // them out to a club where they'll play every week.
            SquadRole::PROSPECT => SquadAction::LOAN_OUT,
            SquadRole::KEY_PLAYER, SquadRole::FIRST_TEAM => $contractCritical
                ? SquadAction::RENEW
                : SquadAction::KEEP,
            SquadRole::ROTATION => $contractCritical
                ? SquadAction::RENEW
                : SquadAction::KEEP,
            SquadRole::RESERVES => $this->isMarketable($player)
                ? SquadAction::LIST
                : SquadAction::KEEP,
            default => null,
        };
    }

    /**
     * Pick the right action for a DEPARTING player. The reason matters:
     *
     *  - CONTRACT_EXPIRING_UNRENEWED is the only path the manager can still
     *    influence — RENEW if they're young with room to grow, REPLACE if
     *    they were a real contributor, otherwise no nudge (let them walk).
     *  - Retiring / transfer agreed / pre-contract elsewhere are locked in:
     *    we only flag REPLACE when the loss actually hurts.
     */
    private function recommendForDeparting(GamePlayer $player, Game $game): ?SquadAction
    {
        $reason = $player->next_season_reason ?? null;

        if ($reason === NextSeasonProjectionService::REASON_CONTRACT_EXPIRING_UNRENEWED) {
            if ($this->isRenewableYouth($player)) {
                return SquadAction::RENEW;
            }
            return $this->isImpactfulLoss($player) ? SquadAction::REPLACE : null;
        }

        return $this->isImpactfulLoss($player) ? SquadAction::REPLACE : null;
    }

    /**
     * "Impactful loss" means the user would feel the player's absence —
     * the player still contributes meaningful quality to the senior squad.
     *
     * Pure ability-based now: tier captures past stature, not current value,
     * so an aging tier-4 player whose overall has fallen below the cutoff
     * gets no replace nudge ("just let them go" speaks for itself).
     */
    private function isImpactfulLoss(GamePlayer $player): bool
    {
        return $player->overall_score >= self::IMPACTFUL_LOSS_MIN_OVERALL;
    }

    /**
     * A young player whose ceiling is well above today's ability is worth
     * offering a renewal to, even at a modest current overall — they're a
     * cheap development project rather than a free-agent loss.
     */
    private function isRenewableYouth(GamePlayer $player): bool
    {
        $age = $player->next_season_age ?? PlayerAge::PRIME_END + 1;
        if ($age > PlayerAge::YOUNG_END) {
            return false;
        }

        $potential = $player->potential ?? $player->overall_score;
        $gap = $potential - $player->overall_score;

        return $gap >= self::RENEWABLE_YOUTH_POTENTIAL_GAP;
    }

    /**
     * A wonderkid or prospect is "ready for minutes" when their current ability
     * is close to their potential ceiling — they've learned what they can in
     * training and need real game time to keep growing.
     */
    private function isReadyForMinutes(GamePlayer $player): bool
    {
        $potential = $player->potential ?? $player->overall_score;
        $gap = max(0, $potential - $player->overall_score);

        return $gap <= self::READY_FOR_MINUTES_GAP || $player->overall_score >= 75;
    }

    /**
     * A reserve is "marketable" when they have enough quality to attract bids
     * and aren't already prime academy fodder (those go to DEVELOP, not LIST).
     */
    private function isMarketable(GamePlayer $player): bool
    {
        return $player->overall_score >= self::LIST_MIN_OVERALL
            && ! PlayerAge::isYoung($player->next_season_age ?? 99);
    }
}
