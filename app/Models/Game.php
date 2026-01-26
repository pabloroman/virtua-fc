<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Game extends Model
{
    use HasFactory, HasUuids;

    protected $guarded = [];

    protected $casts = [
        'current_date' => 'date',
        'current_matchday' => 'integer',
        'cup_round' => 'integer',
        'cup_eliminated' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(GameMatch::class);
    }

    public function standings(): HasMany
    {
        return $this->hasMany(GameStanding::class);
    }

    public function players(): HasMany
    {
        return $this->hasMany(GamePlayer::class);
    }

    public function cupTies(): HasMany
    {
        return $this->hasMany(CupTie::class);
    }

    /**
     * Get players for the user's team.
     */
    public function squad(): HasMany
    {
        return $this->players()->where('team_id', $this->team_id);
    }

    public function getCompetitionIdAttribute(): string
    {
        // Determine competition based on team
        return $this->team?->competitions()->first()?->id ?? 'ESP1';
    }

    public function getNextMatchAttribute(): ?GameMatch
    {
        return $this->matches()
            ->where('played', false)
            ->where(function ($query) {
                $query->where('home_team_id', $this->team_id)
                    ->orWhere('away_team_id', $this->team_id);
            })
            ->orderBy('scheduled_date')
            ->first();
    }
}
