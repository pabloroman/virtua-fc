<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GameMatch extends Model
{
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'round_number' => 'integer',
        'scheduled_date' => 'datetime',
        'home_score' => 'integer',
        'away_score' => 'integer',
        'played' => 'boolean',
        'played_at' => 'datetime',
        'is_extra_time' => 'boolean',
        'home_score_et' => 'integer',
        'away_score_et' => 'integer',
        'home_score_penalties' => 'integer',
        'away_score_penalties' => 'integer',
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
