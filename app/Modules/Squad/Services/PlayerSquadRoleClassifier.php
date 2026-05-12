<?php

namespace App\Modules\Squad\Services;

use App\Models\GamePlayer;
use App\Modules\Lineup\Enums\Formation;
use App\Modules\Lineup\Services\FormationRecommender;
use App\Modules\Player\PlayerAge;
use App\Modules\Squad\Enums\SquadRole;
use Illuminate\Support\Collection;

/**
 * Classifies each projected next-season player into a SquadRole and writes a
 * short auto-generated blurb describing their place in the team.
 *
 * Pure (no DB writes). Operates on the enriched players produced by
 * NextSeasonProjectionService — relies on `next_season_status`,
 * `next_season_age`, and `next_season_overall` already being set.
 */
class PlayerSquadRoleClassifier
{
    /**
     * Players ranked above this percentile in their position group count as
     * "above the median" rotation candidates rather than reserves.
     */
    private const ROTATION_PERCENTILE = 0.60;

    /**
     * Top-K in a position group qualifies for KEY_PLAYER on its own.
     */
    private const KEY_PLAYER_TOP_K = 2;

    /**
     * Minimum ability gap (potential - current) for WONDERKID.
     */
    private const WONDERKID_GAP = 15;

    /**
     * Minimum tier to qualify for KEY_PLAYER on tier alone.
     */
    private const KEY_PLAYER_TIER = 4;

    /**
     * Minimum overall_score to qualify for KEY_PLAYER on ability alone.
     */
    private const KEY_PLAYER_OVERALL = 80;

    public function __construct(
        private readonly FormationRecommender $formationRecommender,
    ) {}

    /**
     * Classify every projected player and write `squad_role` + `squad_blurb`
     * attributes onto each model. Returns the same buckets it received so
     * callers can keep their existing shape.
     *
     * @param  array{
     *     staying: array{goalkeepers: Collection, defenders: Collection, midfielders: Collection, forwards: Collection},
     *     outgoing: Collection,
     *     incoming: Collection,
     *     ...
     * }  $projection
     */
    public function classify(array $projection, ?Formation $formation = null): array
    {
        $incoming = $projection['incoming'];
        $outgoing = $projection['outgoing'];

        // Pool of players who will actually be available next season — used for
        // best XI and peer ranking. STILL_ON_LOAN players are owned but won't be
        // training with the squad, so they're excluded from the XI competition.
        $available = NextSeasonProjectionService::availablePool($projection);

        // STAYING flat collection — used to iterate every owned player for the
        // role assignment loop, with peer ranking provided by `$available`.
        $staying = collect()
            ->merge($projection['staying']['goalkeepers'])
            ->merge($projection['staying']['defenders'])
            ->merge($projection['staying']['midfielders'])
            ->merge($projection['staying']['forwards']);

        $bestXIIds = $this->resolveBestXIIds($available, $formation);
        $rankingByGroup = $this->buildPositionGroupRankings($available);

        foreach ($staying as $player) {
            $role = $this->classifyOne($player, $bestXIIds, $rankingByGroup);
            $player->setAttribute('squad_role', $role);
            $player->setAttribute('squad_blurb', $this->buildBlurb($role));
        }

        foreach ($incoming as $player) {
            $role = $this->classifyOne($player, $bestXIIds, $rankingByGroup);
            $player->setAttribute('squad_role', $role);
            $player->setAttribute('squad_blurb', $this->buildBlurb($role));
        }

        foreach ($outgoing as $player) {
            $player->setAttribute('squad_role', SquadRole::DEPARTING);
            $player->setAttribute('squad_blurb', $this->buildBlurb(SquadRole::DEPARTING));
        }

        return $projection;
    }

