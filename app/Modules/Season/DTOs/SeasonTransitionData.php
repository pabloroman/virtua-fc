<?php

namespace App\Modules\Season\DTOs;

/**
 * Data transfer object passed between season end processors.
 */
final class SeasonTransitionData
{
    public const META_SWISS_POT_DATA = 'swissPotData';

    public function __construct(
        public readonly string $oldSeason,
        public readonly string $newSeason,
        public string $competitionId,
        public readonly bool $isInitialSeason = false,
        public array $playerChanges = [],
        public array $metadata = [],
    ) {}

    /**
     * Add player development changes.
     */
    public function addPlayerChanges(array $changes): self
    {
        $this->playerChanges = array_merge($this->playerChanges, $changes);
        return $this;
    }

    /**
     * Set a metadata value.
     */
    public function setMetadata(string $key, mixed $value): self
    {
        $this->metadata[$key] = $value;
        return $this;
    }

    /**
     * Get a metadata value.
     */
    public function getMetadata(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }
}
