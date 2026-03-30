<?php

namespace App\Modules\Match\DTOs;

/**
 * Bundles a match simulation result with per-player performance modifiers.
 * Eliminates the need to retrieve performances from MatchSimulator instance state.
 */
readonly class MatchSimulationOutput
{
    /**
     * @param  MatchResult  $result  The match result (scores, events, possession)
     * @param  array<string, float>  $performances  Map of player ID to performance modifier (0.7-1.3)
     */
    public function __construct(
        public MatchResult $result,
        public array $performances,
    ) {}
}
