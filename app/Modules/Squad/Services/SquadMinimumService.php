<?php

namespace App\Modules\Squad\Services;

use App\Models\Game;
use App\Models\GamePlayer;

/**
 * Single source of truth for squad-composition minimums (total squad size
 * and per-position-group floors). Used by every flow that can cause a
 * user-owned player to leave a roster — release, call-up, send-back,
 * sale listing, loan-out listing, and offer acceptance — so the same
 * rule is enforced everywhere.
 *
 * Counting is by physical presence (`team_id`) rather than ownership:
 * the minimum protects the playable roster, so loaned-out players
 * (still parent-owned but physically away) do not count toward the
 * source team's minimum.
 */
final class SquadMinimumService
{
    /**
     * Absolute minimum players in a squad — flows that would drop the
     * roster below this are blocked.
     */
    public const MIN_SQUAD_SIZE = 20;

    /**
     * Minimum players per position group. Keys match
     * GamePlayer::position_group values.
     */
    public const POSITION_GROUP_MINIMUMS = [
        'Goalkeeper' => 2,
        'Defender'   => 6,
        'Midfielder' => 6,
        'Forward'    => 4,
    ];

    /**
     * Count physical players on the given team in the given game.
     */
    public function squadCount(Game $game, string $teamId): int
    {
        return GamePlayer::where('game_id', $game->id)
            ->where('team_id', $teamId)
            ->count();
    }

    /**
     * Count physical players in a specific position group on the given team.
     */
    public function positionGroupCount(Game $game, string $teamId, string $positionGroup): int
    {
        return GamePlayer::where('game_id', $game->id)
            ->where('team_id', $teamId)
            ->get()
            ->filter(fn (GamePlayer $p) => $p->position_group === $positionGroup)
            ->count();
    }

    /**
     * Test whether removing $player from $teamId would breach the squad
     * minimum or position-group minimum on that team.
     *
     * Returns null when the removal is allowed, or a structured failure:
     *   ['type' => 'too_small',         'min' => int]
     *   ['type' => 'position_minimum',  'min' => int, 'group' => string]
     *
     * @return array{type: string, min: int, group?: string}|null
     */
    public function validateRemoval(Game $game, GamePlayer $player, string $teamId): ?array
    {
        if ($this->squadCount($game, $teamId) <= self::MIN_SQUAD_SIZE) {
            return ['type' => 'too_small', 'min' => self::MIN_SQUAD_SIZE];
        }

        $positionGroup = $player->position_group;
        $groupMinimum = self::POSITION_GROUP_MINIMUMS[$positionGroup] ?? 0;

        if ($groupMinimum > 0
            && $this->positionGroupCount($game, $teamId, $positionGroup) <= $groupMinimum) {
            return [
                'type'  => 'position_minimum',
                'min'   => $groupMinimum,
                'group' => $positionGroup,
            ];
        }

        return null;
    }
}
