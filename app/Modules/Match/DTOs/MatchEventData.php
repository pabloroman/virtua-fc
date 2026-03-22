<?php

namespace App\Modules\Match\DTOs;

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
    /**
     * Create a substitution event.
     */
    public static function substitution(string $teamId, string $playerOutId, string $playerInId, int $minute): self
    {
        return new self($teamId, $playerOutId, $minute, 'substitution', [
            'player_in_id' => $playerInId,
        ]);
    }

    public static function injury(string $teamId, string $gamePlayerId, int $minute, string $injuryType, int $weeksOut): self
    {
        return new self($teamId, $gamePlayerId, $minute, 'injury', [
            'injury_type' => $injuryType,
            'weeks_out' => $weeksOut,
        ]);
    }

    /**
     * Create a shot on target event (narrative).
     */
    public static function shotOnTarget(string $teamId, string $gamePlayerId, int $minute): self
    {
        return new self($teamId, $gamePlayerId, $minute, 'shot_on_target');
    }

    /**
     * Create a shot off target event (narrative).
     */
    public static function shotOffTarget(string $teamId, string $gamePlayerId, int $minute): self
    {
        return new self($teamId, $gamePlayerId, $minute, 'shot_off_target');
    }

    /**
     * Create a dangerous attack event (narrative).
     */
    public static function dangerousAttack(string $teamId, string $gamePlayerId, int $minute): self
    {
        return new self($teamId, $gamePlayerId, $minute, 'dangerous_attack');
    }

    /**
     * Create a great save event (narrative).
     */
    public static function greatSave(string $teamId, string $gamePlayerId, int $minute): self
    {
        return new self($teamId, $gamePlayerId, $minute, 'great_save');
    }

    /**
     * Create a near miss event (narrative).
     */
    public static function nearMiss(string $teamId, string $gamePlayerId, int $minute): self
    {
        return new self($teamId, $gamePlayerId, $minute, 'near_miss');
    }

    /**
     * Create a key pass event (narrative).
     */
    public static function keyPass(string $teamId, string $gamePlayerId, int $minute): self
    {
        return new self($teamId, $gamePlayerId, $minute, 'key_pass');
    }

    /**
     * Create a tactical insight event.
     */
    public static function insight(string $teamId, int $minute, string $insightKey, array $params = []): self
    {
        return new self($teamId, '', $minute, 'insight', [
            'insight_key' => $insightKey,
            'params' => $params,
        ]);
    }

    /**
     * Check if this is a narrative event (not persisted to DB).
     */
    public function isNarrative(): bool
    {
        return in_array($this->type, self::NARRATIVE_TYPES);
    }

    /**
     * Event types that are narrative only (not stored in DB).
     */
    public const NARRATIVE_TYPES = [
        'shot_on_target',
        'shot_off_target',
        'dangerous_attack',
        'great_save',
        'near_miss',
        'key_pass',
        'insight',
    ];

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
