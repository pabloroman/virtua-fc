<?php

namespace App\Modules\Stadium\Enums;

/**
 * Why a UEFA category upgrade can't be committed right now.
 *
 *   ActiveProject  — another stadium project is already in flight
 *   AlreadyMax     — stadium is already at the top UEFA category
 *   CapacityFloor  — current capacity sits below the next category's
 *                    UEFA-mandated minimum
 *   NoBaseLevel    — the stadium has no UEFA category at all (placeholder
 *                    team / sub-200-seat ground)
 */
enum UefaUpgradeBlocker: string
{
    case ActiveProject = 'active_project';
    case AlreadyMax = 'already_max';
    case CapacityFloor = 'capacity_floor';
    case NoBaseLevel = 'no_base_level';

    /** Translation key for the InvalidArgumentException message. */
    public function messageKey(): string
    {
        return match ($this) {
            self::ActiveProject => 'messages.stadium_active_project_exists',
            self::AlreadyMax    => 'messages.stadium_uefa_already_max',
            self::CapacityFloor => 'messages.stadium_uefa_capacity_floor',
            self::NoBaseLevel   => 'messages.stadium_uefa_no_base_level',
        };
    }
}
