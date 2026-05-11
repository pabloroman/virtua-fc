<?php

namespace App\Modules\Squad\Enums;

/**
 * Actionable verb the manager could take for a player on the planner.
 *
 * Phase 3 surfaces these as passive labels only. Phase 6 will wire each
 * action to its destination flow (renewal modal, transfer listing, lineup,
 * scouting search).
 */
enum SquadAction: string
{
    case PLAY_OFTEN = 'play_often';
    case DEVELOP = 'develop';
    case KEEP = 'keep';
    case RENEW = 'renew';
    case LIST = 'list';
    case REPLACE = 'replace';

    public function label(): string
    {
        return __('planner.action_' . $this->value);
    }

    public function tone(): string
    {
        return match ($this) {
            self::PLAY_OFTEN => 'bg-accent-green/15 text-accent-green border-accent-green/30',
            self::DEVELOP => 'bg-accent-blue/15 text-accent-blue border-accent-blue/30',
            self::RENEW => 'bg-accent-gold/15 text-accent-gold border-accent-gold/30',
            self::LIST => 'bg-accent-orange/15 text-accent-orange border-accent-orange/30',
            self::REPLACE => 'bg-accent-red/15 text-accent-red border-accent-red/30',
            self::KEEP => 'bg-surface-700 text-text-secondary border-border-strong',
        };
    }
}
