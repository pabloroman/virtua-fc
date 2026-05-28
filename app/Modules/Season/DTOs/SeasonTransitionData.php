<?php

namespace App\Modules\Season\DTOs;

/**
 * Data transfer object passed between season end processors.
 */
final class SeasonTransitionData implements \JsonSerializable
{
    public const META_SWISS_POT_DATA = 'swissPotData';
    public const META_UCL_WINNER = 'uclWinner';
    public const META_UEL_WINNER = 'uelWinner';

    /**
     * Set by ApplyPendingTeamSwitchProcessor when a Pro Manager accepted an
     * end-of-season offer and the pipeline just moved the game to the new
     * club. Read by BudgetProjectionProcessor so the new season's projection
     * skips the previous club's carry-overs.
     */
    public const META_PRO_MANAGER_TEAM_SWITCHED = 'proManagerTeamSwitched';

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

    /**
     * Serialize to JSON for checkpoint persistence.
     */
    public function jsonSerialize(): array
    {
        return [
            'oldSeason' => $this->oldSeason,
            'newSeason' => $this->newSeason,
            'competitionId' => $this->competitionId,
            'isInitialSeason' => $this->isInitialSeason,
            'playerChanges' => $this->playerChanges,
            'metadata' => $this->metadata,
        ];
    }
}
