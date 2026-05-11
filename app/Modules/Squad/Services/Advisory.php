<?php

namespace App\Modules\Squad\Services;

/**
 * One bullet on the planner's Transfer Recommendations panel.
 *
 * Severity drives the visual tone; category lets the UI group or filter
 * (e.g., "show me only wage-cliff issues") without parsing the message.
 */
final readonly class Advisory
{
    public const SEVERITY_INFO = 'info';
    public const SEVERITY_WARN = 'warn';
    public const SEVERITY_CRITICAL = 'critical';

    public const CATEGORY_DEPTH = 'depth';
    public const CATEGORY_AGE = 'age';
    public const CATEGORY_WAGE = 'wage';
    public const CATEGORY_DEVELOPMENT = 'development';
    public const CATEGORY_DEPARTURE = 'departure';

    public function __construct(
        public string $severity,
        public string $category,
        public string $message,
    ) {}

    public function tone(): string
    {
        return match ($this->severity) {
            self::SEVERITY_CRITICAL => 'bg-accent-red/10 text-accent-red border-accent-red/30',
            self::SEVERITY_WARN => 'bg-accent-gold/10 text-accent-gold border-accent-gold/30',
            default => 'bg-accent-blue/10 text-accent-blue border-accent-blue/30',
        };
    }
}
