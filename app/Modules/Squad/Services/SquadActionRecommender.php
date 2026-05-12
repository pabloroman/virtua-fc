<?php

namespace App\Modules\Squad\Services;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Modules\Player\PlayerAge;
use App\Modules\Squad\DTOs\SquadContext;
use App\Modules\Squad\Enums\SquadAction;
use App\Modules\Squad\Enums\SquadRole;

/**
 * Recommends a single action verb (Play often / Develop / Keep / Renew /
 * List / Replace) per player on the planner. Reads `squad_role` written
 * by PlayerSquadRoleClassifier and the contract / wage state on the player,
 * and benchmarks ability against the rest of the squad via SquadContext —
 * so the advice is relative to the actual roster instead of absolute
 * overall numbers that misfire on squads at the extremes.
 */
class SquadActionRecommender
{
    /**
     * A wonderkid/prospect is "close to ready" when their potential gap is
     * small — most of the growth has already happened in training and the
     * rest only comes from real minutes.
     */
    private const READY_FOR_MINUTES_GAP = 8;

    /**
     * Points below the worst projected starter within which a player still
     * counts as "useful depth" — close enough to compete for minutes or
     * step in for injuries. Beyond this they're surplus to requirements.
     */
    private const USEFUL_DEPTH_GAP = 6;

    /**
     * Late-prime players (33–34 next season) need a development gap of at
     * least this many points to be considered worth a renewal — anything
     * less is nominal upside for someone about to hit the veteran cliff,
     * and locks in wages with no real future ceiling.
     */
    private const LATE_PRIME_RENEWAL_GAP = 5;

    /**
     * Recommend an action for every player in the projection and write a
     * `squad_action` attribute. Returns the projection unchanged in shape.
     */
    public function recommend(array $projection, Game $game, SquadContext $context): array
    {
        $players = collect()
            ->merge($projection['staying']['goalkeepers'])
            ->merge($projection['staying']['defenders'])
            ->merge($projection['staying']['midfielders'])
            ->merge($projection['staying']['forwards'])
            ->merge($projection['incoming'])
            ->merge($projection['outgoing']);

        foreach ($players as $player) {
            $player->setAttribute('squad_action', $this->recommendOne($player, $game, $context));
        }

        return $projection;
    }

    private function recommendOne(GamePlayer $player, Game $game, SquadContext $context): ?SquadAction
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
            return $this->recommendForDeparting($player, $game, $context);
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
            SquadRole::RESERVES => $this->isMarketable($player, $context)
                ? SquadAction::LIST
                : SquadAction::KEEP,
            default => null,
        };
    }

    /**
     * Pick the right action for a DEPARTING player.
     *
     * The ability question ("is this loss worth flagging?") and the renewal
     * question ("can we still keep them?") are independent — and renewal
     * wins when both are true, because keeping the existing player is
     * strictly cheaper than scouting a replacement.
     *
     *  - Surplus (not impactful, not useful depth) → no nudge.
     *  - Expiring contract on a renewable player → RENEW (the cheapest way
     *    to address either a starter loss or a depth loss).
     *  - Otherwise (impactful or useful depth, but renewal isn't possible
     *    or isn't sensible) → REPLACE, so the user plans a body for the
     *    slot.
     */
    private function recommendForDeparting(GamePlayer $player, Game $game, SquadContext $context): ?SquadAction
    {
        $isImpactful = $context->isImpactfulLoss($player);
        $isUsefulDepth = ! $isImpactful && $this->isUsefulDepth($player, $context);

        if (! $isImpactful && ! $isUsefulDepth) {
            return null;
        }

        $isExpiring = ($player->next_season_reason ?? null) === NextSeasonProjectionService::REASON_CONTRACT_EXPIRING_UNRENEWED;
        if ($isExpiring && $this->isRenewable($player)) {
            return SquadAction::RENEW;
        }

        return SquadAction::REPLACE;
    }

    /**
     * A departing player is "useful depth" when they project close to (but
     * below) the worst starter — strong bench / first sub. Below the
     * useful-depth gap they're surplus to requirements and don't warrant
     * a per-player nudge.
     */
    private function isUsefulDepth(GamePlayer $player, SquadContext $context): bool
    {
        $gap = $context->gapToWorstStarter($player);

        return $gap !== null && $gap > 0 && $gap <= self::USEFUL_DEPTH_GAP;
    }

    /**
     * A renewal is worth suggesting when the wage commitment buys future
     * seasons of value, not just a final-year hold:
     *
     *  - Veterans (past PRIME_END) → never; declining, short shelf life.
     *  - Late-prime (PRIME_END or PRIME_END - 1) → only with real
     *    development upside; a nominal 1–3 point potential gap at age 33
     *    is noise, not a growth project.
     *  - Younger players in prime or growing → always; cheapest way to keep
     *    a player in their good years.
     */
    private function isRenewable(GamePlayer $player): bool
    {
        $age = $player->next_season_age ?? 0;
        if ($age > PlayerAge::PRIME_END) {
            return false;
        }

        if ($age >= PlayerAge::PRIME_END - 1) {
            $potential = $player->potential ?? $player->overall_score;
            $gap = $potential - $player->overall_score;

            return $gap >= self::LATE_PRIME_RENEWAL_GAP;
        }

        return true;
    }

    /**
     * A wonderkid or prospect is "ready for minutes" when their current
     * ability is close to their potential ceiling — they've learned what
     * they can in training and need real game time to keep growing.
     */
    private function isReadyForMinutes(GamePlayer $player): bool
    {
        $potential = $player->potential ?? $player->overall_score;
        $gap = max(0, $potential - $player->overall_score);

        return $gap <= self::READY_FOR_MINUTES_GAP;
    }

    /**
     * A reserve is "marketable" when:
     *
     *  - They aren't a young academy/prospect piece (those go to DEVELOP).
     *  - The position group has at least one body to spare beyond the
     *    formation's needs — selling the only sub leaves the bench bare.
     *  - They project meaningfully below starter level — surplus to
     *    requirements, not injury cover the squad still needs.
     */
    private function isMarketable(GamePlayer $player, SquadContext $context): bool
    {
        if (PlayerAge::isYoung($player->next_season_age ?? 99)) {
            return false;
        }

        if ($context->groupSurplus($player) < 1) {
            return false;
        }

        $gap = $context->gapToWorstStarter($player);

        return $gap !== null && $gap > self::USEFUL_DEPTH_GAP;
    }
}
