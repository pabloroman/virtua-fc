<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $game_id
 * @property string $competition_id
 * @property int $round_number
 * @property string|null $round_name
 * @property string $home_team_id
 * @property string $away_team_id
 * @property \Illuminate\Support\Carbon $scheduled_date
 * @property int|null $home_score
 * @property int|null $away_score
 * @property bool $played
 * @property string|null $cup_tie_id
 * @property bool $is_extra_time
 * @property int|null $home_score_et
 * @property int|null $away_score_et
 * @property int|null $home_score_penalties
 * @property int|null $away_score_penalties
 * @property array<array-key, mixed>|null $home_lineup
 * @property array<array-key, mixed>|null $away_lineup
 * @property string|null $home_formation
 * @property string|null $away_formation
 * @property string|null $home_mentality
 * @property string|null $away_mentality
 * @property array<array-key, mixed>|null $substitutions
 * @property-read \App\Models\Team $awayTeam
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\MatchEvent> $cardEvents
 * @property-read int|null $card_events_count
 * @property-read \App\Models\Competition $competition
 * @property-read \App\Models\CupTie|null $cupTie
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\MatchEvent> $events
 * @property-read int|null $events_count
 * @property-read \App\Models\Game $game
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\MatchEvent> $goalEvents
 * @property-read int|null $goal_events_count
 * @property-read \App\Models\Team $homeTeam
 * @method static \Database\Factories\GameMatchFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameMatch newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameMatch newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameMatch query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameMatch whereAwayFormation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameMatch whereAwayLineup($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameMatch whereAwayMentality($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameMatch whereAwayScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameMatch whereAwayScoreEt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameMatch whereAwayScorePenalties($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameMatch whereAwayTeamId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameMatch whereCompetitionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameMatch whereCupTieId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameMatch whereGameId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameMatch whereHomeFormation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameMatch whereHomeLineup($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameMatch whereHomeMentality($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameMatch whereHomeScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameMatch whereHomeScoreEt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameMatch whereHomeScorePenalties($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameMatch whereHomeTeamId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameMatch whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameMatch whereIsExtraTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameMatch wherePlayed($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameMatch whereRoundName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameMatch whereRoundNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameMatch whereScheduledDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GameMatch whereSubstitutions($value)
 * @mixin \Eloquent
 */
class GameMatch extends Model
{
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'game_id',
        'competition_id',
        'round_number',
        'round_name',
        'home_team_id',
        'away_team_id',
        'scheduled_date',
        'home_score',
        'away_score',
        'played',
        'home_lineup',
        'away_lineup',
        'home_formation',
        'away_formation',
        'home_mentality',
        'away_mentality',
        'home_playing_style',
        'away_playing_style',
        'home_pressing',
        'away_pressing',
        'home_defensive_line',
        'away_defensive_line',
        'cup_tie_id',
        'is_extra_time',
        'home_score_et',
        'away_score_et',
        'home_score_penalties',
        'away_score_penalties',
        'substitutions',
    ];

    protected $casts = [
        'round_number' => 'integer',
        'scheduled_date' => 'datetime',
        'home_score' => 'integer',
        'away_score' => 'integer',
        'home_lineup' => 'array',
        'away_lineup' => 'array',
        'home_formation' => 'string',
        'away_formation' => 'string',
        'home_mentality' => 'string',
        'away_mentality' => 'string',
        'home_playing_style' => 'string',
        'away_playing_style' => 'string',
        'home_pressing' => 'string',
        'away_pressing' => 'string',
        'home_defensive_line' => 'string',
        'away_defensive_line' => 'string',
        'played' => 'boolean',
        'is_extra_time' => 'boolean',
        'home_score_et' => 'integer',
        'away_score_et' => 'integer',
        'home_score_penalties' => 'integer',
        'away_score_penalties' => 'integer',
        'substitutions' => 'array',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    public function homeTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'home_team_id');
    }

    public function awayTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'away_team_id');
    }

    public function cupTie(): BelongsTo
    {
        return $this->belongsTo(CupTie::class);
    }

    public function isCupMatch(): bool
    {
        return $this->cup_tie_id !== null;
    }

    public function events(): HasMany
    {
        return $this->hasMany(MatchEvent::class, 'game_match_id')->orderBy('minute');
    }

    /**
     * Get goal events for this match.
     */
    public function goalEvents(): HasMany
    {
        return $this->events()->whereIn('event_type', ['goal', 'own_goal']);
    }

    /**
     * Get card events for this match.
     */
    public function cardEvents(): HasMany
    {
        return $this->events()->whereIn('event_type', ['yellow_card', 'red_card']);
    }

    public function involvesTeam(string $teamId): bool
    {
        return $this->home_team_id === $teamId || $this->away_team_id === $teamId;
    }

    public function isHomeTeam(string $teamId): bool
    {
        return $this->home_team_id === $teamId;
    }

    public function getOpponentFor(string $teamId): ?Team
    {
        if ($this->home_team_id === $teamId) {
            return $this->awayTeam;
        }
        if ($this->away_team_id === $teamId) {
            return $this->homeTeam;
        }
        return null;
    }

    public function getResultString(): string
    {
        if (!$this->played) {
            return '-';
        }
        return "{$this->home_score} - {$this->away_score}";
    }

    public function getWinnerId(): ?string
    {
        if (!$this->played) {
            return null;
        }
        if ($this->home_score > $this->away_score) {
            return $this->home_team_id;
        }
        if ($this->away_score > $this->home_score) {
            return $this->away_team_id;
        }
        return null; // Draw
    }
}
