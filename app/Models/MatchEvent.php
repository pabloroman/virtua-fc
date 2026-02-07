<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchEvent extends Model
{
    use HasUuids;

    const UPDATED_AT = null;

    protected $fillable = [
        'game_id',
        'game_match_id',
        'game_player_id',
        'team_id',
        'minute',
        'event_type',
        'metadata',
    ];

    protected $casts = [
        'minute' => 'integer',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    // Event types
    public const TYPE_GOAL = 'goal';
    public const TYPE_OWN_GOAL = 'own_goal';
    public const TYPE_ASSIST = 'assist';
    public const TYPE_YELLOW_CARD = 'yellow_card';
    public const TYPE_RED_CARD = 'red_card';
    public const TYPE_INJURY = 'injury';

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function gameMatch(): BelongsTo
    {
        return $this->belongsTo(GameMatch::class);
    }

    public function gamePlayer(): BelongsTo
    {
        return $this->belongsTo(GamePlayer::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Check if this event is a scoring event (goal or own goal).
     */
    public function isGoal(): bool
    {
        return in_array($this->event_type, [self::TYPE_GOAL, self::TYPE_OWN_GOAL]);
    }

    /**
     * Check if this event is a card.
     */
    public function isCard(): bool
    {
        return in_array($this->event_type, [self::TYPE_YELLOW_CARD, self::TYPE_RED_CARD]);
    }

    /**
     * Get the player name via relationship.
     */
    public function getPlayerNameAttribute(): string
    {
        return $this->gamePlayer->player->name;
    }

    /**
     * Get display string for the event (e.g., "45' Goal - Vinicius Jr.")
     */
    public function getDisplayStringAttribute(): string
    {
        $minute = $this->minute . "'";
        $player = $this->player_name;

        return match ($this->event_type) {
            self::TYPE_GOAL => "{$minute} Goal - {$player}",
            self::TYPE_OWN_GOAL => "{$minute} Own Goal - {$player}",
            self::TYPE_ASSIST => "{$minute} Assist - {$player}",
            self::TYPE_YELLOW_CARD => "{$minute} Yellow Card - {$player}",
            self::TYPE_RED_CARD => "{$minute} Red Card - {$player}",
            self::TYPE_INJURY => "{$minute} Injury - {$player}",
            default => "{$minute} {$this->event_type} - {$player}",
        };
    }
}
