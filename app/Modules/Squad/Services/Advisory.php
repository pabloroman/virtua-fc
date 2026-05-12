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
    public const CATEGORY_QUALITY = 'quality';
    public const CATEGORY_DEVELOPMENT = 'development';
    public const CATEGORY_DEPARTURE = 'departure';

    public function __construct(
        public string $severity,
        public string $category,
        public string $message,
    ) {}

    /**
     * Adapt this advisory for `<x-tip-list>` consumption. The component reads
     * a flat `['type' => ..., 'message' => ...]` shape; mapping severity →
     * dot color stays in this domain class rather than leaking into Blade.
     */
    public function toTip(): array
    {
        return [
            'type' => match ($this->severity) {
                self::SEVERITY_CRITICAL => 'danger',
                self::SEVERITY_WARN => 'warning',
                default => 'info',
            },
            'message' => $this->message,
        ];
    }
}
