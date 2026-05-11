<?php

namespace App\Modules\Squad\Enums;

/**
 * Squad role assigned by the planner classifier — a verbal verdict on what
 * each player means to the projected next-season squad.
 *
 * The roles are not mutually exclusive in reality (a wonderkid can also be a
 * starter), but the classifier picks one primary label per player so the UI
 * stays readable. Precedence is encoded in PlayerSquadRoleClassifier.
 */
enum SquadRole: string
{
    case WONDERKID = 'wonderkid';
    case KEY_PLAYER = 'key_player';
    case FIRST_TEAM = 'first_team';
    case ROTATION = 'rotation';
    case PROSPECT = 'prospect';
    case RESERVES = 'reserves';
    case DEPARTING = 'departing';

    public function label(): string
    {
        return __('planner.role_' . $this->value);
    }

    /**
     * Semantic Tailwind class fragment for the badge background+border+text.
     * All accents are tokens — no raw colors — so it works in both themes.
     */
    public function tone(): string
    {
        return match ($this) {
            self::WONDERKID => 'bg-accent-gold/15 text-accent-gold border-accent-gold/30',
            self::KEY_PLAYER => 'bg-accent-blue/15 text-accent-blue border-accent-blue/30',
            self::FIRST_TEAM => 'bg-accent-green/15 text-accent-green border-accent-green/30',
            self::ROTATION => 'bg-surface-700 text-text-secondary border-border-strong',
            self::PROSPECT => 'bg-accent-orange/15 text-accent-orange border-accent-orange/30',
            self::RESERVES => 'bg-surface-700 text-text-muted border-border-default',
            self::DEPARTING => 'bg-accent-red/15 text-accent-red border-accent-red/30',
        };
    }
}
