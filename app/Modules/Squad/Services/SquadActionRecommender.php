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

        // DEPARTING — only surface a "Replace" prompt for players the user will
        // actually miss. Squad fillers leaving need no advice.
        if ($role === SquadRole::DEPARTING) {
            return $this->isImpactfulLoss($player, $game) ? SquadAction::REPLACE : null;
        }

        $contractCritical = $this->contractNeedsAttention($player, $game);

        return match ($role) {
            SquadRole::WONDERKID => $this->isReadyForMinutes($player)
                ? SquadAction::PLAY_OFTEN
                : SquadAction::DEVELOP,
            SquadRole::PROSPECT => SquadAction::DEVELOP,
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
     * "Impactful loss" means the user would feel the player's absence — a key
     * player or first-teamer leaving. Reserves retiring don't need a replace
     * nudge; the slot already has cover.
     *
     * We look at the player's current `overall_score` since DEPARTING players
     * skip the role-tier classifier paths that would have flagged them as KEY.
     */
    private function isImpactfulLoss(GamePlayer $player, Game $game): bool
    {
        return $player->overall_score >= 75
            || $player->tier >= 4
            || ! PlayerAge::isVeteran($player->age($game->current_date));
    }

    /**
     * Contract attention is warranted when the player's deal expires at the
     * end of the *upcoming* season and no renewal or pre-contract elsewhere
     * has been agreed yet.
     */
    private function contractNeedsAttention(GamePlayer $player, Game $game): bool
    {
        if (! $player->contract_until) {
            return false;
        }

        if ($player->hasRenewalAgreed() || $player->hasPreContractAgreement()) {
            return false;
        }

        // Roughly "expires within the next ~14 months" — covers both
        // current-season-end and next-season-end contracts so the user gets a
        // heads-up before the pre-contract window opens.
        $cutoff = $game->current_date->copy()->addMonths(14);

        return $player->contract_until->lte($cutoff);
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
