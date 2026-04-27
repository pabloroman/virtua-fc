<?php

namespace App\Modules\Squad\Exceptions;

use RuntimeException;

/**
 * Base exception for squad-minimum guard failures. Carries the structured
 * payload from SquadMinimumService::validateRemoval so the calling Action
 * can format an action-specific user-facing message.
 *
 * Concrete subclasses (one per flow) let callers catch only the relevant
 * one without false positives from unrelated guards.
 */
class SquadMinimumNotMetException extends RuntimeException
{
    /**
     * @param array{type: string, min: int, group?: string} $payload
     */
    public function __construct(
        public readonly array $payload,
        string $message = 'Squad minimum not met.',
    ) {
        parent::__construct($message);
    }

    public function type(): string
    {
        return $this->payload['type'];
    }

    public function min(): int
    {
        return $this->payload['min'];
    }

    public function group(): ?string
    {
        return $this->payload['group'] ?? null;
    }
}
