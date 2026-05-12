<?php

namespace App\Modules\Squad\Enums;

/**
 * Severity tier for a Squad Planner advisory. Drives both sort order and
 * the dot color rendered by the `<x-tip-list>` component.
 */
enum AdvisorySeverity: string
{
    case INFO = 'info';
    case WARN = 'warn';
    case CRITICAL = 'critical';

    /**
     * Stable sort weight — lower values bubble to the top of the panel.
     */
    public function sortWeight(): int
    {
        return match ($this) {
            self::CRITICAL => 0,
            self::WARN => 1,
            self::INFO => 2,
        };
    }

    /**
     * Map to the `type` token consumed by `<x-tip-list>`. The component's
     * vocabulary is `info|warning|danger`, which deliberately differs from
     * our domain severities — translate at the boundary.
     */
    public function tipType(): string
    {
        return match ($this) {
            self::CRITICAL => 'danger',
            self::WARN => 'warning',
            self::INFO => 'info',
        };
    }
}
