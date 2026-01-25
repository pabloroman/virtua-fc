<?php

namespace App\Game\DTO;

use Illuminate\Support\Collection;

/**
 * Data transfer object for a complete match result with events.
 */
readonly class MatchResult
{
    /**
     * @param int $homeScore Final score for home team
     * @param int $awayScore Final score for away team
     * @param Collection<MatchEventData> $events All events that occurred during the match
     */
    public function __construct(
        public int $homeScore,
        public int $awayScore,
        public Collection $events,
    ) {}

    /**
     * Get all goal events (excluding own goals).
     */
    public function goals(): Collection
    {
        return $this->events->filter(fn (MatchEventData $e) => $e->type === 'goal');
    }

    /**
     * Get all own goal events.
     */
    public function ownGoals(): Collection
    {
        return $this->events->filter(fn (MatchEventData $e) => $e->type === 'own_goal');
    }

    /**
     * Get all assist events.
     */
    public function assists(): Collection
    {
        return $this->events->filter(fn (MatchEventData $e) => $e->type === 'assist');
    }

    /**
     * Get all yellow card events.
     */
    public function yellowCards(): Collection
    {
        return $this->events->filter(fn (MatchEventData $e) => $e->type === 'yellow_card');
    }

    /**
     * Get all red card events.
     */
    public function redCards(): Collection
    {
        return $this->events->filter(fn (MatchEventData $e) => $e->type === 'red_card');
    }

    /**
     * Get all injury events.
     */
    public function injuries(): Collection
    {
        return $this->events->filter(fn (MatchEventData $e) => $e->type === 'injury');
    }

    /**
     * Get events for a specific team.
     */
    public function eventsForTeam(string $teamId): Collection
    {
        return $this->events->filter(fn (MatchEventData $e) => $e->teamId === $teamId);
    }

    /**
     * Get goals scored by a team (including opponent own goals).
     */
    public function goalsForTeam(string $teamId, string $opponentTeamId): Collection
    {
        return $this->events->filter(function (MatchEventData $e) use ($teamId, $opponentTeamId) {
            // Regular goals by this team
            if ($e->type === 'goal' && $e->teamId === $teamId) {
                return true;
            }
            // Own goals by opponent (count for this team)
            if ($e->type === 'own_goal' && $e->teamId === $opponentTeamId) {
                return true;
            }
            return false;
        });
    }

    /**
     * Check if the match was a draw.
     */
    public function isDraw(): bool
    {
        return $this->homeScore === $this->awayScore;
    }

    /**
     * Get the result string (e.g., "2 - 1").
     */
    public function getResultString(): string
    {
        return "{$this->homeScore} - {$this->awayScore}";
    }
}
