<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $game_id
 * @property string $game_match_id
 * @property string $game_player_id
 * @property string $team_id
 * @property int $minute
 * @property string $event_type
 * @property array<array-key, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon $created_at
 * @property-read \App\Models\Game $game
 * @property-read \App\Models\GameMatch $gameMatch
 * @property-read \App\Models\GamePlayer $gamePlayer
 * @property-read string $display_string
 * @property-read string $player_name
 * @property-read \App\Models\Team $team
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MatchEvent newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MatchEvent newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MatchEvent query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MatchEvent whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MatchEvent whereEventType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MatchEvent whereGameId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MatchEvent whereGameMatchId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MatchEvent whereGamePlayerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MatchEvent whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MatchEvent whereMetadata($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MatchEvent whereMinute($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MatchEvent whereTeamId($value)
 * @mixin \Eloquent
 */
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
    public const TYPE_SUBSTITUTION = 'substitution';

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
