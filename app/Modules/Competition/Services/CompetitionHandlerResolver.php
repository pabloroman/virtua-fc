<?php

namespace App\Modules\Competition\Services;

use App\Modules\Competition\Contracts\CompetitionHandler;
use App\Models\Competition;
use InvalidArgumentException;

class CompetitionHandlerResolver
{
    /**
     * @var array<string, CompetitionHandler>
     */
    private array $handlers = [];

    /**
     * Register a handler for a specific competition type.
     */
    public function register(CompetitionHandler $handler): void
    {
        $this->handlers[$handler->getType()] = $handler;
    }

    /**
     * Resolve the handler for a competition.
     *
     * @throws InvalidArgumentException
     */
    public function resolve(Competition $competition): CompetitionHandler
    {
        $type = $competition->handler_type ?? $competition->type;

        if (!isset($this->handlers[$type])) {
            throw new InvalidArgumentException(
                "No competition handler registered for type: {$type}"
            );
        }

        return $this->handlers[$type];
    }

    /**
     * Get all registered handler types.
     *
     * @return array<string>
     */
    public function getRegisteredTypes(): array
    {
        return array_keys($this->handlers);
    }

    /**
     * Check if a handler is registered for a type.
     */
    public function hasHandler(string $type): bool
    {
        return isset($this->handlers[$type]);
    }
}
