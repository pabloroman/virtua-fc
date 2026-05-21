<?php

namespace App\Models;

use App\Modules\Match\Enums\MatchPhase;
use App\Modules\Match\Support\MinuteCoordinates;
use App\Modules\Match\Support\StoppageDurations;
use Illuminate\Database\Eloquent\Builder;
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
 * @property MatchPhase $phase
 * @property int|null $stoppage_minute
 * @property string $event_type
 * @property array<array-key, mixed>|null $metadata
 * @property-read \App\Models\Game $game
 * @property-read \App\Models\GameMatch $gameMatch
 * @property-read \App\Models\GamePlayer $gamePlayer
 * @property-read string $display_string
 * @property-read string $player_name
 * @property-read \App\Models\Team $team
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MatchEvent newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MatchEvent newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MatchEvent query()
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

    public $timestamps = false;

    /**
     * Auto-decompose `minute` (raw absolute) into (phase, base, stoppage)
     * when `phase` isn't supplied. Lets `MatchEvent::create(['minute' => 47])`
     * keep working — tests and ad-hoc creators don't have to know about the
     * phase tuple.
     *
     * The bulk-insert paths (MatchEventRepository, MatchResultProcessor,
     * TacticalChangeService) decompose explicitly because `Model::insert`
     * skips this hook.
     */
    protected static function booted(): void
    {
        static::creating(function (self $event): void {
            if ($event->phase !== null) {
                return;
            }

            $coords = MinuteCoordinates::decomposeWith(
                (int) $event->minute,
                $event->resolveStoppageDurations(),
            );

            $event->phase = $coords['phase'];
            $event->minute = $coords['minute'];
            $event->stoppage_minute = $coords['stoppage_minute'];
        });
    }

    /**
     * Match-level stoppage values for decomposition, falling back to a
     * historical "second half got 3' of stoppage" default when the match
     * has no stoppage set yet — keeps factory fixtures working before
     * stoppage sampling lands.
     */
    private function resolveStoppageDurations(): StoppageDurations
    {
        $match = $this->gameMatch;

        if ($match === null) {
            return new StoppageDurations(0, 3);
        }

        $stoppage = StoppageDurations::fromMatch($match);

        return $match->second_half_stoppage === null
            ? new StoppageDurations($stoppage->firstHalf, 3, $stoppage->etFirstHalf, $stoppage->etSecondHalf)
            : $stoppage;
    }

    protected $fillable = [
        'game_id',
        'game_match_id',
        'game_player_id',
        'team_id',
        'minute',
        'phase',
        'stoppage_minute',
        'event_type',
        'metadata',
    ];

    protected $casts = [
        'minute' => 'integer',
        'phase' => MatchPhase::class,
        'stoppage_minute' => 'integer',
        'metadata' => 'array',
    ];

    // Event types
    public const TYPE_GOAL = 'goal';
    public const TYPE_OWN_GOAL = 'own_goal';
    public const TYPE_ASSIST = 'assist';
    public const TYPE_YELLOW_CARD = 'yellow_card';
    public const TYPE_RED_CARD = 'red_card';
    public const TYPE_INJURY = 'injury';
    public const TYPE_PENALTY_MISSED = 'penalty_missed';
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
        return $this->gamePlayer->name;
    }

    /**
     * Render the minute as it should appear in the UI — "45+2'" for stoppage,
     * "47'" for open play. Penalty-shootout events have no minute.
     */
    public function displayMinute(): string
    {
        if ($this->phase === MatchPhase::PENALTIES) {
            return '';
        }

        return $this->stoppage_minute
            ? "{$this->minute}+{$this->stoppage_minute}'"
            : "{$this->minute}'";
    }

    /**
     * The simulator's raw absolute clock time for this event, derived from
     * the persisted phase tuple + the match's stoppage values. Used by the
     * resimulation service when it needs to compare DB-loaded events to
     * newly-generated ones on a common axis.
     */
    public function absoluteMinute(): int
    {
        return MinuteCoordinates::toAbsoluteWith(
            $this->phase,
            $this->minute,
            $this->stoppage_minute,
            StoppageDurations::fromMatch($this->gameMatch),
        );
    }

    /**
     * Chronological ordering across phase + minute + stoppage_minute. Use
     * this instead of `orderBy('minute')` — minute alone is ambiguous in
     * stoppage time. The CASE arms are generated from `MatchPhase::ordinal()`
     * so adding a phase only requires updating the enum.
     */
    public function scopeOrderedChronologically(Builder $query): Builder
    {
        $arms = array_map(
            fn (MatchPhase $p) => "WHEN '{$p->value}' THEN {$p->ordinal()}",
            MatchPhase::cases(),
        );

        return $query
            ->orderByRaw('CASE phase '.implode(' ', $arms).' ELSE 99 END')
            ->orderBy('minute')
            ->orderByRaw('COALESCE(stoppage_minute, 0)');
    }

    /**
     * Get display string for the event (e.g., "45' Goal - Vinicius Jr.")
     */
    public function getDisplayStringAttribute(): string
    {
        $minute = $this->displayMinute();
        $player = $this->player_name;

        return match ($this->event_type) {
            self::TYPE_GOAL => "{$minute} Goal - {$player}",
            self::TYPE_OWN_GOAL => "{$minute} Own Goal - {$player}",
            self::TYPE_ASSIST => "{$minute} Assist - {$player}",
            self::TYPE_YELLOW_CARD => "{$minute} Yellow Card - {$player}",
            self::TYPE_RED_CARD => "{$minute} Red Card - {$player}",
            self::TYPE_INJURY => "{$minute} Injury - {$player}",
            self::TYPE_PENALTY_MISSED => "{$minute} Penalty Missed - {$player}",
            default => "{$minute} {$this->event_type} - {$player}",
        };
    }
}