    private function classifyOne(GamePlayer $player, array $bestXIIds, array $rankingByGroup): SquadRole
    {
        if ($player->next_season_status === NextSeasonProjectionService::STATUS_OUTGOING) {
            return SquadRole::DEPARTING;
        }

        $age = $player->next_season_age;
        $overall = $player->overall_score;
        $potential = $player->potential ?? $overall;
        $gap = max(0, $potential - $overall);
        $group = $player->position_group;
        $ranking = $rankingByGroup[$group] ?? [];
        $rankInGroup = $ranking[$player->id]['rank'] ?? null;
        $groupSize = $ranking[$player->id]['size'] ?? 1;
        $percentile = $rankInGroup === null ? 1.0 : ($rankInGroup / max(1, $groupSize));

        $inBestXI = isset($bestXIIds[$player->id]);

        // 1. WONDERKID — young, big upside, top-tier potential in the group.
        if (PlayerAge::isYoung($age) && $gap >= self::WONDERKID_GAP && $percentile <= 0.30) {
            return SquadRole::WONDERKID;
        }

        // 2. KEY PLAYER — established quality regardless of formation fit.
        if ($overall >= self::KEY_PLAYER_OVERALL
            || $player->tier >= self::KEY_PLAYER_TIER
            || $rankInGroup !== null && $rankInGroup <= self::KEY_PLAYER_TOP_K
        ) {
            return SquadRole::KEY_PLAYER;
        }

        // 3. FIRST TEAM — chosen by the formation's best XI.
        if ($inBestXI) {
            return SquadRole::FIRST_TEAM;
        }

        // 4. PROSPECT — young, not yet in the XI but has room to grow.
        if ($age <= PlayerAge::ACADEMY_END && $potential > $overall) {
            return SquadRole::PROSPECT;
        }

        // 5. ROTATION — above the position-group median.
        if ($percentile <= self::ROTATION_PERCENTILE) {
            return SquadRole::ROTATION;
        }

        return SquadRole::RESERVES;
    }

    /**
     * Resolve the projected best XI: returns a [playerId => true] lookup.
     */
    private function resolveBestXIIds(Collection $available, ?Formation $formation): array
    {
        if ($available->isEmpty()) {
            return [];
        }

        $formation ??= $this->formationRecommender->getBestFormation($available);
        $bestXI = $this->formationRecommender->bestXIFor($formation, $available);

        $ids = [];
        foreach ($bestXI as $slot) {
            $playerId = $slot['player']['id'] ?? null;
            if ($playerId !== null) {
                $ids[$playerId] = true;
            }
        }

        return $ids;
    }

    /**
     * Per-position-group ranking by overall_score (1 = best). Returns a map
     * keyed by player id with their rank and the group size.
     *
     * @return array<string, array<string, array{rank: int, size: int}>>
     *         Outer key is position group name; inner key is player id.
     */
    private function buildPositionGroupRankings(Collection $available): array
    {
        $rankings = [];
        $grouped = $available->groupBy(fn (GamePlayer $p) => $p->position_group);

        foreach ($grouped as $group => $players) {
            $ordered = $players->sortByDesc('overall_score')->values();
            $size = $ordered->count();
            foreach ($ordered as $index => $player) {
                $rankings[$group][$player->id] = [
                    'rank' => $index + 1,
                    'size' => $size,
                ];
            }
        }

        return $rankings;
    }

    /**
     * Build a short auto-generated blurb describing the player's place.
     *
     * Blurbs are role-driven first; we pass the player's localized position
     * name so the copy reads naturally ("Squad depth at goalkeeper" vs the
     * raw enum value).
     */
    private function buildBlurb(SquadRole $role): string
    {
        return match ($role) {
            SquadRole::WONDERKID => __('planner.blurb_wonderkid'),
            SquadRole::KEY_PLAYER => __('planner.blurb_key_player'),
            SquadRole::FIRST_TEAM => __('planner.blurb_first_team'),
            SquadRole::PROSPECT => __('planner.blurb_prospect'),
            SquadRole::ROTATION => __('planner.blurb_rotation'),
            SquadRole::RESERVES => __('planner.blurb_reserves'),
            SquadRole::DEPARTING => __('planner.blurb_departing'),
        };
    }
}
