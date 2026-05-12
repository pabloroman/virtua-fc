<?php

namespace App\Modules\Squad\DTOs;

use App\Modules\Squad\Enums\AdvisoryCategory;
use App\Modules\Squad\Enums\AdvisorySeverity;

/**
 * One bullet on the planner's Transfer Recommendations panel.
 *
 * Severity drives the visual tone and ordering; category lets the UI group
 * or filter (e.g., "show me only wage-cliff issues") without parsing the
 * message.
 */
final readonly class Advisory
{
    public function __construct(
        public AdvisorySeverity $severity,
        public AdvisoryCategory $category,
        public string $message,
    ) {}

    /**
     * Adapt this advisory for `<x-tip-list>` consumption. The component reads
     * a flat `['type' => ..., 'message' => ...]` shape and uses its own
     * vocabulary for severity — translate at the boundary so the mapping
     * doesn't leak into Blade.
     */
    public function toTip(): array
    {
        return [
            'type' => $this->severity->tipType(),
            'message' => $this->message,
        ];
    }
}
