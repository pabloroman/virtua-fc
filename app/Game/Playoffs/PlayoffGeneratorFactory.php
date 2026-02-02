<?php

namespace App\Game\Playoffs;

use App\Game\Contracts\PlayoffGenerator;

class PlayoffGeneratorFactory
{
    public function __construct(
        private ESP2PlayoffGenerator $esp2,
        // Add more generators here as needed:
        // private ENG2PlayoffGenerator $eng2,
    ) {}

    /**
     * Get the playoff generator for a competition.
     */
    public function forCompetition(string $competitionId): ?PlayoffGenerator
    {
        return match ($competitionId) {
            'ESP2' => $this->esp2,
            // 'ENG2' => $this->eng2,
            default => null,
        };
    }

    /**
     * Check if a competition has playoffs configured.
     */
    public function hasPlayoff(string $competitionId): bool
    {
        return $this->forCompetition($competitionId) !== null;
    }

    /**
     * Get all registered playoff generators.
     *
     * @return PlayoffGenerator[]
     */
    public function all(): array
    {
        return array_filter([
            $this->esp2,
            // $this->eng2,
        ]);
    }
}
