<?php

namespace App\Game\DTO;

/**
 * Data transfer object for a single match event.
 */
readonly class MatchEventData
{
    public function __construct(
        public string $teamId,
        public string $gamePlayerId,
        public int $minute,
        public string $type,
        public ?array $metadata = null,
    ) {}

    /**
     * Create a goal event.
     */
    public static function goal(string $teamId, string $gamePlayerId, int $minute): self
    {
        return new self($teamId, $gamePlayerId, $minute, 'goal');
    }

    /**
     * Create an own goal event.
     */
    public static function ownGoal(string $teamId, string $gamePlayerId, int $minute): self
    {
        return new self($teamId, $gamePlayerId, $minute, 'own_goal');
    }

    /**
     * Create an assist event.
     */
    public static function assist(string $teamId, string $gamePlayerId, int $minute): self
    {
        return new self($teamId, $gamePlayerId, $minute, 'assist');
    }

    /**
     * Create a yellow card event.
     */
    public static function yellowCard(string $teamId, string $gamePlayerId, int $minute): self
    {
        return new self($teamId, $gamePlayerId, $minute, 'yellow_card');
    }

    /**
     * Create a red card event.
     */
    public static function redCard(string $teamId, string $gamePlayerId, int $minute, bool $secondYellow = false): self
    {
        return new self($teamId, $gamePlayerId, $minute, 'red_card', [
            'second_yellow' => $secondYellow,
        ]);
    }

    /**
     * Create an injury event.
     */
    public static function injury(string $teamId, string $gamePlayerId, int $minute, string $injuryType, int $weeksOut): self
    {
        return new self($teamId, $gamePlayerId, $minute, 'injury', [
            'injury_type' => $injuryType,
            'weeks_out' => $weeksOut,
        ]);
    }

    public function toArray(): array
    {
        return [
            'team_id' => $this->teamId,
            'game_player_id' => $this->gamePlayerId,
            'minute' => $this->minute,
            'event_type' => $this->type,
            'metadata' => $this->metadata,
        ];
    }
}
