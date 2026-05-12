<?php

namespace App\Modules\Squad\Enums;

/**
 * Coarse position group used across the squad-planner surfaces.
 *
 * Values match `GamePlayer::$position_group`, which is the long-standing
 * string convention across the rest of the codebase — the enum is the
 * source of truth for the planner code without forcing a project-wide
 * migration of every `position_group` string usage.
 */
enum PositionGroup: string
{
    case GOALKEEPER = 'Goalkeeper';
    case DEFENDER = 'Defender';
    case MIDFIELDER = 'Midfielder';
    case FORWARD = 'Forward';

    /**
     * Plural slug used as array keys in the projection payload
     * (e.g. `staying['goalkeepers']`) and matching translation keys.
     */
    public function pluralKey(): string
    {
        return match ($this) {
            self::GOALKEEPER => 'goalkeepers',
            self::DEFENDER => 'defenders',
            self::MIDFIELDER => 'midfielders',
            self::FORWARD => 'forwards',
        };
    }

    public function pluralLabel(): string
    {
        return __('planner.' . $this->pluralKey());
    }

    /**
     * Singular, lowercase label used inside advisory sentences
     * ("Reinforce defence — 2 short of the formation.").
     */
    public function advisoryLabel(): string
    {
        return match ($this) {
            self::GOALKEEPER => __('planner.group_goalkeeper'),
            self::DEFENDER => __('planner.group_defender'),
            self::MIDFIELDER => __('planner.group_midfielder'),
            self::FORWARD => __('planner.group_forward'),
        };
    }
}
