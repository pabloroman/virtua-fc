<?php

namespace App\Modules\Competition\Promotions;

/**
 * Outcome of a {@see ReserveParentCoexistenceRepairer} run.
 *
 * Carries the detected issues and (for a Repaired result) the resolved
 * mutation descriptors — each a swap plus the two located standings/sim slots —
 * so apply() can execute them and console/alert callers can describe them.
 * The Unsafe case carries a human-readable reason instead of throwing, so the
 * processor can distinguish "could not safely heal" from "fixed".
 */
final class ReserveRepairResult
{
    /**
     * @param  list<array{kind: string, reserve: array<string, mixed>, parent: array<string, mixed>}>  $issues
     * @param  list<array{swap: array<string, mixed>, slotA: array<string, mixed>, slotB: array<string, mixed>}>  $mutations
     */
    private function __construct(
        public readonly RepairOutcome $outcome,
        public readonly array $issues = [],
        public readonly array $mutations = [],
        public readonly ?string $reason = null,
    ) {}

    public static function nothingToFix(): self
    {
        return new self(RepairOutcome::NothingToFix);
    }

    /**
     * @param  list<array{kind: string, reserve: array<string, mixed>, parent: array<string, mixed>}>  $issues
     */
    public static function unsafe(string $reason, array $issues = []): self
    {
        return new self(RepairOutcome::Unsafe, $issues, [], $reason);
    }

    /**
     * @param  list<array{kind: string, reserve: array<string, mixed>, parent: array<string, mixed>}>  $issues
     * @param  list<array{swap: array<string, mixed>, slotA: array<string, mixed>, slotB: array<string, mixed>}>  $mutations
     */
    public static function repaired(array $issues, array $mutations): self
    {
        return new self(RepairOutcome::Repaired, $issues, $mutations);
    }

    /**
     * Human-readable one-line summary per planned/applied swap, for logs/alerts.
     *
     * @return list<string>
     */
    public function swapSummaries(): array
    {
        return array_map(fn (array $m) => (string) $m['swap']['reason'], $this->mutations);
    }
}
